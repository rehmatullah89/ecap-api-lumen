<?php

/*
 * This file is part of the IdeaToLife package.
 *
 * (c) Youssef Jradeh <youssef.jradeh@ideatolife.me>
 *
 */

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\WhoController;
use App\Models\Indicator;
use Idea\Helpers\Paging;
use App\Models\ResultFilter;

class IndicatorController extends WhoController {
	
	public $filePath = "indicators/";
	
	protected $permissions = [
		"index"   => ["code" => "indicators", "action" => "read"],
		"one"     => ["code" => "indicators", "action" => "read"],
		"results" => ["code" => "indicators", "action" => "read"],
		"store"   => ["code" => "indicators", "action" => "write"],
		"update"  => ["code" => "indicators", "action" => "write"],
		"destroy" => ["code" => "indicators", "action" => "write"],
	];
	
	/**
	 *
	 * @return array
	 */
	protected static function validationRules() {
		return [
			'index'  => [
				'project_id' => 'required|exists:projects,id',
			],
			'store'  => [
				"name"            => "required",
				'project_id'      => 'required|exists:projects,id',
				'upper_threshold' => 'required',
				'lower_threshold' => 'required',
				'arithmetic'      => 'required',
				'result_type'     => 'required',
			],
			'update' => [
				"name"            => "required",
				'upper_threshold' => 'required',
				'lower_threshold' => 'required',
				'arithmetic'      => 'required',
				'result_type'     => 'required',
			],
		];
	}
	
	/**
	 * Display a listing of the resource.
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function index() {
		return $this->successData(Indicator::where("project_id", request("project_id"))->get());
	}
	
	/**
	 * Display a listing of the resource.
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function results() {
		
            if(!request("project_id") && request("id")){
                
                $filter = ResultFilter::find(request("id"));
                $projectId = $filter->project_id;
                $dateStart = $filter->date_from;
                $dateEnd = $filter->date_to;
                $siteIds = ($filter->site_ids != "")?explode(",",@$filter->site_ids):[];
                $clusterIds = ($filter->cluster_ids != "")?explode(",",@$filter->cluster_ids):[];
                $questionIds = ($filter->question_ids != "")?explode(",",@$filter->question_ids):[];
                $collectorId = ($filter->collector_ids != "")?explode(",",@$filter->collector_ids):[];
                
            }else{
		$projectId = request("project_id");
                $dateStart = request("date_from");
                $dateEnd = request("date_to");
                $siteIds = request("site_ids");       
                $clusterIds = request("cluster_ids");       
                $questionIds = request("question_ids");
                $collectorId = request("collector_ids");
            }
		$indicators = Indicator::where("project_id", intval($projectId))->get();
		if (empty($indicators)) {
			return $this->successData();
		}
                
                if(empty($dateStart) && empty($dateEnd) && empty($siteIds) && empty($questionIds) && empty($collectorId)){
                    return $this->successData($indicators);
                }
                    
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
                                if($questionId > 0)            
                                    $responseType = \App\Models\Question::where("id", $questionId)->pluck("response_type_id")->first();
                    
				//select all the answer tof this question
                                $query = "select count(1) as count from `indicators_results` where project_id=".intval($projectId)." and question_id=".$questionId;
				
                                if(isset($siteIds) && !empty($siteIds)){
                                    $query .=" and site_id IN (".implode(",",$siteIds).") ";
				}
                                
                                if(isset($clusterIds) && !empty($clusterIds)){
                                    $query .=" and cluster_id IN (".implode(",",$clusterIds).") ";
				}
                                
                                if(isset($questionIds) && !empty($questionIds)){
                                    $query .=" and question_id IN (".implode(",",$questionIds).") ";
				}
                                
                                if(isset($collectorId) && !empty($collectorId)){
                                    $query .=" and user_id IN (".implode(",",$collectorId).") ";
				} 
                                
                                if(isset($dateStart) && !empty($dateStart) && !empty($dateEnd)){
                                        $query .=" and DATE_FORMAT(date_time ,'%Y-%m-%d') BETWEEN DATE('{$dateStart}') AND DATE('{$dateEnd}') ";
				}
                                
				//if for specific question
                                if(@in_array($responseType, [4,6,13,17]) && $indicator->lower_threshold != "" && $indicator->upper_threshold != "")
                                    $query .=" and value Between {$indicator->lower_threshold} AND {$indicator->upper_threshold} ";    
				else if (!empty($options[1]) && count($options) > 0) {
                                    $query .=" and value IN (".implode(",",$options).") ";
				}
				
                //current we are only allowing the count , we might need to enhance this later to allow sum or min or max.
                $questionAnswer = \DB::select($query);
				$count          = 0;
                if (!empty($questionAnswer[0]) && isset($questionAnswer[0]->count)) {
                    $count = $questionAnswer[0]->count;
                }
                $mathQuery = str_replace(
                    "[".$question."]",
                    $count,
                    $mathQuery);
			}
			try {
				$indicator->results = eval('return ' . $mathQuery . ';');
                                
                                if($indicator->result_type == 'percentage')
                                    $indicator->results *= 100;
                                
                                $indicator->results = round($indicator->results,4);
                                
			} catch (\Exception $e) {
				$indicator->results = "invalid results";
			}
		}
		
		return $this->successData($indicators);
	}
        
        
        /**
        * Previous Indicator Results function
        * */
        public function resultsPrevious() {
		
		$projectId = request("project_id");
		$indicators = Indicator::where("project_id", intval($projectId))->get();
		if (empty($indicators)) {
			return $this->successData();
		}
		$siteIds = request("site_ids");
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
				else if (!empty($options[1]) && count($options) > 0) {
                                    $query .=" and qa.value IN (".implode(",",$options).")";
				}
				
				if(!empty($siteIds)){                
                                    $query .=" and fi.site_id IN (".implode(",",$siteIds).")";
				}
                //current we are only allowing the count , we might need to enhance this later to allow sum or min or max.
                $questionAnswer = \DB::select($query);
				$count          = 0;
                if (!empty($questionAnswer[0]) && isset($questionAnswer[0]->count)) {
                    $count = $questionAnswer[0]->count;
                }
                $mathQuery = str_replace(
                    "[".$question."]",
                    $count,
                    $mathQuery);
			}
			try {
				$indicator->results = eval('return ' . $mathQuery . ';');
                                
                                if($indicator->result_type == 'percentage')
                                    $indicator->results *= 100;
                                
                                $indicator->results = round($indicator->results,4);
                                
			} catch (\Exception $e) {
				$indicator->results = "invalid results";
			}
		}
		
		return $this->successData($indicators);
	}
	
	/**
	 * Display the specified resource.
	 *
	 * @param  int $id
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function one($id) {
		$indicator = Indicator::find($id);
		if (!$indicator) {
			return $this->failed("Invalid indicator Id");
		}
		
		return $this->successData($indicator);
	}
	
	/**
	 * Store a newly created resource in storage.
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function store() {
		$indicator                          = new Indicator();
		$indicator->project_id              = request("project_id");		
		$indicator->name                    = request("name");
		$indicator->description             = request("description");
		$indicator->upper_threshold         = request("upper_threshold");
		$indicator->lower_threshold         = request("lower_threshold");
		$indicator->arithmetic              = str_replace("LN", "e", request("arithmetic"));
		$indicator->result_type             = request("result_type");
		$indicator->show_on_project_summary = request("show_on_project_summary");		
		$indicator->save();
		
		return $this->successData($indicator);
	}
	
	/**
	 * Update the specified resource in storage.
	 *
	 * @param  int $id
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function update($id) {
		
		$indicator = Indicator::find($id);
		if (!$indicator) {
			return $this->failed("Invalid Indicator");
		}
		
		$indicator->name                    = request("name");
		$indicator->description             = request("description");
		$indicator->upper_threshold         = request("upper_threshold");
		$indicator->lower_threshold         = request("lower_threshold");
                $indicator->result_type             = request("result_type");
		$indicator->show_on_project_summary = request("show_on_project_summary");
		
                if(request("arithmetic") != "")
                    $indicator->arithmetic          = str_replace("LN", "e", request("arithmetic"));
		
		$indicator->save();
		
		return $this->successData($indicator);
	}
	
	/**
	 * Remove the specified resource from storage.
	 *
	 * @param  int $id
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function destroy($id) {
		try {
			if (!$indicator = Indicator::find($id)) {
				return $this->failed("Invalid Indicator");
			}
			
			//then delete the row from the database
			$indicator->delete();
			
			return $this->success('Indicator deleted');
		} catch (\Exception $e) {
			return $this->failed('destroy error');
		}
	}
}