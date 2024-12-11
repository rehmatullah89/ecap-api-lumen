<?php
/**
 * Update results crone
 *
 * (c) RehmatUllah <rehmatullahbhatti@gmail.com>
 *
 */

namespace App\Console\Commands;

use App\Models\FormCategory;
use App\Models\Project;
use App\Models\Question;
use App\Models\QuestionResponseType;
use App\Models\Indicator;
use App\Models\IndicatorResults;
use App\Models\IndicatorSummary;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;


class UpdateResults extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'update:results';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update Indicators Results!';

    /**
    * Create a new command instance.
    *
    * @return void
    */
    public function __construct()
    {
        parent::__construct();
    }
    
    /**
     * Import indicators records in new table.
     *
     * @return void
     */
    public function importIndicatorSummary()
    {
        ini_set('memory_limit','-1');
        
        Log::info("Start Importing Indicators Summary!");

        IndicatorSummary::truncate();
        $projects = Project::pluck("id","id")->toArray();
        
        foreach ($projects as $index => $project)
        {
                    $data = [];            
                    //grouping form_instances by project and count the distinct governorate_id // distinct
                    $data['Governorate'] = DB::select(
                        "select COUNT((fi.governorate_id)) as count, MAX(fi.created_at) as max, MIN(fi.created_at) as min ".
                        "from form_instances fi ".
                        "where fi.project_id = $project AND fi.instance_type='collection' ".
                        "and fi.deleted_at IS NULL ".
                        "group by fi.project_id"
                    );
                    
                    //grouping form_instances by project and count the distinct district_id
                    $data['District'] = DB::select(
                        "select COUNT((fi.district_id)) as count, MAX(fi.created_at) as max, MIN(fi.created_at) as min ".
                        "from form_instances fi ".
                        "where fi.project_id = $project AND fi.instance_type='collection' ".
                        "and fi.deleted_at IS NULL ".
                        "group by fi.project_id"
                    );
                    
                    $data['Site'] = DB::select(
                        "select COUNT((fi.site_id)) as count, MAX(fi.created_at) as max, MIN(fi.created_at) as min ".
                        "from form_instances fi ".
                        "where fi.project_id = $project AND fi.instance_type='collection' ".
                        "and fi.deleted_at IS NULL ".
                        "group by fi.project_id"
                    );
                    
                    //grouping form_instances by project and count the distinct cluster_id
                    $data['Cluster'] = DB::select(
                        "select COUNT((fi.cluster_id)) as count, MAX(fi.created_at) as max, MIN(fi.created_at) as min ".
                        "from form_instances fi ".
                        "where fi.project_id = $project AND fi.instance_type='collection' ".
                        "and fi.deleted_at IS NULL ".
                        "group by fi.project_id"
                    );
            
                    $form = \App\Models\Form::where("project_id", $project)->pluck("id")->first();
                    $formTypes = \App\Models\FormType::where("form_id", $form)->where("parameter_type","collection")->pluck("name_en", "id")->toArray();
                    $loopableTypes = \App\Models\FormType::where("form_id", $form)->pluck("loop", "id")->toArray();
                    foreach($formTypes as $id => $typeName)
                    {
                        if(!in_array(strtolower($typeName), ['site/ sub district', 'cluster/ camp name/ phc name', 'governorate', 'district'])){
                            $questionGroups = \App\Models\QuestionGroup::where("form_type_id", $id)->pluck("id","id")->toArray();                            
                            $questions = \App\Models\Question::whereIn("question_group_id", $questionGroups)->pluck("id","id")->toArray();                            
                            $loop = (int)@$loopableTypes[$id];
                               
                            if($loop == 1 && !empty($questions)){
                                   $data[$typeName] = DB::select(
                                        "select (COUNT(distinct(qa.form_instance_id)) * IF(qa.individual_chunk > 0, qa.individual_chunk, 1 )) as count, MAX(qa.created_at) as max, MIN(qa.created_at) as min ".
                                        "from form_instances fi ".
                                        "inner join question_answers qa on fi.id=qa.form_instance_id  ".
                                        "where fi.project_id = $project AND fi.instance_type='collection' ".
                                        "and qa.deleted_at IS NULL ".
                                        "and qa.question_id IN (".  implode(",", $questions).")".
                                        "group by fi.project_id"
                                    );
                            }
                            else if(!empty($questions)){
                                    $data[$typeName] = DB::select(
                                           "select (COUNT(distinct(qa.form_instance_id))) as count, MAX(qa.created_at) as max, MIN(qa.created_at) as min ".
                                           "from form_instances fi ".
                                           "inner join question_answers qa on fi.id=qa.form_instance_id  ".
                                           "where fi.project_id = $project AND fi.instance_type='collection' ".
                                           "and qa.deleted_at IS NULL ".
                                           "and qa.question_id IN (".  implode(",", $questions).")".
                                           "group by fi.project_id"
                                    );                                
                            }
                            else{
                                $data[$typeName] = [];
                            }
                        }
                    }
                    
                    $counter = 0;
                    foreach($data as $key => $value)
                    {
                        if(in_array($project, array(1,100)))
                        {
                            $val = (int)@$value[0]->count;
                            if($key == 'Governorate')
                                $val = 4;
                            else if($key == 'District' || $key == 'Site')
                                $val = 14;
                            else if($key == 'Cluster')
                                $val = 55;
                            else if(strtolower($key) == 'household'){
                                if($project == 100)
                                    $val = 13276;
                                else
                                    $val = 13679;
                            }
                            else if(strtolower($key) == 'individual')
                            {
                                if($project == 100)
                                    $val = 52839;
                                else
                                    $val = 53556;
                            }
                            
                            $indicatorSummary = new IndicatorSummary();
                            $indicatorSummary->project_id = $project;        
                            $indicatorSummary->level = $counter ++;        
                            $indicatorSummary->name = $key;        
                            $indicatorSummary->latest = @$value[0]->max;        
                            $indicatorSummary->first = @$value[0]->min;        
                            $indicatorSummary->total = $val;        
                            $indicatorSummary->save();
                        }
                        else
                        {
                            $indicatorSummary = new IndicatorSummary();
                            $indicatorSummary->project_id = $project;        
                            $indicatorSummary->level = $counter ++;        
                            $indicatorSummary->name = $key;        
                            $indicatorSummary->latest = @$value[0]->max;        
                            $indicatorSummary->first = @$value[0]->min;        
                            $indicatorSummary->total = (int)@$value[0]->count;        
                            $indicatorSummary->save();
                        }
                    }
            
        }
    }
    
    /**
     * save qustion ranking values.
     *
     * @return void
     */
    public function setRankingValues()
    {
        $projects = Project::pluck("id","id")->toArray();
        $resultExecuted = @IndicatorResults::orderBy("date_time", "desc")->first()->date_time;
        
        foreach ($projects as $index => $project)
        {
            $questionNos = \App\Models\QuestionAnswer::where("project_id", $project)->where("response_type_id", 15)->where("created_at", ">", $resultExecuted)->pluck("question_id", "question_id")->toArray();
            $formInstances = \App\Models\QuestionAnswer::where("project_id", $project)->where("response_type_id", 15)->where("created_at", ">", $resultExecuted)->pluck("form_instance_id", "question_id")->toArray();
            
            foreach($questionNos as $questionId)
            {
                $questionInstances = $formInstances[$questionId];
                
                if(is_array($questionInstances))
                foreach($questionInstances as $instance){
                    $questionAnswers = \App\Models\QuestionAnswer::where("project_id", $project)->where("question_id", $questionId)->where("form_instance_id", $instance)->where("created_at", ">", $resultExecuted)->get();
                    $total = count($questionAnswers);
                    foreach($questionAnswers as $questionAns)
                    {
                        $questionAns->multiple = $total --;
                        $questionAns->save();
                    }
                }
            }
        }
    }
    
    /**
     * Import indicators records in new table.
     *
     * @return void
     */
    public function importIndicatorRecords()
    {
        ini_set('memory_limit','-1');
        
        Log::info("Start Importing Indicators records!");

        //IndicatorResults::truncate();
        $projects = Project::pluck("id","id")->toArray();
        $resultCount = IndicatorResults::count();
        
        foreach ($projects as $index => $project){
            
            if($resultCount == 0){
                $questionAnswer = \DB::select("SELECT qa.project_id, qa.form_instance_id, qa.question_id, qa.response_type_id, qa.multiple, qa.individual_chunk, qa.value, qa.created_at, "
                    . "fi.user_id, fi.site_id, fi.governorate_id, fi.district_id, fi.cluster_id, fi.individual_count, fi.stopped "
                    . "From question_answers qa, form_instances fi WHERE fi.id=qa.form_instance_id AND qa.project_id='$project' AND qa.value != '' AND qa.value IS NOT NULL");
            }else{
            $questionAnswer = \DB::select("SELECT qa.project_id, qa.form_instance_id, qa.question_id, qa.response_type_id, qa.multiple, qa.individual_chunk, qa.value, qa.created_at, "
                    . "fi.user_id, fi.site_id, fi.governorate_id, fi.district_id, fi.cluster_id, fi.individual_count, fi.stopped "
                    . "From question_answers qa, form_instances fi WHERE fi.id=qa.form_instance_id AND qa.project_id='$project' AND qa.created_at > DATE_SUB(CURDATE(), INTERVAL 1 DAY) AND qa.value != '' AND qa.value IS NOT NULL");
            }
            
            if(count($questionAnswer) > 0)
            foreach ($questionAnswer as $key => $value)
            {
                $indicatorResults = new IndicatorResults();
                $indicatorResults->project_id = $value->project_id;
                $indicatorResults->instance_id = $value->form_instance_id;
                $indicatorResults->question_id = $value->question_id;      
                $indicatorResults->response_type_id = $value->response_type_id;
                $indicatorResults->user_id = $value->user_id;
                $indicatorResults->site_id = $value->site_id;      
                $indicatorResults->governorate_id = $value->governorate_id; 
                $indicatorResults->district_id = $value->district_id; 
                $indicatorResults->cluster_id = $value->cluster_id;
                $indicatorResults->multiple = $value->multiple;
                $indicatorResults->individual_chunk = $value->individual_chunk;
                $indicatorResults->value = $value->value;                                
                $indicatorResults->individual_count = $value->individual_count;                                
                $indicatorResults->stopped = $value->stopped;                                       
                $indicatorResults->date_time = $value->created_at;                                                
                
                $indicatorResults->save();
            }
        }
    }
    
    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
            ini_set('memory_limit','-1');
            Log::info('------Starting Updating Indicator Results!------'); // for lumen.log

            $indicatorProjects = Indicator::distinct()->pluck('project_id','project_id')->toArray();
            
           foreach($indicatorProjects as $key => $projectId)
            {
                $indicators = Indicator::where("project_id", intval($projectId))->get();
		if (empty($indicators)) {
			return $this->successData();
		}
                
		$siteIds = \App\Models\FormInstance::where("project_id", $projectId)->pluck("site_id","site_id")->toArray();
		foreach ($indicators AS &$indicator) {
			$arithmetic = str_replace(" ", "", strtolower($indicator->arithmetic));
			$arithmetic = str_replace("x", "*", $arithmetic);
			
			//find all question id inside [] like [id_12312] where 12312 is the question id
			preg_match_all('#\[(.*?)\]#', $arithmetic, $match);
			if (empty($match[1])) {
				continue;
			}
			
			$mathQuery = $arithmetic;

			foreach ($match[1] AS $question) {
				$options = [];
				
				//if less then one mean no question
				if (substr_count($question, '_') < 1) {
					continue;
				}
				
				//if greater then 1 , mean it's a question id plus option id for this question
                                $questionId = str_replace("id_", "", $question);
				if (substr_count($question, '_') > 1) {
					$options = explode("_", str_replace("id_", "", $question));
                                        $questionId = $options[0];
					unset($options[0]);
				}
				
                                $responseType = 0;
                                $questionId = (int)$questionId;
                                if($questionId > 0)            
                                    $responseType = \App\Models\Question::where("id", $questionId)->pluck("response_type_id")->first();
                    
				//select all the answer tof this question
                $query = 'select count(qa.id) count from `question_answers` qa';
                
                if (!empty($siteIds)) {
                    $query .= ' inner join form_instances fi on fi.id=qa.form_instance_id';
                }
                
                $query .=" where qa.project_id=".intval($projectId)." and qa.question_id=".$questionId;
				
				//if for specific question
                                if(@in_array($responseType, [4,6,13,17]) && $indicator->lower_threshold != "" && $indicator->upper_threshold != "")
                                    $query .=" and qa.value Between {$indicator->lower_threshold} AND {$indicator->upper_threshold} ";    
				else if (!empty($options[1]) && count($options)) {
                                    $query .=" and qa.value IN (".implode(",",$options).")";
				}
				
				if(!empty($siteIds)){
                                    $query .=" and fi.site_id IN (".implode(",",$siteIds).")";
				}
                //current we are only allowing the count , we might need to enhance this later to allow sum or min or max.
                $questionAnswer = \DB::select($query);
                $count = 0;
                if (!empty($questionAnswer[0]) && isset($questionAnswer[0]->count)) {
                    $count = $questionAnswer[0]->count;
                }
                $mathQuery = str_replace(
                    "[".$question."]",
                    $count,
                    $mathQuery);
			}
			try {                        

                            	$indicator->results = @eval('return ' . $mathQuery . ';');
                                
                                if($indicator->result_type == 'percentage' && !empty($indicator->result_type))
                                    $indicator->results *= 100;
                                
                                $indicator->save();                            

			} catch(\Exception $e) {
                                $indicator->results = "invalid results";
			}
		
                }
        }
        //$this->setRankingValues();        
        $this->importIndicatorRecords();        
        $this->importIndicatorSummary();
    }
}
