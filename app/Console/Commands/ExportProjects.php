<?php
/**
 * Export Projects
 *
 * (c) Youssef Jradeh <youssef.jradeh@ideatolife.me>
 *
 */

namespace App\Console\Commands;

use App\Models\FormCategory;
use App\Models\Project;
use App\Models\Question;
use App\Models\QuestionResponseType;
use App\Models\SiteReference;
use App\Models\ClusterReference;
use App\Models\ProjectDetail;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExportProjects extends Command
{

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'export:projects';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Export projects to Excel files';

    protected $tableName = "";

    protected $tableNameFinal = "";

    protected $siteQuestion;
    protected $gpsQuestion;
    protected $questionOptionMax = [];

    public function fixOldValues($project)
    {
        //prepare
        $pdo = DB::getPdo();
        $pdo->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);

        $questions = [];
        if(isset($project->form->id) && $project->form->id > 0){
            $questions = Question::with("options")->where("form_id", $project->form->id)->whereNull("deleted_at")
                ->orderBy("id")
                ->get();
        }
        
        foreach ($questions as $question) {
            if (!$question->multiple) {
                continue;
            }

            $statement = $pdo->prepare(
                "select id,form_instance_id,individual_chunk from question_answers where multiple>0 AND question_id=" . $question->id . " order by form_instance_id,individual_chunk ASC"
            );
            $statement->setFetchMode(\PDO::FETCH_ASSOC);
            $statement->execute();
            $data = $statement->fetchAll();

            $i = 0;
            $currentInstance = "";
            $currentChunk = "";

            foreach ($data as $row) {
                $i++;
                if ($currentInstance != $row['form_instance_id'] || $currentChunk != $row['individual_chunk']) {
                    $i = 1;
                }
                $currentInstance = $row['form_instance_id'];
                $currentChunk = $row['individual_chunk'];
                $updateQuery = "update question_answers set multiple=$i where id=" . $row['id'];

                $pdo->exec($updateQuery);
            }
        }
//        exit;
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function fire()
    {
        $this->info('Starting!'); // for console
        Log::info('Starting!'); // for lumen.log

        //get all project with date end is greator than or equal yesterday
        $projects = Project::where("status", 1)->where("id",">",200)->get();
                    // ->whereDate('date_end', '>=', Carbon::yesterday())->where("id",1)

        //for each project create the dump
        foreach ($projects as $project) {
            try {
                //to be run one time only
                //                $this->fixOldValues($project);
                $this->handleProject($project->id);
                $this->exportPercentageExportBySite($project);
                $this->exportPercentageExportByCluster($project);
            } catch (\Exception $exception) {
                $this->info('message ' . $exception->getMessage());
                $this->info('stack ' . $exception->getTraceAsString());
            }

            //then drop tables
            if ($this->tableName) {
                DB::unprepared(DB::raw("DROP TABLE $this->tableName"));
            }
            if ($this->tableNameFinal) {
                DB::unprepared(DB::raw("DROP TABLE $this->tableNameFinal"));
            }
        }

        //mark command as done
        $this->info('Done!'); // for console
        Log::info('Done!'); // for lumen.log
    }

    public function handleProject($id)
    {
        $this->info('start ExportProject of project ' . $id);

        //prepare
        $pdo = DB::getPdo();
        $pdo->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);

        $project = Project::with("form")->find($id);
        $questionTypes = QuestionResponseType::pluck("code", "id")->all();
        
        $questions = [];
        if(isset($project->form->id) && $project->form->id > 0){
        $questions = Question::with("options")->where("form_id", $project->form->id)->whereNull("deleted_at")
            ->orderBy("id")
            ->get();
        }
        
        $columns = $clearColumns = $clearColumnsFull = [];
        
        foreach ($questions as $question) {
            // ECAP-148: returning the complete string
            $clearColumns[$question->id] = $this->clean($question->name_en);
            $clearColumnsFull[$question->id] = $this->clean($question->name_en, false);

            //if multiple create multiple columns (per count NOT per options)
            if ($question->multiple) {

                //get the maximum number of answers to know how many column
                $this->questionOptionMax[$question->id] = $this->getMaximumAnswersPerQuestion($question->id);
                if (!$this->questionOptionMax[$question->id]) {
                    continue;
                }

                //add multiple answers columns
                $toMax = 1;
                while ($toMax <= $this->questionOptionMax[$question->id]) {
                    $columns[$question->id . "." . $toMax] = "q_" . $question->id . "__" . $toMax;
                    $toMax++;
                }
            } else {
                //else it's a normal question with one answer
                $columns[$question->id] = "q_" . $question->id;
            }
        }

        //create temporary table
        $this->tableName = $tableName = "tmp_{$id}_" . time() . "_" . rand();
        $this->tableNameFinal = $tableNameFinal = $tableName . "_final";
        $this->createTemporaryTables(
            $tableName,
            $columns,
            $pdo,
            $tableNameFinal,
            $project
        );

        //insert main data
        $insertQuery = 'INSERT INTO ' . $tableName . ' (form_instance_id,individual_chunk,collector,date,begin,end) ' .
            ' select f.id,0,u.username,DATE(f.date_start),TIME(f.date_start),TIME(f.date_end)' .
            ' from form_instances f' .
            ' left join users u on f.user_id=u.id ' .
            ' left join project_members pm on pm.user_id=u.id and pm.project_id=' . $id .
            ' where f.project_id=' . $id;
        $pdo->exec($insertQuery);

        //custom query to group by question_id`,value
        $i = 0;
        foreach ($questions as $question) {
            //get the select query and increment $i if needed
            list($i, $selectQuestion) = $this->getSelectQuery(
                $id,
                $question,
                $i,
                $questionTypes
            );

            //if site , then adding governorate as well
            $questionToAdd = $i == 1 ? "q_$question->id,governorate" : "q_$question->id";

            //insert the selected query
            if ($question->multiple) {
                $toMax = 1;
                while ($toMax <= $this->questionOptionMax[$question->id]) {
                    $insertQuery = "INSERT INTO $tableName (form_instance_id,individual_chunk,$questionToAdd" . '__' . $toMax . ") $selectQuestion AND qa.multiple=$toMax";
                    $pdo->exec($insertQuery);
                    $toMax++;
                }
            } else {
                $insertQuery = "INSERT INTO $tableName (form_instance_id,individual_chunk,$questionToAdd) $selectQuestion";
                $pdo->exec($insertQuery);
            }
        }

        //move all data to the new temporary table grouping by form_instance_id,individual_chunk
        $this->moveToFinalTable($columns, $tableName, $tableNameFinal, $pdo);
        $this->info('export:insert the grouping by');

        //and delete first table records
        $this->info('export:table one deleted');

        list($columnsToSelect, $columnsToSelectAsArray, $secondRowData) = $this->returnColumnToSelect(
            $questions,
            $columns,
            $clearColumns,
            $clearColumnsFull
        );

        $writer = new \XLSXWriter();
        $writer->writeSheetHeader(
            'FullData',
            [
                'form_instance_id' => 'string',
                'individual_chunk' => 'string',
                'collector' => 'string',
                'date' => 'string',
                'begin' => 'string',
                'end' => 'string',
                'governorate' => 'string',
            ] + $columnsToSelectAsArray
        );
        $writer->writeSheetRow('FullData', $secondRowData);
        
        $loop = true;
        while ($loop == true) {
            try {
                $this->info("LIMIT $i,500");

                $statement = $pdo->prepare(
                    "select " . $columnsToSelect . " from $tableNameFinal LIMIT $i,500"
                );
                $statement->setFetchMode(\PDO::FETCH_ASSOC);
                $statement->execute();
                $data = $statement->fetchAll();
                foreach ($data as $row) {
                    array_walk_recursive($row, 'dot_if_empty');
                    $writer->writeSheetRow('FullData', $row);//array_map('utf8_encode', $row)
                }

                $i += 500;
                // Nothing to do, we've finished.
                if (!count($data)) {
                    $loop = false;
                }
                $data = null;
            } catch (PDOException $e) {
                $this->info(" Reason given:" . $e->getMessage());
            }
        }
        //delete the final table
        $this->info('export:table final deleted');

        $writer->writeToFile(
            storage_path('exports') . "/" . $project->name . '.xlsx'
        );

        $this->info('end ExportProject of project ' . $id);
    }

    private function clean($string, $supperClean = true)
    {
        $string = str_replace(
            ' ',
            '_',
            strtolower($string)
        );

        // Replaces all spaces with hyphens.
        $string = preg_replace(
            '/[^A-Za-z\_]/',
            '',
            $string
        ); // Removes special chars.

        return $supperClean ? substr($string, 0, 90) : strtolower($string);
    }

    /**
     * @param $tableName
     * @param $columns
     * @param $pdo
     * @param $tableNameFinal
     *
     */
    private function createTemporaryTables(
        $tableName,
        $columns,
        $pdo,
        $tableNameFinal,
        $project
    ) {

        //get site question to add index on it
        if(isset($project->form->id) && $project->form->id > 0){
            $this->siteQuestion = Question::where("form_id", $project->form->id)->whereNull("deleted_at")
                ->whereRaw('lower(name_en) like "%name of the site%"')
                ->first();
        }
        if(isset($project->form->id) && $project->form->id > 0){
            $this->gpsQuestion = Question::where("form_id", $project->form->id)->whereNull("deleted_at")
                ->whereRaw('lower(name_en) like "%gps of cluster%"')
                ->first();
        }
        
        $sql = "CREATE TABLE `$tableName` (
`form_instance_id` bigint(20) unsigned NOT NULL,
`individual_chunk` int(10) NOT NULL DEFAULT '0',
`collector` varchar(255) COLLATE utf8mb4_unicode_ci  DEFAULT '',
`date` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '',
`begin` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '',
`end` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '',
`governorate` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '',";

        foreach ($columns as $row) {
            if ($row == 'q_' . $this->siteQuestion->id) {
                $sql .= "`{$row}` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,";
            } elseif ($row == 'q_' . $this->gpsQuestion->id) {
                $sql .= "`{$row}` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,";
                $sql .= "`longitude` decimal(10,8) DEFAULT NULL,";
            } else {
                $sql .= "`{$row}` TEXT,";
            }
        }

        $sql = substr($sql, 0, -1) . " )ENGINE = MYISAM CHARSET=utf8;";
        $pdo->exec($sql);
        $pdo->exec(
            "create index index_$tableName on $tableName (form_instance_id, individual_chunk);"
        );

        $this->info('export:TEMPORARY table created ' . $tableName);

        //Add final data table
        $pdo->exec(str_replace($tableName, $tableNameFinal, $sql));
        $this->info('export:TEMPORARY table created ' . $tableNameFinal);
        $pdo->exec(
            "create index i0_$tableNameFinal on $tableNameFinal (form_instance_id, individual_chunk);"
        );

        $pdo->exec(
            "create index i1_$tableNameFinal on $tableNameFinal (q_{$this->siteQuestion->id}(50));"
        );
    }

    /**
     * @param $id
     * @param $question
     * @param $i
     * @param $questionTypes
     *
     * @return array
     */
    private function getSelectQuery($id, $question, $i, $questionTypes)
    {
        $this->info('export:fetching for question ' . $question->id);
        //site name
        if ($i == 0) {
            $i++;
            $selectQuestion = "select f.id,0,s.name,g.name  from form_instances f" .
                " left join site_references s on f.site_id=s.id " .
                " left join governorates g on g.id=s.governorate_id " .
                " where f.project_id={$id}";
        } //site cluster
        elseif ($i == 1) {
            $i++;
            $selectQuestion = "select f.id,0,c.name  from form_instances f left join cluster_references c on f.cluster_id=c.id where f.project_id={$id}";
        } //long lat
        elseif ($i == 2) {
            $i++;
            $selectQuestion = "select f.id,0,concat(f.lat,',',f.lng) from form_instances f where f.project_id={$id}";
        } //other questions
        else {
            if (in_array(
                $questionTypes[$question->response_type_id],
                [
                    'yes_no',
                    'multiple_choice',
                ]
            )) {
                $selectQuestion = "select qa.form_instance_id,qa.individual_chunk,qo.name_en from question_answers qa " .
                    "left join question_options qo on qo.id=qa.value " .
                    "where qa.question_id={$question->id} AND qa.project_id={$id}";
            } else {
                $selectQuestion = "select qa.form_instance_id,qa.individual_chunk,qa.value from question_answers qa where qa.question_id={$question->id} AND qa.project_id={$id}";
            }
        }

        return [$i, $selectQuestion];
    }

    /**
     * @param $columns
     * @param $tableName
     * @param $tableNameFinal
     * @param $pdo
     */
    private function moveToFinalTable(
        $columns,
        $tableName,
        $tableNameFinal,
        $pdo
    ) {
        $columnsText = "GROUP_CONCAT(" . implode("),GROUP_CONCAT(", $columns) . ")";
        $columnsText = str_replace(")", "  SEPARATOR ' , ')", $columnsText);

        $selectQuestion = "select form_instance_id,individual_chunk,MAX(collector),MAX(date),
MAX(begin),MAX(end),MAX(governorate),$columnsText from $tableName group by form_instance_id,individual_chunk";

        //save to the new table
        $insertQuery = 'INSERT into ' . $tableNameFinal . ' (form_instance_id,individual_chunk,collector,date,begin,end,governorate,' . implode(
            ",",
            $columns
        ) . ') ' . $selectQuestion;

        $pdo->exec($insertQuery);

        //update main info
        $siteColumn = current($columns);
        $clusterColumn = current($columns);
        $updateQuery = 'update ' . $tableNameFinal . ' a ' .
            'left join ' . $tableNameFinal . ' b on a.form_instance_id=b.form_instance_id and b.individual_chunk=0 ' .
            'set a.collector=b.collector, ' .
            'a.date=b.date,' .
            'a.begin=b.begin,' .
            'a.end=b.end,' .
            'a.governorate=b.governorate,' .
            'a.' . $siteColumn . '=b.' . $siteColumn . ',' .
            'a.' . $clusterColumn . '=b.' . $clusterColumn . ' ' .
            'where a.individual_chunk!=0';

        $pdo->exec($updateQuery);

        $updateLong = "UPDATE $tableNameFinal SET longitude = SUBSTRING_INDEX(q_{$this->gpsQuestion->id}, ',', -1)";
        $pdo->exec($updateLong);
//
        $updateLat = "UPDATE $tableNameFinal SET q_{$this->gpsQuestion->id} = SUBSTRING_INDEX(q_{$this->gpsQuestion->id}, ',', 1)";
        $pdo->exec($updateLat);

        //create another column for long and latitude
        $renameQuery = "ALTER TABLE $tableNameFinal CHANGE q_{$this->gpsQuestion->id} latitude decimal(10,8)";
        $pdo->exec($renameQuery);
    }

    /**
     * @param $questions
     * @param $columns
     * @param $clearColumns
     *
     * @return array
     */
    private function returnColumnToSelect($questions, $columns, $clearColumns, $clearColumnsFull)
    {
        $i=8;
        $columnsToSelect = "form_instance_id,individual_chunk,collector,date,begin,end,governorate,";
        $secondRowData = [0=>"1-",1=>"2-",2=>"3-",3=>"4-",4=>"5-",5=>"6-",6=>"7-"];
        $columnsToSelectAsArray = [];
        foreach ($questions as $question) {
            if ($question->id == $this->gpsQuestion->id) {
                $columnsToSelect .= "latitude,";
                $columnsToSelect .= "longitude,";
                $columnsToSelectAsArray["latitude"] = 'string';
                $columnsToSelectAsArray["longitude"] = 'string';
                $secondRowData[] = ($i."-"); 
                $secondRowData[] = (++$i."-");
            } elseif ($question->multiple) {
                $toMax = 1;
                while ($toMax <= $this->questionOptionMax[$question->id]) {
                    if($clearColumns[$question->id] != ""){
                        $columnsToSelect .= $columns[$question->id . "." . $toMax] . " as " . $clearColumns[$question->id] . "_" . $toMax . ",";
                        $columnsToSelectAsArray[$clearColumnsFull[$question->id] . "." . $toMax] = 'string';
                        $secondRowData[] = ($i."-".$question->question_code);
                        $toMax++;
                    }
                }
            } else {
                if($clearColumns[$question->id] != ""){
                    $columnsToSelect .= $columns[$question->id] . " as `" . $clearColumns[$question->id] . "`,";
                    $columnsToSelectAsArray[$clearColumnsFull[$question->id]] = 'string';
                    $secondRowData[] = ($i."-".$question->question_code);
                }
            }            
            $i++;
        }
        $columnsToSelect = substr($columnsToSelect, 0, -1);

        $this->info('export:select all data to be exported');

        return [$columnsToSelect, $columnsToSelectAsArray, $secondRowData];
    }

    public function getMaximumAnswersPerQuestion($id)
    {
        $pdo = DB::getPdo();
        $statement = $pdo->prepare(
            "select max(multiple) AS multiple from question_answers where question_id = :id"
        );
        $statement->execute([':id' => $id]);
        $result = $statement->fetch(\PDO::FETCH_ASSOC);
        return !empty($result['multiple']) ? $result['multiple'] : 0;
    }

    /**
     * the following method is used to export project percentage data by cluster
     *
     * @param [type] $project
     * @return void
     */
    public function exportPercentageExportByCluster($project)
    {
        $this->info('start exportPercentageExportByCluster of project ' . $project->id);

        //prepare
        $pdo = DB::getPdo();
        $pdo->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);

        //$clusterIds = ProjectDetail::Where("project_id", $project->id)->pluck("cluster_id", "cluster_id")->toArray();
        $clusterIds = \App\Models\FormInstance::Where("project_id", $project->id)->pluck("cluster_id", "cluster_id")->toArray();
        
        if(!empty($clusterIds))
            $clusters = ClusterReference::whereIn("id", $clusterIds)->orderBy("name")->pluck("name", "id")->all();
        else
            $clusters = [];

        $clusterColumns = [];
        foreach ($clusters as $key =>$cluster) {
            $tempCluster = ClusterReference::find($key);
            $site = SiteReference::where("id", $tempCluster->site_id)->first();
            // $clusterColumns[$cluster] = 'string';
            if(isset($clusterColumns[$site->name." / ".$cluster])){
                $clusterColumns[$site->name." / ".$cluster] = 'string';
            }else{
                $clusterColumns[$site->name." / ".$cluster] = 'string';
            }
            // $clusterColumns = $clusterColumns + array($cluster => 'string');
        }
        // sort the data by site name first i.e. group the data by site
        ksort($clusterColumns);

        $writer = new \XLSXWriter();
        $writer->writeSheetHeader(
            'ClusterPercentageData',
            [
                'Category' => 'string',
                'Question' => 'string',
                'Option' => 'string',
            ] + $clusterColumns
        );

        //write AGE data
        $ageMetrics = ['<5' => 'BETWEEN 0 AND 4', '5-14' => 'BETWEEN 5 AND 14', '15-44' => 'BETWEEN 15 AND 44', '45+' => '> 44'];

        //get Age question
        if(isset($project->form->id) && $project->form->id > 0){
            $ageQuestion = Question::where("form_id", $project->form->id)->whereNull("deleted_at")
                ->whereRaw('LOWER(name_en) = ?', 'age')
                ->first();
        
            if(!empty($ageQuestion)){
                //loop on metric to add Age Data
                $loop = 0;
                foreach ($ageMetrics as $key => $if) {
                    $loop++;
                    $ageData = [];
                    //loop on clusters
                    foreach ($clusters as $id => $cluster) {
                        $statement = $pdo->prepare(
                            "SELECT Concat(Round(( Count(qa.value) / (SELECT Count(qa.value)
                                                 FROM   question_answers qa
                                                        INNER JOIN form_instances fi ON qa.form_instance_id = fi.id
                                                 WHERE  qa.question_id = {$ageQuestion->id}
                                                        AND fi.cluster_id = $id
                                                 GROUP  BY fi.cluster_id) * 100 ), 2), '%') AS percentage
                                                 FROM   question_answers qa
                                                 INNER JOIN form_instances fi ON qa.form_instance_id = fi.id
                                                 WHERE  qa.question_id = {$ageQuestion->id}
                                                 AND qa.value $if
                                                 AND fi.cluster_id = $id
                                                 GROUP  BY fi.cluster_id"
                                                 );
                        $statement->execute();
                        $result = $statement->fetch(\PDO::FETCH_ASSOC);
                        $ageData[] = !empty($result['percentage']) ? $result['percentage'] : ".";
                    }

                    array_unshift($ageData, $loop == 1 ? "Age group" : "", $loop == 1 ? "Age" : "", $key);
                    $writer->writeSheetRow('ClusterPercentageData', $ageData);
                }
            }
        }
        //loop on all questions
        $categories = [];
        if(isset($project->form->id) && $project->form->id > 0){
            $categories = FormCategory::where("form_id", $project->form->id)->whereNull("deleted_at")
                ->with(
                    [
                        'groups',
                        'groups.questions' => function ($query) {
                            $query->whereIn("response_type_id", [1, 2, 4])
                                ->whereRaw('LOWER(name_en) != ?', 'age');
                        },
                    ]
                )
                ->get();
        }
        //line separator
        $writer->writeSheetRow('ClusterPercentageData', []);

        $cLoop = $qLoop = 0;
        foreach ($categories as $category) {
            $cLoop++;
            foreach ($category->groups as $group) {
                foreach ($group->questions as $question) {
                    $qLoop++;
                    $questionName = $question->name_en;
                    //Numeric or Other
                    if ($question->options->count() == 0) {
                        $dataToAdd = $this->getQuestionCountForClusters($clusters, $pdo, $question);
                        array_unshift($dataToAdd, $cLoop == 1 ? $category->name : "", $qLoop == 1 ? $question->name_en : "", "");
                        $writer->writeSheetRow('ClusterPercentageData', $dataToAdd);
                        $cLoop++;
                    } else {
                        //else if yes no or multiple choice
                        foreach ($question->options as $option) {
                            $dataToAdd = $this->getQuestionPercentageForClusters($clusters, $pdo, $question, $option);
                            array_unshift($dataToAdd, $cLoop == 1 ? $category->name : "", $qLoop == 1 ? $question->name_en : "", $option->name);
                            $writer->writeSheetRow('ClusterPercentageData', $dataToAdd);
                            $qLoop++;
                            $cLoop++;
                        }
                    }
                    $qLoop = 0;
                }
            }
            $cLoop = 0;
        }

        //delete the final table
        $this->info('export:table final deleted');

        $writer->writeToFile(
            storage_path('exports') . "/percentage_cluster_" . $project->name . '.xlsx'
        );

        $this->info('end exportPercentageExportByCluster of project ' . $project->id);
    }

    /**
     * the following method is used to get the percentage export by Site
     *
     * @param [type] $project
     * @return void
     */
    public function exportPercentageExportBySite($project)
    {
        $this->info('start exportPercentageExportBySite of project ' . $project->id);

        //prepare
        $pdo = DB::getPdo();
        $pdo->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);

        //$siteIds = ProjectDetail::Where("project_id", $project->id)->pluck("site_id", "site_id")->toArray();
        $siteIds = \App\Models\FormInstance::Where("project_id", $project->id)->pluck("site_id", "site_id")->toArray();
        
        if(!empty($siteIds))
            $sites = SiteReference::whereIn("id", $siteIds)->orderBy("name")->pluck("name", "id")->all();
        else
            $sites = [];

        $sitesColumns = [];
        foreach ($sites as $site) {
            $sitesColumns[$site] = 'string';
        }
        $writer = new \XLSXWriter();
        $writer->writeSheetHeader(
            'SitePercentageData',
            [
                'Category' => 'string',
                'Question' => 'string',
                'Option' => 'string',
            ] + $sitesColumns
        );

        //write AGE data
        $ageMetrics = ['<5' => 'BETWEEN 0 AND 4', '5-14' => 'BETWEEN 5 AND 14', '15-44' => 'BETWEEN 15 AND 44', '45+' => '> 44'];

        //get Age question
        if(isset($project->form->id) && $project->form->id > 0){
            $ageQuestion = Question::where("form_id", $project->form->id)->whereNull("deleted_at")
                ->whereRaw('LOWER(name_en) = ?', 'age')
                ->first();
        }
        //loop on metric to add Age Data
        $loop = 0;
        foreach ($ageMetrics as $key => $if) {
            $loop++;
            $ageData = [];
            // if the age question has not been deleted and still exists
            if($ageQuestion){
                //loop on sites
                foreach ($sites as $id => $site) {
                    $statement = $pdo->prepare(
                        "SELECT Concat(Round(( Count(qa.value) / (SELECT Count(qa.value)
                                             FROM   question_answers qa
                                                    INNER JOIN form_instances fi ON qa.form_instance_id = fi.id
                                             WHERE  qa.question_id = {$ageQuestion->id}
                                                    AND fi.site_id = $id
                                             GROUP  BY fi.site_id) * 100 ), 2), '%') AS percentage
                                             FROM   question_answers qa
                                             INNER JOIN form_instances fi ON qa.form_instance_id = fi.id
                                             WHERE  qa.question_id = {$ageQuestion->id}
                                             AND qa.value $if
                                             AND fi.site_id = $id
                                             GROUP  BY fi.site_id"
                                             );
                    $statement->execute();
                    $result = $statement->fetch(\PDO::FETCH_ASSOC);
                    $ageData[] = !empty($result['percentage']) ? $result['percentage'] : ".";
                }
            }

            array_unshift($ageData, $loop == 1 ? "Age group" : "", $loop == 1 ? "Age" : "", $key);
            $writer->writeSheetRow('SitePercentageData', $ageData);
        }

        //loop on all questions
        $categories = [];
        if(isset($project->form->id) && $project->form->id > 0){
            $categories = FormCategory::where("form_id", $project->form->id)->whereNull("deleted_at")
                ->with(
                    [
                        'groups',
                        'groups.questions' => function ($query) {
                            $query->whereIn("response_type_id", [1, 2, 4])
                                ->whereRaw('LOWER(name_en) != ?', 'age');
                        },
                    ]
                )
                ->get();
        }
        //line separator
        $writer->writeSheetRow('SitePercentageData', []);

        $cLoop = $qLoop = 0;
        foreach ($categories as $category) {
            $cLoop++;
            foreach ($category->groups as $group) {
                foreach ($group->questions as $question) {
                    $qLoop++;
                    $questionName = $question->name_en;
                    //Numeric or Other
                    if ($question->options->count() == 0) {
                        $dataToAdd = $this->getQuestionCountForSites($sites, $pdo, $question);
                        array_unshift($dataToAdd, $cLoop == 1 ? $category->name : "", $qLoop == 1 ? $question->name_en : "", "");
                        $writer->writeSheetRow('SitePercentageData', $dataToAdd);
                        $cLoop++;
                    } else {
                        //else if yes no or multiple choice
                        foreach ($question->options as $option) {
                            $dataToAdd = $this->getQuestionCountForSites($sites, $pdo, $question, $option);
                            array_unshift($dataToAdd, $cLoop == 1 ? $category->name : "", $qLoop == 1 ? $question->name_en : "", $option->name);
                            $writer->writeSheetRow('SitePercentageData', $dataToAdd);
                            $qLoop++;
                            $cLoop++;
                        }
                    }
                    $qLoop = 0;
                }
            }
            $cLoop = 0;
        }

        //delete the final table
        $this->info('export:table final deleted');

        $writer->writeToFile(
            storage_path('exports') . "/percentage_" . $project->name . '.xlsx'
        );

        $this->info('end exportPercentageExportBySite of project ' . $project->id);
    }

    /**
     * @param $sites
     * @param $pdo
     * @param $question
     *
     * @return array
     */
    private function getQuestionCountForSites($sites, $pdo, $question)
    {
        $dataToAdd = [];
        foreach ($sites as $id => $site) {
            $statement = $pdo->prepare(
                "SELECT concat(Round(Avg(qa.value), 2),' ± ',Round(std(qa.value), 2)) AS avgstd
                FROM question_answers qa
                INNER JOIN form_instances fi ON qa.form_instance_id = fi.id
                WHERE  qa.question_id = {$question->id}
                AND fi.site_id = $id
                GROUP  BY fi.site_id "
            );
            $statement->execute();
            $result = $statement->fetch(\PDO::FETCH_ASSOC);
            $dataToAdd[] = ($result['avgstd'] != ""?$result['avgstd']:0);
        }

        if(empty($dataToAdd))
            $dataToAdd[] = ".";
        
        return $dataToAdd;
    }
    /**
     * @param $clusters
     * @param $pdo
     * @param $question
     *
     * @return array
     */
    private function getQuestionCountForClusters($clusters, $pdo, $question)
    {
        $dataToAdd = [];
        foreach ($clusters as $id => $cluster) {
            $statement = $pdo->prepare(
                "SELECT concat(Round(Avg(qa.value), 2),' ± ',Round(std(qa.value), 2)) AS avgstd
                FROM question_answers qa
                INNER JOIN form_instances fi ON qa.form_instance_id = fi.id
                WHERE  qa.question_id = {$question->id}
                AND fi.cluster_id = $id
                GROUP  BY fi.cluster_id "
            );
            $statement->execute();
            $result = $statement->fetch(\PDO::FETCH_ASSOC);
            $dataToAdd[] = ($result['avgstd'] != ""?$result['avgstd']:0);
        }

        return $dataToAdd;
    }

    /**
     * @param $sites
     * @param   v  b   g  $pdo
     * @param $question
     * @param $option
     *
     * @return array
     */
    private function getQuestionPercentageForSites($sites, $pdo, $question, $option)
    {
        $dataToAdd = [];

        foreach ($sites as $id => $site) {

            $query = "SELECT Concat(Round(( Count(qa.value) / (SELECT Count(qa.value)
                                         FROM   question_answers qa
                                                INNER JOIN form_instances fi
                                                        ON
                                                qa.form_instance_id = fi.id
                                         WHERE  qa.question_id = {$question->id}
                                                AND fi.site_id = $id
                                         GROUP  BY fi.site_id) * 100 ), 2), '%') AS percentage
                                         FROM   question_answers qa
                                         INNER JOIN form_instances fi ON qa.form_instance_id = fi.id
                                         WHERE  qa.question_id = {$question->id}
                                         AND qa.value = {$option->id}
                                         AND fi.site_id = $id
                                         GROUP  BY fi.site_id";

            $statement = $pdo->prepare($query);
            $statement->execute();
            $result = $statement->fetch(\PDO::FETCH_ASSOC);

            $dataToAdd[] = $result['percentage'];
        }

        return $dataToAdd;
    }

    /**
     * the following gets the percentage questions data for cluster
     *
     * @param [type] $clusters
     * @param [type] $pdo
     * @param [type] $question
     * @param [type] $option
     * @return void
     */
    private function getQuestionPercentageForClusters($clusters, $pdo, $question, $option)
    {
        $dataToAdd = [];

        foreach ($clusters as $id => $cluster) {

            $query = "SELECT Concat(Round(( Count(qa.value) / (SELECT Count(qa.value)
                                         FROM   question_answers qa
                                                INNER JOIN form_instances fi
                                                        ON
                                                qa.form_instance_id = fi.id
                                         WHERE  qa.question_id = {$question->id}
                                                AND fi.cluster_id = $id
                                         GROUP  BY fi.cluster_id) * 100 ), 2), '%') AS percentage
                                         FROM   question_answers qa
                                         INNER JOIN form_instances fi ON qa.form_instance_id = fi.id
                                         WHERE  qa.question_id = {$question->id}
                                         AND qa.value = {$option->id}
                                         AND fi.cluster_id = $id
                                         GROUP  BY fi.cluster_id";

            $statement = $pdo->prepare($query);
            $statement->execute();
            $result = $statement->fetch(\PDO::FETCH_ASSOC);

            $dataToAdd[] = ($result['percentage'] != ""?$result['percentage']:0);
        }

        return $dataToAdd;
    }

    public function format($number, $withPercentage = true)
    {
        return number_format(floor($number * 100) / 100, 2, '.', '') . ($withPercentage ? " %" : "");
    }
}
