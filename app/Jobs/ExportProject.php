<?php

namespace App\Jobs;

use App\Models\Project;
use App\Models\Question;
use App\Models\QuestionResponseType;
use Idea\Base\BaseJob;
use Illuminate\Support\Facades\DB;
use Log;


class ExportProject extends BaseJob {
	
	/**
	 * exporting the job.
	 *
	 * @return void
	 */
	public function handle() {
		$id = $this->params['id'];
		Log::info('start ExportProject of project ' . $id);
		
		//prepare
		$pdo = DB::getPdo();
		$pdo->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, TRUE);
		
		
		$project       = Project::with("form")->find($id);
		$questionTypes = QuestionResponseType::pluck("code", "id")->all();
		$questions     = Question::where("form_id", $project->form->id)
		                         ->orderBy("id")
		                         ->get();
		
		$columns = $columnsType = $clearColumns = [];
		
		foreach ($questions AS $question) {
			$clearColumns[$question->id] = $this->clean($question->name);
			$columns[$question->id]      = "q_" . $question->id;
			
			//set column type
			$columnsType[$question->id] = "TEXT";
			//however if it's a yes no or multiple then it should int
			if (in_array($questionTypes[$question->response_type_id], [
				'yes_no',
				'multiple_choice',
			])) {
				$columnsType[$question->id] = "bigint(20) unsigned NOT NULL";
			}
		}
		
		//create temporary table
		$tableName      = "tmp_{$id}_" . time() . "_" . rand();
		$tableNameFinal = $tableName . "_final";
		$sql            = "CREATE TEMPORARY TABLE `$tableName` (
`form_instance_id` bigint(20) unsigned NOT NULL,
`individual_chunk` int(10) NOT NULL DEFAULT '0',";
		foreach ($columns as $row) {
			$sql .= "`{$row}` TEXT,";
		}
		
		$sql = substr($sql, 0, -1) . ");";
		$pdo->exec($sql);
		$pdo->exec("create index index_$tableName on $tableName (form_instance_id, individual_chunk);");
		
		Log::info('export:TEMPORARY table created ' . $tableName);
		
		//Add final data table
		$pdo->exec(str_replace($tableName, $tableNameFinal, $sql));
		Log::info('export:TEMPORARY table created ' . $tableNameFinal);
		
		//custom query to group by question_id`,value
		$i = 0;
		foreach ($questions AS $question) {
			$fileName = $tableName . "_" . $question->id;
			Log::info('export:fetching for question ' . $question->id);
			//site name
			if ($i == 0) {
				$i++;
				$selectQuestion = "select f.id,0,s.name  from form_instances f left join sites s on f.site_id=s.id where f.project_id={$id}";
			}
			//site cluster
			elseif ($i == 1) {
				$i++;
				$selectQuestion = "select f.id,0,c.name  from form_instances f left join clusters c on f.cluster_id=c.id where f.project_id={$id}";
			}
			//long lat
			elseif ($i == 2) {
				$i++;
				$selectQuestion = "select f.id,0,concat(f.lat,',',f.lng) from form_instances f where f.project_id={$id}";
			}
			//other questions
			else {
				if (in_array($questionTypes[$question->response_type_id], [
					'yes_no',
					'multiple_choice',
				])) {
					$selectQuestion = "select qa.form_instance_id,qa.individual_chunk,qo.name from question_answers qa " .
					                  "left join question_options qo on qo.id=qa.value " .
					                  "where qa.question_id={$question->id} AND qa.project_id={$id}";
				}
				else {
					$selectQuestion = "select form_instance_id,individual_chunk,value from question_answers where question_id={$question->id} AND project_id={$id}";
				}
			}
			
			$insertQuery = "INSERT INTO $tableName (form_instance_id,individual_chunk,q_$question->id) $selectQuestion";
			$pdo->exec($insertQuery);
			
		}
		
		//move all data to the new temporary table grouping by form_instance_id,individual_chunk
		$columnsText    = "MAX(" . implode("),MAX(", $columns) . ")";
		$selectQuestion = "select form_instance_id,individual_chunk,$columnsText from $tableName group by form_instance_id,individual_chunk";
		
		//save to the new table
		$insertQuery = 'INSERT into ' . $tableNameFinal . ' (form_instance_id,individual_chunk,' . implode(",", $columns) . ') ' . $selectQuestion;
		$pdo->exec($insertQuery);
		Log::info('export:insert the grouping by');
		
		//and delete first table records
		Log::info('export:table one deleted');
		DB::unprepared(DB::raw("DROP TEMPORARY TABLE $tableName"));
		
		$columnsToSelect = "form_instance_id,individual_chunk,";
		foreach ($questions AS $question) {
			$columnsToSelect .= $columns[$question->id] . " as " . $clearColumns[$question->id] . ",";
		}
		$columnsToSelect = substr($columnsToSelect, 0, -1);
		
		Log::info('export:select all data to be exported');
		
		
		//delete temporary table
		\Excel::create($project->name, function ($excel) use ($project, $pdo, $columnsToSelect, $tableNameFinal) {
			// Set the title
			$excel->setTitle('Form data submission of the project ' . $project->name);
			
			// Chain the setters
			$excel->setCreator('IdeatoLife')
			      ->setCompany('IdeatoLife');
			
			$excel->sheet('Raw Data', function ($sheet) use ($pdo, $columnsToSelect, $tableNameFinal) {
				$loop = TRUE;
				$i    = 0;
				while ($loop == TRUE) {
					try {
						$statement = $pdo->prepare("select " . $columnsToSelect . " from $tableNameFinal LIMIT $i,200");
						$statement->setFetchMode(\PDO::FETCH_ASSOC);
						$statement->execute();
						$data = $statement->fetchAll();
						$sheet->fromArray($data);
						$i += 500;
						// Nothing to do, we've finished.
						if (!count($data)) {
							$loop = FALSE;
						}
						$sheet->data = [];
						$data = null;
					} catch (PDOException $e) {
						Log::info(" Reason given:" . $e->getMessage());
					}
				}
				
				//delete the final table
				$dropTable = DB::unprepared(DB::raw("DROP TEMPORARY TABLE $tableNameFinal"));
				Log::info('export:table final deleted');
				
			});
		})->store('xls', storage_path('exports'));
		
		Log::info('end ExportProject of project ' . $id);
	}
	
	private function clean($string) {
		$string = str_replace(' ', '_', strtolower($string)); // Replaces all spaces with hyphens.
		$string = preg_replace('/[^A-Za-z\_]/', '', $string); // Removes special chars.
		return substr($string, 0, 25);
	}
}