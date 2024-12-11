<?php

/*
 * This file is part of the IdeaToLife package.
 *
 * (c) Youssef Jradeh <youssef.jradeh@ideatolife.me>
 *
 */

namespace App\Http\Controllers\Result;

use App\Http\Controllers\WhoController;
use App\Models\GuestSite;
use App\Models\Project;
use App\Models\Question;
use App\Models\QuestionGroup;
use App\Models\QuestionOption;
use App\Models\QuestionResponseType;
use App\Models\SiteReference;
use App\Models\ClusterReference;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ResultController extends WhoController
{
    
    public $filePath = "sites/";
        
    
    public $selectedAnswers = [];
    
    protected $permissions = [
        "summary" => ["code" => "results", "action" => "read"],
        "results" => ["code" => "results", "action" => "read"],
        "export"  => ["code" => "results", "action" => "read"],
    ];
    
    /**
     *
     * @return array
     */
    protected static function validationRules()
    {
        return [
        ];
    }
    
    /**
     * summary of the results.
     *
     * @return \Illuminate\Http\Response
     */
    public function summary($id)
    {     
        return $this->successData(\App\Models\IndicatorSummary::where("project_id", $id)->orderBy("level")->get());
    }
    
    /**
     * previous summary of the results caluculations
     *
     * @return \Illuminate\Http\Response
     */
    public function summaryPrevious($id)
    {
        $this->setIsGuest();
        
        
        $results = [];
        //grouping form_instances by project and count the distinct site
        $results['data'] = DB::select(
            "select COUNT(distinct(fi.site_id)) as count, MAX(fi.created_at) as max, MIN(fi.created_at) as min ".
            "from form_instances fi ".
            "where fi.project_id = $id ".
            "and fi.deleted_at IS NULL ".$this->addGuestFilter().
            "group by fi.project_id"
        );
        
        
        //grouping form_instances by project and count the distinct cluster_id
        $clusterData = DB::select(
            "select COUNT(distinct(fi.cluster_id)) as count, MAX(fi.created_at) as max, MIN(fi.created_at) as min ".
            "from form_instances fi ".
            "where fi.project_id = $id ".
            "and fi.deleted_at IS NULL ".$this->addGuestFilter().
            "group by fi.project_id"
        );
        
        
        //grouping question_answers by project and count the distinct form_instance_id
        $householdData = DB::select(
            "select COUNT(distinct(qa.form_instance_id)) as count, MAX(qa.created_at) as max, MIN(qa.created_at) as min ".
            "from form_instances fi ".
            "inner join question_answers qa on fi.id=qa.form_instance_id  ".
            "where fi.project_id = $id ".
            "and qa.deleted_at IS NULL ".$this->addGuestFilter().
            "group by fi.project_id"
        );
        
        //custom query to group by form_instance_id`,individual_chunk
        $individualData = DB::select(
            'select SUM(count) as sum, max(max) as max, min(min) as min from (
select count(distinct(qa.form_instance_id)) as count, max(qa.created_at) as max, min(qa.created_at) as min
from form_instances fi
inner join question_answers qa on fi.id=qa.form_instance_id '.'
where fi.project_id = '.$id.' and qa.deleted_at is null '.$this->addGuestFilter().' and qa.individual_chunk is not null and qa.individual_chunk <> 0 group by qa.form_instance_id,qa.individual_chunk)  as test
'
        );
        
        if (!empty($siteData[0]->max)) {
            $results[] = [
                "level"  => "0",
                "name"   => "Site",
                "latest" => $siteData[0]->max,
                "first"  => $siteData[0]->min,
                "total"  => $siteData[0]->count,
            ];
        }
        if (!empty($clusterData[0]->max)) {
            $results[] = [
                "level"  => "1",
                "name"   => "Cluster",
                "latest" => $clusterData[0]->max,
                "first"  => $clusterData[0]->min,
                "total"  => $clusterData[0]->count,
            ];
        }
        if (!empty($householdData[0]->max)) {
            $results[] = [
                "level"  => "2",
                "name"   => "Household",
                "latest" => $householdData[0]->max,
                "first"  => $householdData[0]->min,
                "total"  => $householdData[0]->count,
            ];
        }
        
        if (!empty($individualData[0]->max)) {
            
            $results[] = [
                "level"  => "3",
                "name"   => "Individual",
                "latest" => $individualData[0]->max,
                "first"  => $individualData[0]->min,
                "total"  => intval($individualData[0]->sum),
            ];
        }
        
        return $this->successData($results);
    }

    /**
     * the following method gets the results for respective questions
     *
     * @param boolean $filterId
     * @return void
     */
    public function results($returnDataOnly = false, $site_ids = [],$question_ids =[], $cluster_id = null)
    {
        $this->setIsGuest();
        
        ini_set('memory_limit','-1');
        ini_set('max_execution_time', 0);
        
        $results = [];
        $projectId = 0;
        $questions = $questionWithRelation = [];

        $filterId = (int)request("id");
        $projectId = (int)@request("project_id");
        $page = (int)@request("page");
        
        if ($filterId == 0 && $projectId == 0)
            return $this->failed("Invalid request Id");
        else if($filterId == 0 && $projectId > 0){
            $filterId = \App\Models\ResultFilter::where("project_id", $projectId)->orderBy("id")->pluck("id")->first();
        }
        
        if ($filterId == 0)
            return $this->failed("Invalid request Id");
        
        $filter = \App\Models\ResultFilter::find($filterId);
        
        if($filter)
        {
            $projectId = $filter->project_id;
            $dateStart = $filter->date_from;
            $dateEnd = $filter->date_to;
            $site_ids = ($filter->site_ids != "")?explode(",",@$filter->site_ids):[];
            $clusterIds = ($filter->cluster_ids != "")?explode(",",@$filter->cluster_ids):[];
            $question_ids = ($filter->question_ids != "")?explode(",",@$filter->question_ids):[];
            $collectorIds = ($filter->collector_ids != "")?explode(",",@$filter->collector_ids):[];
            
            $project = Project::find($projectId);
            
            if(!empty($clusterIds))
                $cluster_id = $clusterIds[0];
            
            if(empty($question_ids))
            {
                //select all the answer tof this question
                $query = "select GROUP_CONCAT(DISTINCT question_id SEPARATOR ',') as _Questions "
                        . "from `indicators_results` where project_id=".intval($projectId)." ";

                if(isset($site_ids) && !empty($site_ids))
                    $query .=" and site_id IN (".implode(",",$site_ids).") ";

                if(isset($clusterIds) && !empty($clusterIds))
                    $query .=" and cluster_id IN (".implode(",",$clusterIds).") ";

                if(isset($collectorIds) && !empty($collectorIds))
                    $query .=" and user_id IN (".implode(",",$collectorIds).") ";

                if(isset($dateStart) && !empty($dateStart) && !empty($dateEnd))
                    $query .=" and DATE_FORMAT(date_time ,'%Y-%m-%d') BETWEEN DATE('{$dateStart}') AND DATE('{$dateEnd}') ";

                $questionQry = \DB::select($query);
                $question_ids = explode(",", @$questionQry[0]->_Questions);   
            }
            
            $questionChunk = collect($question_ids);
            $questionChunk = $questionChunk->chunk(48);
            $question_ids = @$questionChunk[$page];
            
            //if(!empty($question_ids) && empty($site_ids))
            //    $site_ids = \App\Models\IndicatorResults::whereIn("question_id", $question_ids)->where("project_id", $projectId)->pluck("site_id","site_id")->toArray();
            $projectId = (int)@$projectId;
            $formId = \App\Models\Form::where("project_id", $projectId)->first()->id;            
            $formId = (int)@$formId;
            $gorvernorateQuestion = Question::where("form_id", $formId)->where("name_en", "LIKE", "Name of Governorate?")->first();
            $districtQuestion = Question::where("form_id", $formId)->where("name_en", "LIKE", "Name of District?")->first();
            $siteQuestion = Question::where("form_id", $formId)->where("name_en", "LIKE", "Name of the Site?")->first();
            $clusterQuestion = Question::where("form_id", $formId)->where("name_en", "LIKE", "Name of Cluster?")->first();
            $clusterQuestion2 = Question::where("form_id", $formId)->where("name_en", "LIKE", "GPS of Cluster?")->first();
            $skipFourQuestions = array((int)@$gorvernorateQuestion->id, (int)@$districtQuestion->id, (int)@$siteQuestion->id, (int)@$clusterQuestion->id, (int)@$clusterQuestion2->id);
            
            $questionTypes = QuestionResponseType::pluck("code", "id")->all();
            // setting the questionIds
            $questionIds = count($question_ids) > 0 ? $question_ids : request("question_ids");
            
            if(count($question_ids) > 0){
                foreach($question_ids as $questionId){
                    if(!in_array($questionId, $skipFourQuestions))
                        $questions[$questionId] = Question::find($questionId);
                }
            }

            $siteIds = count($site_ids) > 0 ? $site_ids : request("site_ids");
            $clusterId = $cluster_id ? $cluster_id : request('cluster_id');
            $sites   = [];
            $isSiteGrouped = true;


            if (!empty($siteIds)) {
                // since we have got site_ids from input 
                // then we need to explicitly show those sites in the repotr separately
                //TODO: tempfix
                $isSiteGrouped = false;
                foreach ($siteIds AS $siteId) {
                    $site = \App\Models\SiteReference::where('id', $siteId)->first();

                    if(isset($site) && @$site->id > 0)
                        $sites[$siteId] = $site->name;
                }
            }            
            else {
                $sites[0] = "All Sites";
            }

            //loop on questions without relation
            if (!empty($questions)) {
                foreach ($questions AS $id => $question) {
                    if($question){
                        if(!in_array($id, array($skipFourQuestions))){                        
                            $result = [
                                "id"    => $id,
                                "name"  => $question->question_code." - ".$question->name_en,
                                "label" => $question->label,
                                'type'  => $questionTypes[$question->response_type_id],
                            ];

                            if (in_array(
                                $questionTypes[$question->response_type_id],
                                [
                                    'yes_no',
                                    'multiple_choice',
                                    'rating',
                                    /*'currency'*/                                    
                                ]
                            )
                            ) {
                                foreach ($sites AS $siteId => $siteName) {
                                    $name = $clusterId ? ClusterReference::find($clusterId)->name : $siteName;
                                    $result['data'][$name] = $this->getNewStatistic(
                                        $id,
                                        $siteId,
                                        $clusterId,
                                            0,
                                            0
                                    );
                                }
                            }else if (in_array(
                                $questionTypes[$question->response_type_id],
                                [
                                    'ranking'
                                ]
                            )
                            ) {
                                foreach ($sites AS $siteId => $siteName) {
                                    $name = $clusterId ? ClusterReference::find($clusterId)->name : $siteName;
                                    $result['data'][$name] = $this->getRankingStatistic(
                                        $id,
                                        $siteId,
                                        $clusterId,
                                            0,
                                            0
                                    );
                                }
                            }
                            elseif (in_array(
                                $questionTypes[$question->response_type_id],
                                ['number', 'slider', 'range']
                            )
                            ) {
                                foreach ($sites AS $siteId => $siteName) {
                                    $name = $clusterId ? ClusterReference::find($clusterId)->name : $siteName;
                                    $result['data'][$name] = $this->getNewStatisticNumber(
                                        $id,
                                        $siteId,
                                        $clusterId,
                                            0,
                                            0
                                    );
                                }
                            } else {
                                //continue;
                                foreach ($sites AS $siteId => $siteName) {
                                    $name = $clusterId ? ClusterReference::find($clusterId)->name : $siteName;
                                    $result['data'][$name] = $this->getOtherStatistics(
                                        $id,
                                        $siteId,
                                        $clusterId,
                                            0,
                                            0
                                    );
                                }
                            }

                            if(!isset($result['data']))
                                $result['data']["All Sites"] = [];

                            if(!empty($result))
                                $results['questions'][] = $result;
                        }
                    }
                }
            }

            if (request("export_data")) {
                return $this->exportResults($project->name, $results, $isSiteGrouped);
            }
            // incase only results need to be returned
            if($returnDataOnly) {
                return $results;
            }
    }
        return $this->successData($results);
    }
    
    
    /**
     * the following method gets the results for respective questions, based on site or form_category_id
     *
     * @param boolean $returnDataOnly
     * @param array $site_ids
     * @param array $question_ids
     * @return void
     */
    public function resultsComparison($returnDataOnly = false, $site_ids = [],$question_ids =[], $cluster_id = null)
    {
        $this->setIsGuest();
        
        $questions = $questionWithRelation = [];

        
        $questionTypes = QuestionResponseType::pluck("code", "id")->all();
        // setting the questionIds
        $questionIds = count($question_ids) > 0 ? $question_ids : request("question_ids");
        //or if by question
        $i = 1;
        if (!empty($questionIds)) {
            
            foreach ($questionIds AS $questionId) {
                
                if (is_array($questionId)) {
                    // if question_2 empty
                    if (empty($questionId['question_2']) && !empty($questionId['question_1'])) {
                        $questions[$questionId['question_1']] = Question::find(
                            $questionId['question_1']
                        );
                        //selected answer for specific questions
                        if (!empty($questionId['question_1_value'])) {
                            $this->selectedAnswers[$questionId['question_1
                            ']] = $questionId['question_1_value'];
                        }
                        continue;
                    } else {
                        //otherwise, it's a multi filter
                        $questionWithRelation[$i]['question_1']       = Question::find(
                            $questionId['question_1']
                        );
                        $questionWithRelation[$i]['question_1_value'] = $questionId['question_1_value'];
                        $questionWithRelation[$i]['question_2']       = Question::find(
                            $questionId['question_2']
                        );
                        $questionWithRelation[$i]['question_2_value'] = $questionId['question_2_value'];
                        $questionWithRelation[$i]['relation']         = $questionId['relation'];
                        $i++;
                    }
                    
                } else {
                    $query = Question::where('id', $questionId);
                    if(request("cluster_id")){
                        // validating if this question was contained in this cluster or not 
                        // i.e. this cluster had a form submission with this question
                        $questionsData = $query->whereHas('answers', function ($q1){
                            $q1->whereHas('formInstance', function($q2){
                                $q2->where('cluster_id', request("cluster_id"));
                            });
                        })->first();
                        // if this question was submitted for the respective cluster, else null
                        $questionsData ?  $questions[$questionId] = $questionsData : null; 
                    }else{
                        $questions[$questionId] = $query->first();
                    }
                }
            }
        }
        
        //or else if by category
        else if (!empty(request("form_category_id"))) {
            $groups = QuestionGroup::with("questions")
                                   ->where(
                                       "form_category_id",
                                       request("form_category_id")
                                   )
                                   ->get();
            
            if (!empty($groups)) {
                foreach ($groups AS $group) {
                    if (!empty($group->questions)) {
                        foreach ($group->questions AS $question) {
                            $questions[$question->id] = $question;
                        }
                    }
                }
            }
        }
        
        $results = [];
        $siteIds = count($site_ids) > 0 ? $site_ids : request("site_ids");
        $clusterId = $cluster_id ? $cluster_id : request('cluster_id');
        $sites   = [];
        $isSiteGrouped = true;

        
        if (!empty($siteIds)) {
            // since we have got site_ids from input 
            // then we need to explicitly show those sites in the repotr separately
            //TODO: tempfix
            $isSiteGrouped = false;
            foreach ($siteIds AS $siteId) {
                $site = \App\Models\SiteReference::where('id', $siteId)->first();
                
                if(isset($site) && @$site->id > 0)
                    $sites[$siteId] = $site->name;
            }
        }
        // else if the user is guest then only get the sites to which the user belongs
        else if($this->isGuest) {
            $userId = $this->user->id;
            // guest sites only
            $guestSites = \App\Models\SiteReference::whereHas(
                'guestSites', function ($query) use ($userId) {
                    $query->where('user_id', $userId);
                }
            );
            $guestSites = $guestSites->get();
            foreach ($guestSites AS $guestSite) {
                $sites[$guestSite->id] = $guestSite->name;
            }
        }
        else {
            $sites[0] = "All Sites";
        }
        
        //loop on questions without relation
        if (!empty($questions)) {
            foreach ($questions AS $id => $question) {
                if($question){
                    $result = [
                        "id"    => $id,
                        "name"  => $question->name_en,
                        "label" => $question->label,
                        'type'  => $questionTypes[$question->response_type_id],
                    ];

                    if (in_array(
                        $questionTypes[$question->response_type_id],
                        [
                            'yes_no',
                            'multiple_choice',
                        ]
                    )
                    ) {
                        foreach ($sites AS $siteId => $siteName) {
                            $name = $clusterId ? @ClusterReference::find($clusterId)->name  : $siteName;
                            $result['data'][$name] = $this->getStatistic(
                                $id,
                                $siteId,
                                $clusterId
                            );
                        }
                    } elseif (in_array(
                        $questionTypes[$question->response_type_id],
                        ['number']
                    )
                    ) {
                        foreach ($sites AS $siteId => $siteName) {
                            $name = $clusterId ? @ClusterReference::find($clusterId)->name  : $siteName;
                            $result['data'][$name] = $this->getStatisticNumber(
                                $id,
                                $siteId,
                                $clusterId
                            );
                        }
                    } else {
                        continue;
                    }
                    
                    if(!isset($result['data']))
                        $result['data']["All Sites"] = [];
                    
                    $results['questions'][] = $result;
                }
            }
        }
        
        //loop on questions with relation
        if (!empty($questionWithRelation)) {
            foreach ($questionWithRelation AS $array) {
                $result = [
                    "name"  => '['.$array['question_1']->name."] ".$array['relation']." [".$array['question_2']->name.']',
                    "label" => '['.$array['question_1']->label."] ".$array['relation']." [".$array['question_2']->label.']',
                    'type'  => "comparison",
                ];
                
                foreach ($sites AS $siteId => $siteName) {
                    $name = $clusterId ? ClusterReference::find($clusterId)->name : $siteName;
                    $result['data'][$name] = $this->getStatistic(
                        $array,
                        $siteId
                    );
                }
                
                $results['questions_with_relation'][] = $result;
            }
        }
        
        if (request("export_data")) {
            return $this->exportResults("Project ", $results, $isSiteGrouped);
        }
        // incase only results need to be returned
        if($returnDataOnly) {
            return $results;
        }
        return $this->successData($results);
    }
    
    private function getStatistic($questionId, $siteId = 0, $clusterId = 0)
    {
        
        //get result as number first (number here are the foreign key of the options values)
        if (is_array($questionId)) {
            $results = $this->getStatisticNumberWithRelation(
                $questionId,
                $siteId,
                $clusterId
            );
        } else {
            $results = $this->getStatisticNumber($questionId, $siteId, $clusterId);
            //get all option of questions
            $questionOptions = QuestionOption::where("question_id", $questionId)
                                             ->pluck("name_en", "id")
                                             ->all();
            
            //loop to add the name of the option
            foreach ($results AS &$result) {
                $result->name = isset($questionOptions[$result->value]) ? $questionOptions[$result->value] : $result->value;
            }
        }
        
        return $results;
    }
    
    private function getOtherStatistics($questionId, $siteId = 0, $clusterId = 0, $districtId = 0, $governorateId = 0)
    {
        $questionId = (int)$questionId;
        $query = "select Count(value) as myValue, count(1) as count from indicators_results where question_id = {$questionId} ";
        
        if ($clusterId > 0) 
            $query .= " and cluster_id = $clusterId ";
        else if ($siteId > 0)
            $query .= " and site_id=".$siteId;
        else if ($districtId > 0)
            $query .= " and district_id=".$districtId;
        else if ($governorateId > 0)
            $query .= " and governorate_id=".$governorateId;
        
        if (request("date_from")) {
            $dateFrom = \Carbon\Carbon::createFromFormat(
                'Y-m-d',
                request("date_from")
            )
                                      ->toDateTimeString();
            $query    .= " AND created_at >= '$dateFrom' ";
        }
        
        if (request("date_to")) {
            $dateTo = \Carbon\Carbon::createFromFormat(
                'Y-m-d',
                request("date_to")
            )
                                    ->toDateTimeString();
            $query  .= " AND created_at <= '$dateTo' ";
        }
        
        //if specific answers
        if (!empty($this->selectedAnswers[$questionId])) {
            $query .= " AND value IN (".implode(
                ",",
                $this->selectedAnswers[$questionId]
            ).") ";
        }
        
        $query .= ' group by question_id'; //myValue  
        
        $results = DB::select($query);
        
        $newList = [];
        //loop to add the name of the option
        foreach ($results AS $result) {
            $newList[] = array('value' => @$result->myValue, 'count'=> @$result->count, 'name'=>'Count');//((isset($result->myValue) && @$result->myValue) == 0?'Not Answered':'Answered')
        }
        
        return $newList;
    }
    
    private function getNewStatistic($questionId, $siteId, $clusterId, $districtId, $governorateId)
    {
            $results = $this->getNewStatisticNumber($questionId, $siteId, $clusterId, $districtId, $governorateId);
            //get all option of questions
            $questionOptions = QuestionOption::where("question_id", $questionId)
                                             ->pluck("name_en", "id")
                                             ->all();
            
            //loop to add the name of the option
            foreach ($results AS &$result) {
                if(isset($questionOptions[$result->value]))
                    $result->name = $questionOptions[$result->value];
                else
                    $result->name = $result->value;
            }
        
        
        return $results;
    }
    
    public function getRankingStatistic($questionId, $siteId=0, $clusterId=0, $districtId=0, $governorateId=0)
    {
            //custom query to group by question_id`,value
            $query = "select value from indicators_results where question_id = {$questionId} AND value LIKE '[{%' ";

            if ($clusterId > 0) 
                $query .= " and cluster_id = $clusterId ";
            else if ($siteId > 0)
                $query .= " and site_id=".$siteId;
            else if ($districtId > 0)
                $query .= " and district_id=".$districtId;
            else if ($governorateId > 0)
                $query .= " and governorate_id=".$governorateId;

            if (request("date_from")) {
                $dateFrom = \Carbon\Carbon::createFromFormat(
                    'Y-m-d',
                    request("date_from")
                )
                                          ->toDateTimeString();
                $query    .= " AND date_time >= '$dateFrom' ";
            }
            if (request("date_to")) {
                $dateTo = \Carbon\Carbon::createFromFormat(
                    'Y-m-d',
                    request("date_to")
                )
                                        ->toDateTimeString();
                $query  .= " AND date_time <= '$dateTo' ";
            }
           
            $results = DB::select($query);
            
            //if empty return nothing
            if (empty($results)) 
                $results = [];
            else{
                $listCounts = [];
                foreach($results as &$result){
                    $values = json_decode($result->value);
                    foreach($values as $value){
                        if($value->rank == 1){
                            $listCounts[$value->id] = 1 + (int)@$listCounts[$value->id];
                        }
                    }
                }
                $newList = [];
                //get all option of questions
                $questionOptions = QuestionOption::where("question_id", $questionId)
                                             ->pluck("name_en", "id")
                                             ->all();
                
                //assign option names and prepare data
                foreach($listCounts as $value => $count){
                    $name = @$questionOptions[$value];
                    array_push($newList, ['value'=>(string)$value, 'count'=>$count, 'name'=>(string)($name!=""?$name:$value)]);
                }
                
                $results = json_decode(json_encode($newList),1);
            }
       
        return $results;
    }
    
    
    private function getStatisticNumberWithRelation($array, $siteId = 0, $clusterId = 0)
    {
        //custom query to group by question_id`,value
        $query = 'select count(ABS(count_form)) as count_form from (select count(fi.id) as count_form '.
                 'from `question_answers` qa'.
                 ' inner join form_instances fi on fi.id=qa.form_instance_id';
        
        if ($clusterId) {
            $query .= " and fi.cluster_id=".$clusterId;
        }else if ($siteId) {
            $query .= " and fi.site_id=".$siteId;
        }
        
        $query .= ' where ((qa.question_id='.$array['question_1']->id.' AND qa.value='.$array['question_1_value'].') '
                  .'or'
                  .' (qa.question_id='.$array['question_2']->id.' AND qa.value='.$array['question_2_value'].'))'
                  .' and qa.deleted_at is null ';
        
        //add guest filter
        $this->addGuestFilter($query);
        
        $query = $this->addDateFilter($query);
        
        $query = $this->addCollectorFilter($query);
        
        $query   .= ' group by fi.id';
        $query   .= ' having count_form > '.(strtolower(
            $array['relation']
        ) == "and" ? '1' : '0');
        $query   .= ') as e';
        $results = DB::select($query);
        
        //if empty return nothing
        if (empty($results)) {
            return [];
        }
        
        return $results;
    }
    
    private function getNewStatisticNumber(
        $questionId,
        $siteId,
        $clusterId,
        $districtId,
        $governorateId    
    ) {
        //custom query to group by question_id`,value
        $query = "select max(value) as value, count(ABS(value)) as count from indicators_results where question_id = {$questionId} ";
        
        if ($clusterId > 0) 
            $query .= " and cluster_id = $clusterId ";
        else if ($siteId > 0)
            $query .= " and site_id=".$siteId;
        else if ($districtId > 0)
            $query .= " and district_id=".$districtId;
        else if ($governorateId > 0)
            $query .= " and governorate_id=".$governorateId;
        
        if (request("date_from")) {
            $dateFrom = \Carbon\Carbon::createFromFormat(
                'Y-m-d',
                request("date_from")
            )
                                      ->toDateTimeString();
            $query    .= " AND date_time >= '$dateFrom' ";
        }
        if (request("date_to")) {
            $dateTo = \Carbon\Carbon::createFromFormat(
                'Y-m-d',
                request("date_to")
            )
                                    ->toDateTimeString();
            $query  .= " AND date_time <= '$dateTo' ";
        }
       // $query = $this->addCollectorFilter($query);
        
        //if specific answers
        if (!empty($this->selectedAnswers[$questionId])) {
            $query .= " AND value IN (".implode(
                ",",
                $this->selectedAnswers[$questionId]
            ).") ";
        }
        
        $query .= ' group by question_id, value';
        
        $results = DB::select($query);
        
        //if empty return nothing
        if (empty($results)) {
            return [];
        }
        
        return $results;
    }
    
    private function getStatisticNumber(
        $questionId,
        $siteId = 0,
        $clusterId
    ) {
        
        //custom query to group by question_id`,value
        $query = 'select max(ABS(qa.value)) as value,count(ABS(qa.value)) as count '.
                 'from `question_answers` qa '.
                 'inner join form_instances fi on fi.id=qa.form_instance_id';
        

        if ($clusterId) {
            $query .= " and fi.cluster_id = $clusterId ";
        }else if ($siteId) {
            $query .= " and fi.site_id=".$siteId;
        }
        
        
        $query .= ' where qa.question_id = '.$questionId.' and qa.deleted_at is null ';
        
        //add guest filter
        $this->addGuestFilter($query);
        
        if (request("date_from")) {
            $dateFrom = \Carbon\Carbon::createFromFormat(
                'Y-m-d',
                request("date_from")
            )
                                      ->toDateTimeString();
            $query    .= "AND qa.created_at >= '$dateFrom' ";
        }
        if (request("date_to")) {
            $dateTo = \Carbon\Carbon::createFromFormat(
                'Y-m-d',
                request("date_to")
            )
                                    ->toDateTimeString();
            $query  .= "AND qa.created_at <= '$dateTo' ";
        }
        $query = $this->addCollectorFilter($query);
        
        //if specific answers
        if (!empty($this->selectedAnswers[$questionId])) {
            $query .= " AND qa.value IN (".implode(
                ",",
                $this->selectedAnswers[$questionId]
            ).") ";
        }
        
        $query .= ' group by qa.question_id,qa.value';
        
        $results = DB::select($query);
        
        //if empty return nothing
        if (empty($results)) {
            return [];
        }
        
        return $results;
    }
    
    private function clean($string)
    {
        $string = str_replace(
            ' ',
            '_',
            strtolower($string)
        ); // Replaces all spaces with hyphens.
        $string = preg_replace(
            '/[^A-Za-z\_]/',
            '',
            $string
        ); // Removes special chars.
        
        return substr($string, 0, 25);
    }
    
    public function export($id)
    {
        $project = Project::with("form")->find($id);
        
        //PDF file is stored under project/public/download/info.pdf
        $file = storage_path()."/exports/".$project->name.".xlsx";
        
        $headers = [
            'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ];
        
        return response()->download($file, $project->name."~Raw Data~".date("Ymd ~ H:i").".xlsx", $headers);
    }
    
    public function exportPercentage($id,$type)
    {
        $project = Project::with("form")->find($id);
        if($type === "cluster"){
            //PDF file is stored under project/public/download/info.pdf
            $file = storage_path()."/exports/percentage_".$type."_".$project->name.".xlsx";   
        }else{
            //PDF file is stored under project/public/download/info.pdf
            $file = storage_path()."/exports/percentage_".$project->name.".xlsx";
        }

        
        $headers = [
            'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ];
        
        return response()->download($file, $project->name."~".strtoupper($type)."~".date("Ymd ~ H:i").".xlsx", $headers);
    }
    
    /**
     * @param $query
     *
     * @return string
     */
    private function addDateFilter($query)
    {
        if (request("date_from")) {
            $dateFrom = \Carbon\Carbon::createFromFormat(
                'd/m/y',
                request("date_from")
            )
                                      ->toDateTimeString();
            $query    .= "AND qa.created_at >= '$dateFrom' ";
        }
        if (request("date_to")) {
            $dateTo = \Carbon\Carbon::createFromFormat(
                'd/m/y',
                request("date_to")
            )
                                    ->toDateTimeString();
            $query  .= "AND qa.created_at <= '$dateTo' ";
        }
        
        return $query;
    }
    
     /**
     * the following method is used to get the export for the question comparison of different projects
     *
     * @return void
     */
    public function questionComparisonResults()
    {
        $firstResults =  $this->resultsComparison2(true, request('first_site_ids'), request('first_question_ids'), request('first_cluster_ids'), request('first_district_ids'), request('first_governorate_ids'), request('first_date_from'), request('first_date_to'));        
        $secondResults =  $this->resultsComparison2(true, request('second_site_ids'), request('second_question_ids'), request('second_cluster_ids'), request('second_district_ids'), request('second_governorate_ids'), request('second_date_from'), request('second_date_to'));
        return $this->exportProjectComparison($firstResults,$secondResults,request('first_project_name'),request('second_project_name'),false);
    }
    /**
     * the following method is used to export project's question comparison
     *
     * @param [type] $results
     * @param [type] $isSiteGrouped
     * @return void
     */
    private function exportProjectComparison($firstResults, $secondResults, $firstProjectName, $secondProjectName, $isSiteGrouped){

             //delete temporary table
             \Excel::create(
                "results",
                function ($excel) use ($firstResults, $secondResults, $isSiteGrouped, $firstProjectName,$secondProjectName) {
                    // Set the title
                    $excel->setTitle('Exporting results');
                    
                    // Chain the setters
                    $excel->setCreator('IdeatoLife')->setCompany('IdeatoLife');
                    
                    if (!empty($firstResults['questions'])) {
                        $firstQuestionData = (!isset($firstResults['questions'])?[]:$firstResults['questions']);
                        $secondQuestionData = (!isset($secondResults['questions'])?[]:$secondResults['questions']);
                        //echo "<pre>";print_r($secondQuestionData);exit;
                        $excel->sheet(
                            'Questions',
                            function ($sheet) use ($firstQuestionData, $secondQuestionData, $isSiteGrouped, $firstProjectName,$secondProjectName) {
                                // putting Project Names on top of the excel sheet
                                $sheet->rows([[$firstProjectName,'']]);
                                // first question data
                                foreach ($firstQuestionData AS $row) {
                                    $sheet->rows([[" ", $row['name']]]);
                                    if (in_array($row['type'], array('number','slider', 'range'))) {
                                        if (empty($row['data']) || empty($row['data']['All Data'])) {
                                            continue;
                                        }
                                        //adding header
                                        $sheet->rows([['','Mean', 'Min Value', 'Max Value']]);
                                        foreach ($row['data'] AS $site => $dataSites) {
                                            // used to keep track of values across all sites
                                            // so we can show cummulative results in excel sheet
                                            $total_value_array = [];
                                            $current_site_total = [];
                                            foreach ($dataSites AS $row) {
                                                // keeping track of all values
                                                if($isSiteGrouped) {
                                                    array_push($total_value_array, $row->value);
                                                }else {
                                                    array_push($current_site_total, $row->value);
                                                }
                                            }
                                             // if the sites are not grouped then calculate the mean for the current site only
                                             // and insert it into respective sheet
                                            if(!$isSiteGrouped) {
                                                $mean = empty($current_site_total) ? 0 : array_sum($current_site_total) / count($current_site_total);
                                                $sheet->rows([[$site, $mean, empty($current_site_total) ? 0 : min($current_site_total),empty($current_site_total) ? 0 : max($current_site_total)]]);
                                            }
                                        }
                                        // once all the sites have nee iterated , insert it in the excel sheet at the end
                                        if($isSiteGrouped) {
                                            // to avoid division by or for from 0
                                            $mean = array_sum($total_value_array) === 0 || count($total_value_array) === 0 ? 0 : array_sum($total_value_array) / count($total_value_array);
                                            $sheet->rows([['All Sites', $mean, empty($total_value_array) ? 0: min($total_value_array),empty($total_value_array) ? 0 : max($total_value_array)]]);
                                        }
                                    }else{
                                        if (empty($row['data']) || empty($row['data']['All Data'])) {
                                            continue;
                                        }
                                        $dataToAdd = [];
                                        foreach ($row['data'] AS $site => $dataSites) {
                                            $total = 0;
                                            foreach ($dataSites AS $dataSite) {
                                                if($dataSite)
                                                    $total = $total + (int)@$dataSite->count;
                                            }
                                            foreach ($dataSites AS $dataSite) {
                                                if (empty($dataSite->name)) {
                                                    continue;
                                                }
                                                //add each results
                                                if($dataSite)
                                                    $dataToAdd[$dataSite->name][] = $dataSite->count." (".$this->truncate_number(($dataSite->count / $total) * 100)."%)";
                                            }
                                            
                                            //finally add the total
                                            if(isset($dataSite->name) && !empty($dataToAdd[$dataSite->name])) {
                                                $dataToAdd["total"][] = $total." (100%)";
                                            }
                                        }
                                        
                                        //Add options
                                        $sheet->rows([array_merge(["Options"], array_keys($row['data']))]);
                                        
                                        //Adding results
                                        foreach ($dataToAdd AS $key => $row) {
                                            $sheet->rows([array_merge([$key], $row)]);
                                        }
                                        
                                    }
                                    $sheet->rows([['         '], ['         ']]); 
                                }
                                $sheet->rows([[$secondProjectName,'']]);
                                // second question data
                                foreach ($secondQuestionData AS $row) {
                                    $sheet->rows([[" ", $row['name']]]);
                                   if (in_array($row['type'], array('number','slider','range'))){
                                        if (empty($row['data']) || empty($row['data']['All Data'])) {
                                            continue;
                                        }
                                        //adding header
                                        $sheet->rows([['','Mean', 'Min Value', 'Max Value']]);
                                        foreach ($row['data'] AS $site => $dataSites) {
                                            // used to keep track of values across all sites
                                            // so we can show cummulative results in excel sheet
                                            $total_value_array = [];
                                            $current_site_total = [];
                                            foreach ($dataSites AS $row) {
                                                // keeping track of all values
                                                if($isSiteGrouped) {
                                                    array_push($total_value_array, $row->value);
                                                }else {
                                                    array_push($current_site_total, $row->value);
                                                }
                                            }
                                             // if the sites are not grouped then calculate the mean for the current site only
                                             // and insert it into respective sheet
                                            if(!$isSiteGrouped) {
                                                $mean = empty($current_site_total) ? 0 : array_sum($current_site_total) / count($current_site_total);
                                                $sheet->rows([[$site, $mean, empty($current_site_total) ? 0 : min($current_site_total),empty($current_site_total) ? 0 : max($current_site_total)]]);
                                            }
                                        }
                                        // once all the sites have nee iterated , insert it in the excel sheet at the end
                                        if($isSiteGrouped) {
                                            // to avoid division by or for from 0
                                            $mean = array_sum($total_value_array) === 0 || count($total_value_array) === 0 ? 0 : array_sum($total_value_array) / count($total_value_array);
                                            $sheet->rows([['All Sites', $mean, empty($total_value_array) ? 0: min($total_value_array),empty($total_value_array) ? 0 : max($total_value_array)]]);
                                        }
                                    }else {
                                        if (empty($row['data']) || empty($row['data']['All Data'])) {
                                            continue;
                                        }
                                        $dataToAdd = [];
                                        foreach ($row['data'] AS $site => $dataSites) {
                                            $total = 0;
                                            foreach ($dataSites AS $dataSite) {
                                                if($dataSite)
                                                    $total = $total + (int)@$dataSite->count;
                                            }
                                            foreach ($dataSites AS $dataSite) {
                                                if (empty(@$dataSite->name)) {
                                                    continue;
                                                }
                                                
                                                if($dataSite)
                                                    $dataToAdd[$dataSite->name][] = $dataSite->count." (".$this->truncate_number(($dataSite->count / $total) * 100)."%)";
                                            }
                                            
                                            //finally add the total
                                            if(isset($dataSite->name) && !empty(@$dataToAdd[$dataSite->name])) {
                                                $dataToAdd["total"][] = $total." (100%)";
                                            }
                                        }
                                        
                                        //Add options
                                        $sheet->rows([array_merge(["Options"], array_keys($row['data']))]);
                                        
                                        //Adding results
                                        foreach ($dataToAdd AS $key => $row) {
                                            $sheet->rows([array_merge([$key], $row)]);
                                        }
                                        
                                    }
                                    $sheet->rows([['         '], ['         ']]); 
                                }
                            }
                        );
                    }
                    /*if (!empty($firstResults['questions_with_relation']) || !empty($secondResults['questions_with_relation'])) {
                        $firstData = $firstResults['questions_with_relation'];
                        $secondData = $secondResults['questions_with_relation'];
                        $excel->sheet(
                            'Question With Relation',
                            function ($sheet) use ($data) {
                                foreach ($firstData AS $row) {
                                    $sheet->row(
                                        1,
                                        function ($row) {
                                            $row->setBackground('#CCCCCC');
                                        }
                                    );
                                    $sheet->rows(
                                        [
                                            [$row['name']],
                                        ]
                                    );
                                    if (!empty($row['data'])) {
                                        foreach ($row['data'] AS $datum) {
                                            if (!empty($datum[0]->count_form)) {
                                                $sheet->rows(
                                                    [
                                                        [
                                                            'count',
                                                            $datum[0]->count_form,
                                                        ],
                                                    ]
                                                );
                                            }
                                        }
                                    }
                                    $sheet->rows([]);
                                }
                                foreach ($secondData AS $row) {
                                    $sheet->row(
                                        1,
                                        function ($row) {
                                            $row->setBackground('#CCCCCC');
                                        }
                                    );
                                    $sheet->rows(
                                        [
                                            [$row['name']],
                                        ]
                                    );
                                    if (!empty($row['data'])) {
                                        foreach ($row['data'] AS $datum) {
                                            if (!empty($datum[0]->count_form)) {
                                                $sheet->rows(
                                                    [
                                                        [
                                                            'count',
                                                            $datum[0]->count_form,
                                                        ],
                                                    ]
                                                );
                                            }
                                        }
                                    }
                                    $sheet->rows([]);
                                }
                            }
                        );
                    }*/
                    
                    if (empty($firstResults['questions']) && empty(@$firstResults['questions_with_relation'])) {
                        $data = [];
                        $excel->sheet(
                            'Question With Relation',
                            function ($sheet) use ($data) {
                                $sheet->rows(
                                    [
                                        ["no Results available"],
                                    ]
                                );
                            }
                        );
                    }
                    
                }
            )->store('xls', "/tmp");
            
            //PDF file is stored under project/public/download/info.pdf
            $file = "/tmp/results.xls";
            
            $headers = [
                'Content-Type: application/vnd.ms-excel',
            ];
            
            return response()->download($file, "results_export_comparison.xls", $headers);
    }
    /**
     * @param $query
     *
     * @return string
     */
    private function exportResults($projectName, $results, $isSiteGrouped)
    {                
        //delete temporary table
        \Excel::create(
            "results",
            function ($excel) use ($results, $isSiteGrouped) {
                // Set the title
                $excel->setTitle('Exporting results');
                
                // Chain the setters
                $excel->setCreator('IdeatoLife')->setCompany('IdeatoLife');
                
                if (!empty($results['questions'])) {
                    $data = $results['questions'];
                    $excel->sheet(
                        'Questions',
                        function ($sheet) use ($data, $isSiteGrouped) {
                            foreach ($data AS $row) {
                                //adding header
                                $sheet->row(
                                    1,
                                    function ($row) {
                                        $row->setBackground('#cccccc');
                                    }
                                );
                                $sheet->rows([[" ", $row['name']]]);
                                
                                //if yes no or multiple choice
                                if (in_array($row['type'], ['yes_no', 'multiple_choice'])) {
                                    if (empty($row['data'])) {
                                        continue;
                                    }
                                    $dataToAdd = [];
                                    
                                    foreach ($row['data'] AS $site => $dataSites) {
                                        if(isset($row['data'][$site]) && count($row['data'][$site]) >0)
                                        {
                                            $total = 0;
                                            foreach ($dataSites AS $dataSite) {
                                                $total = $total + (int)$dataSite->count;
                                            }
                                            foreach ($dataSites AS $dataSite) {
                                                if (empty($dataSite->name)) {
                                                    continue;
                                                }
                                                //add each results
                                                $dataToAdd[$dataSite->name][] = $dataSite->count." (".$this->truncate_number(($dataSite->count / $total) * 100)."%)";
                                            }

                                            //finally add the total
                                            if(isset($dataSite->name) && !empty($dataToAdd[$dataSite->name])) {
                                                $dataToAdd["total"][] = $total." (100%)";
                                            }
                                        }
                                    }
                                    
                                    //Add options
                                    $sheet->rows([array_merge(["Options"], array_keys($row['data']))]);
                                    
                                    //Adding results
                                    foreach ($dataToAdd AS $key => $row) {
                                        $sheet->rows([array_merge([$key], $row)]);
                                    }
                                    
                                } //else if number
                                elseif ($row['type'] == 'number') {
                                    if (empty($row['data'])) {
                                        continue;
                                    }
                                    //adding header
                                    $sheet->rows([['','Mean', 'Min Value', 'Max Value']]);
                                    foreach ($row['data'] AS $site => $dataSites) {
                                        // used to keep track of values across all sites
                                        // so we can show cummulative results in excel sheet
                                        $total_value_array = [];
                                        $current_site_total = [];
                                        foreach ($dataSites AS $row) {
                                            // keeping track of all values
                                            if($isSiteGrouped) {
                                                array_push($total_value_array, $row->value);
                                            }else {
                                                array_push($current_site_total, $row->value);
                                            }
                                        }
                                         // if the sites are not grouped then calculate the mean for the current site only
                                         // and insert it into respective sheet
                                        if(!$isSiteGrouped) {
                                            $mean = empty($current_site_total) ? 0 : array_sum($current_site_total) / count($current_site_total);
                                            $sheet->rows([[$site, $mean, empty($current_site_total) ? 0 : min($current_site_total),empty($current_site_total) ? 0 : max($current_site_total)]]);
                                        }
                                    }
                                    // once all the sites have nee iterated , insert it in the excel sheet at the end
                                    if($isSiteGrouped) {
                                        // to avoid division by or for from 0
                                        $mean = array_sum($total_value_array) === 0 || count($total_value_array) === 0 ? 0 : array_sum($total_value_array) / count($total_value_array);
                                        $sheet->rows([['All Sites', $mean, empty($total_value_array) ? 0: min($total_value_array),empty($total_value_array) ? 0 : max($total_value_array)]]);
                                    }
                                }
                                
                                $sheet->rows([['         '], ['         ']]);
                                
                            }
                        }
                    );
                }
                if (!empty($results['questions_with_relation'])) {
                    $data = $results['questions_with_relation'];
                    $excel->sheet(
                        'Question With Relation',
                        function ($sheet) use ($data) {
                            foreach ($data AS $row) {
                                $sheet->row(
                                    1,
                                    function ($row) {
                                        $row->setBackground('#CCCCCC');
                                    }
                                );
                                $sheet->rows(
                                    [
                                        [$row['name']],
                                    ]
                                );
                                if (!empty($row['data'])) {
                                    foreach ($row['data'] AS $datum) {
                                        if (!empty($datum[0]->count_form)) {
                                            $sheet->rows(
                                                [
                                                    [
                                                        'count',
                                                        $datum[0]->count_form,
                                                    ],
                                                ]
                                            );
                                        }
                                    }
                                }
                                $sheet->rows([]);
                            }
                        }
                    );
                }
                
                if (empty($results['questions']) && empty($results['questions_with_relation'])) {
                    $data = [];
                    $excel->sheet(
                        'Question With Relation',
                        function ($sheet) use ($data) {
                            $sheet->rows(
                                [
                                    ["no Results available"],
                                ]
                            );
                        }
                    );
                }
                
            }
        )->store('xls', "/tmp");
        
        //PDF file is stored under project/public/download/info.pdf
        $file = "/tmp/results.xls";
        
        $headers = [
            'Content-Type: application/vnd.ms-excel',
        ];
        //results_export
        return response()->download($file, $projectName."~Specific Question Export"."~"."~".date("Ymd ~ H:i").".xls", $headers);
    }
    
    /**
     * @param $query
     *
     * @return string
     */
    private function addCollectorFilter($query)
    {
        if (request("collector_id")) {
            if (is_array(request("collector_id"))) {
                $query .= " AND fi.user_id IN ("
                          .implode(",", request("collector_id")).")";
            } else {
                
                $query .= " AND fi.user_id=".request("collector_id");
            }
        }
        
        return $query;
    }
    
    
    /**
     * @param $query
     *
     * @return string
     */
    private function setIsGuest()
    {
        $user = User::whereId($this->user->id)->first();
        if ($user->hasAnyOfRoles(["guest","super_guest"])) {
            $this->isGuest = true;
        }
    }
    
    function truncate_number($number, $precision = 2)
    {
        return number_format(floor($number * 100) / 100, $precision, '.', '');
    }
    
    function arrayGroup(array $data, $byColumn)
    {
        $result = [];
        
        foreach ($data as $item) {
            $column = $item[$byColumn];
            unset($item[$byColumn]);
            if (isset($result[$column])) {
                $result[$column][] = $item;
            } else {
                $result[$column] = [$item];
            }
        }
        
        return $result;
    }
    
    /**
     * the following method gets the results for respective questions, based on site or form_category_id
     *
     * @param boolean $returnDataOnly
     * @param array $site_ids
     * @param array $question_ids
     * @return void ($returnDataOnly = false, $site_ids = [],$question_ids =[], $cluster_id = null)
     */

    public function resultsComparison2($returnDataOnly = false, $site_ids=[], $question_ids=[], $cluster_ids=[], $district_ids=[], $governorate_ids=[], $date_from="", $date_to="")
    {                
        $combined = request("combined");
        $questionIds = (!empty($question_ids) && $question_ids != null)?$question_ids:request("question_ids");
        $governorates = (!empty($governorate_ids) && $governorate_ids != null)?$governorate_ids:request("governorates");
        $districts = (!empty($district_ids) && $district_ids != null)?$district_ids:request("districts");
        $sites = (!empty($site_ids) && $site_ids != null)?$site_ids:request("sites");
        $clusters = (!empty($cluster_ids) && $cluster_ids != null)?$cluster_ids:request("clusters");
        $dateStart = (!empty($date_from) && $date_from != null)?$date_from:request("date_from");
        $dateEnd = (!empty($date_to) && $date_to != null)?$date_to:request("date_to");        
        $questionIds = Question::whereIn("id", $questionIds)->distinct()->pluck('id')->toArray();

        $firstQuestion = (int)@$questionIds[0];

        if($firstQuestion>0)
        {
            $questObj = Question::find($firstQuestion);
            
            $formId = $questObj->form_id;
            
            $gorvernorateQuestion = Question::where("form_id", $formId)->where("name_en", "LIKE", "Name of Governorate?")->first();
            $districtQuestion = Question::where("form_id", $formId)->where("name_en", "LIKE", "Name of District?")->first();
            $siteQuestion = Question::where("form_id", $formId)->where("name_en", "LIKE", "Name of the Site?")->first();
            $clusterQuestion = Question::where("form_id", $formId)->where("name_en", "LIKE", "Name of Cluster?")->first();
            $clusterQuestion2 = Question::where("form_id", $formId)->where("name_en", "LIKE", "GPS of Cluster?")->first();
            $skipFourQuestions = array((int)@$gorvernorateQuestion->id, (int)@$districtQuestion->id, (int)@$siteQuestion->id, (int)@$clusterQuestion->id, (int)@$clusterQuestion2->id);
            
            $tempQuestions = [];
            if(count($questionIds) > 0){
                foreach($questionIds as $questionId){
                    if(!in_array($questionId, $skipFourQuestions))
                        $tempQuestions[$questionId] = $questionId;
                }
            }
            $questionIds = $tempQuestions;            
        }
        
        $selectedValue = "";
        $clusterList = [];
        $siteList = [];
        $districtList = [];
        $governorateList = [];
        $listValues[0] = "All Data";
        $isSiteGrouped = $combined;
        
        if(!empty($clusters)){
            if($combined && count($clusters) > 1)
                $clusterList[0] = "All Clusters";
            else
                $clusterList = ClusterReference::whereIn("id", $clusters)->pluck("name","id")->toArray();            
        }
        
        if(!empty($sites)){
            if($combined && count($sites) > 1)
                $siteList[0] = "All Sites";
            else
                $siteList = SiteReference::whereIn("id", $sites)->pluck("name","id")->toArray();
        }
        
        if(!empty($districts)){
            if($combined && count($districts) > 1)
                $districtList[0] = "All Districts";
            else
                $districtList = \App\Models\District::whereIn("id", $districts)->pluck("name","id")->toArray();
        }
        
        if(!empty($governorates)){
            if($combined && count($governorates) > 1)
                $governorateList[0] = "All Governorates";
            else
                $governorateList = \App\Models\Governorate::whereIn("id", $governorates)->pluck("name","id")->toArray();
        }
        
        //or if by question    
        $questions = [];
        $siteQuestions = [];
        $clusterQuestions = [];
        $districtQuestions = [];
        $governorateQuestions = [];
        
        if (!empty($questionIds)) {
            foreach ($questionIds AS $questionId) {
                $query = Question::where('id', $questionId);
                    
                    if(!empty($clusters) && $clusters != null){
                        $questionsData = $query->whereHas('answers', function ($q1) use ($clusters){
                            $q1->whereHas('formInstance', function($q2) use ($clusters){
                                $q2->whereIn('cluster_id', $clusters);
                            });
                        })->first();
                        // if this question was submitted for the respective cluster, else null
                        $questionsData ?  $clusterQuestions[$questionId] = $questionsData : null; 
                    }
                    
                    if(!empty($sites) && $sites != null){
                        $questionsData = $query->whereHas('answers', function ($q1) use ($sites){
                            $q1->whereHas('formInstance', function($q2) use ($sites){
                                $q2->whereIn('site_id', $sites);
                            });
                        })->first();
                        // if this question was submitted for the respective cluster, else null
                        $questionsData ?  $siteQuestions[$questionId] = $questionsData : null; 
                    }
                    
                    if(!empty($districts) && $districts != null){
                        $questionsData = $query->whereHas('answers', function ($q1) use ($districts){
                            $q1->whereHas('formInstance', function($q2) use ($districts){
                                $q2->whereIn('district_id', $districts);
                            });
                        })->first();
                        // if this question was submitted for the respective cluster, else null
                        $questionsData ?  $districtQuestions[$questionId] = $questionsData : null; 
                    }
                    
                    if(!empty($governorates) && $governorates != null){
                        $questionsData = $query->whereHas('answers', function ($q1) use ($governorates){
                            $q1->whereHas('formInstance', function($q2) use ($governorates){
                                $q2->whereIn('governorate_id', $governorates);
                            });
                        })->first();
                        // if this question was submitted for the respective cluster, else null
                        $questionsData ?  $governorateQuestions[$questionId] = $questionsData : null; 
                    }
                    
                    if((empty($governorates) || $governorates == null) && (empty($districts) || $districts == null) && (empty($sites) || $sites == null) && (empty($clusters) || $clusters == null))
                    {
                        $questions[$questionId] = $query->first();
                    }
            }
        }
        
        $results = [];        
        
        if(!empty($governorateQuestions))
            $results = $this->getResultsforFilters($results, $governorateQuestions, $governorateList, "Governorate", $combined);
        
        if(!empty($districtQuestions))
            $results = $this->getResultsforFilters($results, $districtQuestions, $districtList, "District", $combined);
        
        if(!empty($siteQuestions))
            $results = $this->getResultsforFilters($results, $siteQuestions, $siteList, "Site", $combined);
    
        if(!empty($clusterQuestions))
            $results = $this->getResultsforFilters($results, $clusterQuestions, $clusterList, "Cluster", $combined);
        
        if(!empty($questions))
            $results = $this->getResultsforFilters($results, $questions, $listValues, "", $combined);
        
        if (request("export_data")) {
            return $this->exportResults("Project ", $results, $isSiteGrouped);
        }
        // incase only results need to be returned
        if($returnDataOnly) {
            return $results;
        }
        return $this->successData($results);
    }
    
    private function getResultsforFilters($results, $questions, $listValues, $selectedValue, $combined)
    {
        $questionTypes = QuestionResponseType::pluck("code", "id")->all();
        //loop on questions without relation
        if (!empty($questions)) {
            foreach ($questions AS $id => $question) {
                if($question){
                    
                    $falg = false;
                    
                    $result = [
                        "id"    => $id,
                        "name"  => $question->name_en,
                        "label" => $question->label,
                        'type'  => $questionTypes[$question->response_type_id],
                    ];

                    if (in_array(
                        $questionTypes[$question->response_type_id],
                        [
                            'yes_no',
                            'multiple_choice',
                            'rating',
                        ]
                    )
                    ) {
                        foreach ($listValues AS $itemId => $itemName) {
                            $siteId = ($selectedValue == "Site" && $combined == 0)?$itemId:0;
                            $clusterId = ($selectedValue == "Cluster" && $combined == 0)?$itemId:0;
                            $districtId = ($selectedValue == "District" && $combined == 0)?$itemId:0;
                            $governorateId = ($selectedValue == "Governorate" && $combined == 0)?$itemId:0;
                            $title = $selectedValue != ""?(" ({$selectedValue})"):"";
                            
                            $dataReturened = $this->getNewStatistic(
                                $id,
                                $siteId,
                                $clusterId,
                                $districtId,
                                $governorateId    
                            );
                            
                            if(!empty($dataReturened)){
                                $result['data'][$itemName.$title] = $dataReturened;
                                $falg = true;
                            }
                        }
                    }
                    else if (in_array(
                        $questionTypes[$question->response_type_id],
                        [
                            'ranking'
                        ]
                    )
                    ) {
                        foreach ($listValues AS $itemId => $itemName) {
                            $siteId = ($selectedValue == "Site" && $combined == 0)?$itemId:0;
                            $clusterId = ($selectedValue == "Cluster" && $combined == 0)?$itemId:0;
                            $districtId = ($selectedValue == "District" && $combined == 0)?$itemId:0;
                            $governorateId = ($selectedValue == "Governorate" && $combined == 0)?$itemId:0;
                            $title = $selectedValue != ""?(" ({$selectedValue})"):"";
                            
                            $dataReturened = $this->getRankingStatistic(
                                $id,
                                $siteId,
                                $clusterId,
                                $districtId,
                                $governorateId    
                            );
                            
                            if(!empty($dataReturened)){
                                $result['data'][$itemName.$title] = $dataReturened;
                                $falg = true;
                            }
                        }
                    }elseif (in_array(
                        $questionTypes[$question->response_type_id],
                        ['number', 'slider', 'range']
                    )
                    ) {
                        foreach ($listValues AS $itemId => $itemName) {
                            $siteId = ($selectedValue == "Site" && $combined == 0)?$itemId:0;
                            $clusterId = ($selectedValue == "Cluster" && $combined == 0)?$itemId:0;
                            $districtId = ($selectedValue == "District" && $combined == 0)?$itemId:0;
                            $governorateId = ($selectedValue == "Governorate" && $combined == 0)?$itemId:0;
                            $title = $selectedValue != ""?(" ({$selectedValue})"):"";
                            
                             $dataReturened = $this->getNewStatisticNumber(
                                $id,
                                $siteId,
                                $clusterId,
                                $districtId,
                                $governorateId    
                            );
                            
                            if(!empty($dataReturened)){
                               $result['data'][$itemName.$title] = $dataReturened;
                               $falg = true;
                            }
                        }
                    } else {
                        //continue; with remaning ones;
                        foreach ($listValues AS $itemId => $itemName) {
                            $siteId = ($selectedValue == "Site" && $combined == 0)?$itemId:0;
                            $clusterId = ($selectedValue == "Cluster" && $combined == 0)?$itemId:0;
                            $districtId = ($selectedValue == "District" && $combined == 0)?$itemId:0;
                            $governorateId = ($selectedValue == "Governorate" && $combined == 0)?$itemId:0;
                            $title = $selectedValue != ""?(" ({$selectedValue})"):"";
                            
                            $dataReturened = $this->getOtherStatistics(
                                $id,
                                $siteId,
                                $clusterId,
                                $districtId,
                                $governorateId    
                            );
                            
                            if(!empty($dataReturened)){
                               $result['data'][$itemName.$title] = $dataReturened;
                               $falg = true;
                            }
                        }
                    }
                            
                    //if(!isset($result['data']))
                    //    $result['data']["All Data"] = [];
                    
                    if(!empty($result) && $falg == true)
                        $results['questions'][] = $result;
                }
            }
        }
        
        return $results;
    }
    
}