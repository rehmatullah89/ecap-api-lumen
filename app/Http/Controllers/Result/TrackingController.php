<?php

/*
 * This file is part of the IdeaToLife package.
 *
 * (c) Youssef Jradeh <youssef.jradeh@ideatolife.me>
 *
 */

namespace App\Http\Controllers\Result;

use App\Http\Controllers\WhoController;
use App\Models\Project;
use Illuminate\Support\Facades\DB;

class TrackingController extends WhoController
{
    
    public $filePath = "sites/";
    
    protected $permissions = [
        "summary"   => ["code" => "trackings", "action" => "read"],
        "locations" => ["code" => "trackings", "action" => "read"],
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
        $project = Project::find($id);
        $governorateId = request("governorate_id");
        $districtId = request("district_id");
        $siteId = request("site_id");
        $clusterId = request("cluster_id");
        $userId = request("user_id");
        
        $results            = [];
        $results['boxes']   = [];
        $results['table']   = [];
        $results['diagram'] = [];
        
        $indicatorResults = \App\Models\IndicatorSummary::where("project_id", $id)->OrderBy("level")->get();
        
        foreach($indicatorResults as $result){            
            $results['boxes'][] = [
               "name"  => $result->name,
               "value" => (int)$result->total
            ];  
        }
        
        //table is always by dates
        $date0 = \Carbon\Carbon::createFromFormat(
            'd/m/y',
            request("date_from") ?: date('d/m/y', $project->date_end->timestamp)
        );
        $date1 = $date0->copy();
        $date2 = $date0->copy();
        $date3 = $date0->copy();
        $date4 = $date0->copy();
        $date5 = $date0->copy();
        $date6 = $date0->copy();
        $date7 = $date0->copy();
        $date1->subDays(6);
        $date2->subDays(5);
        $date3->subDays(4);
        $date4->subDays(3);
        $date5->subDays(2);
        $date6->subDays(1);
        
        $dataList = array('Type', $date1->toDateString(), $date2->toDateString(), $date3->toDateString(), $date4->toDateString(), $date5->toDateString(), $date6->toDateString(), $date0->toDateString(), 'Total');
        $dataObjList = array('Type', $date1, $date2, $date3, $date4, $date5, $date6, $date0, 'Total');
        $results['table'][] = $dataList;
        
        $formId = $project->form->id;
        $formTypes = \App\Models\FormType::where("form_id", $formId)->where("parameter_type","collection")->pluck("name_en", "id")->toArray();
        
        foreach($formTypes as $formTypeId => $formType)
        {
            $diagramList = [];
            $diagramList["name"] = $formType;
            if(!in_array(strtolower($formType), array('site/ sub district', 'cluster/ camp name/ phc name', 'governorate', 'district'))){
                $questionGroups = \App\Models\QuestionGroup::where("form_type_id", $formTypeId)->pluck("id", "id")->toArray();
                $questions = \App\Models\Question::whereIn("question_group_id", $questionGroups)->pluck("id", "id")->toArray();
                
                $count = 0;
                $tempList = [];
                $tempList[] = $formType;
                foreach($dataObjList as $key => $date){
                    if($key != 0 && $key != 8){
                        $nextDay = $date->copy();
                        if(count($questions)>0){

                            $subQuery = "";
                            if($governorateId > 0)
                                $subQuery = " AND governorate_id = $governorateId ";
                            else if($districtId)
                                $subQuery = " AND district_id = $districtId ";
                            else if($siteId)
                                $subQuery = " AND site_id = $siteId ";
                            else if($clusterId)
                                $subQuery = " AND cluster_id = $clusterId ";
                            
                            if($userId >0)
                                $subQuery .= " AND user_id = $userId ";
                            
                            $data = DB::select(
                                    " select COUNT(Distinct instance_id) as count ".
                                    " from indicators_results ".
                                    " where project_id = $id ".
                                    " and question_id IN (".implode(",",$questions).") and date_time >= '".$date->startOfDay()."' AND date_time < '".$nextDay->endOfDay()."'".$subQuery.
                                    " group by instance_id"
                                );
                            $countData = (int)@$data[0]->count;                        
                            $tempList[] = $countData;
                            $diagramList["series"][] = array("name"=>$date->toDateString(), "value"=>$countData);
                            $count += $countData;
                        }
                        else{
                            $tempList[] = 0;
                            $diagramList["series"][] = array("name"=>$date->toDateString(), "value"=>0);
                        }
                    }
                }
                $tempList[] = $count;
                $results['diagram'][] = $diagramList;
                $results['table'][] = $tempList;
            }
        }
        
        return $this->successData($results);
    }
    
    private function getDiagramItem($id, $date, $isByTime = 0,$totalValueCount = 0,$totalIndividualCount = 0 )
    {
        //DB::raw('SUM(individual_count) as individual_count'),
        //DB::raw('COUNT(id) as count')
       
        $query = DB::table('form_instances')
                   ->select(
                       DB::raw('COUNT(site_id) as individual_count'),
                       DB::raw('COUNT(cluster_id) as count')
                   )
                   ->where('project_id', $id)
                   ->whereNull("deleted_at")
                   ->groupBy('project_id');
        
        if($isByTime) {
            $isByTimeDater = $date->copy();
            $query->where("created_at", ">", $isByTimeDater->startOfDay())->where("created_at", "<", $date);
        }else{
            $nextDay = $date->copy();
            $query->where("created_at", ">", $date->startOfDay())->where("created_at", "<", $nextDay->endOfDay());
        }
        if (request("site_id")) {
            $query->where('site_id', request("site_id"));
        }
        
        if (request("cluster_id")) {
            $query->where('cluster_id', request("cluster_id"));
        }
        
        if (request("user_id")) {
            $query->where('user_id', request("user_id"));
        }
        
        $result          = $query->get();
        $data            = $result->all();

        return [
            'date'             => $isByTime ? $date->toTimeString() : $date->toDateTimeString(),
            'value'            => !empty($data) ? $data[0]->count : 0,
            'individual_count' => !empty($data) ? $data[0]->individual_count : 0,
        ];
    }
    
    public function locations($id)
    {
        // if date filter is given
        if(request("date_from")) { 
            $dateFrom   = \Carbon\Carbon::createFromFormat(
                'd/m/y',
                request("date_from") ?: date('d/m/y')
            )->startOfDay();
        }
        if(request("date_from")) {
            $dateTo = \Carbon\Carbon::createFromFormat(
                'd/m/y',
                request("date_from") ?: '01/01/18'
            )->endOfDay();
        }
        
        $query = DB::table('form_instances')
                   ->join('users', 'users.id', '=', 'form_instances.user_id')
                   ->join('site_references', 'site_references.id', '=', 'form_instances.site_id')
                   ->join(
                       'cluster_references',
                       'cluster_references.id',
                       '=',
                       'form_instances.cluster_id'
                   )
                   ->where('form_instances.project_id', $id)
                   ->whereNull("form_instances.deleted_at");
        
        // if date filter is given
        if(isset($dateFrom)) {
            $query->where("form_instances.date_start", ">=", $dateFrom);
        }
        if(isset($dateFrom)) {
            $query->where("form_instances.date_start", "<=", $dateTo);
        }

        // if site filter is set
        if(request('governorate_id')) {
            $query->where("form_instances.governorate_id", request('governorate_id'));
        }
        
        // if site filter is set
        if(request('district_id')) {
            $query->where("form_instances.district_id", request('district_id'));
        }
        
        // if site filter is set
        if(request('site_id')) {
            $query->where("form_instances.site_id", request('site_id'));
        }
        
        // if cluster filter is set
        if(request('cluster_id')) {
            $query->where("form_instances.cluster_id", request('cluster_id'));
        }
        // if user filter is set
        if(request('user_id')) {
            $query->where("form_instances.user_id", request('user_id'));
        }

    
        // if user_id is set then grouping all by locations
        if (request("user_id")) {
            // concatinating both locations of on sites for the respective collector
            $location = 'CONCAT(site_references.lat,",",site_references.lng) as location';
            $query->groupBy('location');
            $location = 'CONCAT(form_instances.lat,",",form_instances.lng) as location';
            $query->where('form_instances.user_id', request("user_id"));
            $query->groupBy('form_instances.id');
            $query->select(
                DB::raw(
                    'MAX(users.username) as username,MAX(site_references.name) as site_name,MAX(cluster_references.name) as cluster_name,'.$location.',MAX(form_instances.date_start) as date_start,MAX(form_instances.date_end) as date_end'
                )
            );
        }
        else{
             // concatinating both locations of each form_instance submitted
            $location = 'CONCAT(form_instances.lat,",",form_instances.lng) as location';
            $query->groupBy('location');
            $query->select(
                DB::raw(
                    // 'MAX(users.username) as username,MAX(site_references.name) as site_name,MAX(cluster_references.name) as cluster_name,'.$location.',MAX(form_instances.date_start) as date_start,MAX(form_instances.date_end) as date_end'
                    'users.username as username,site_references.name as site_name,cluster_references.name as cluster_name,'.$location.',form_instances.date_start as date_start,form_instances.date_end as date_end'
                )
            );
        }
        
        $result = $query->get();
        
        return $this->successData($result->all());
    }
    
    public function performance($id)
    {
        
        $dateTo   = \Carbon\Carbon::createFromFormat(
            'd/m/y',
            request("date_to") ?: date('d/m/y')
        );
        
        $dateFrom = \Carbon\Carbon::createFromFormat(
            'd/m/y',
            request("date_from") ?: '01/01/18'
        );
        
        if (!request("user_id") && !request("site_id") && !request("cluster_id")) 
        {
            return $this->failed("Invalid filter");
        }
        
        
        $query = DB::table('form_instances')
                   ->select(
                       DB::raw(
                           'count(id) as count,concat(DAY(`created_at`),"-",MONTH(`created_at`),"-",YEAR(`created_at`)) as submission_day'
                       )
                   )
                   ->where('project_id', $id)
                   ->whereNull("deleted_at")
                   ->where("created_at", ">=", $dateFrom)
                   ->where("created_at", "<=", $dateTo)
                   ->orderBy("submission_day", "DESC")
                   ->groupBy('submission_day');
        
        if(request('site_id')) {
            $query->where("site_id", request('site_id'));
        }
        else if(request('cluster_id')) {
            $query->where("cluster_id", request('cluster_id'));
        }
        elseif (request("user_id")) {
            $query->where('user_id', request("user_id"));
        }
        
        $query->groupBy('user_id');
        $query->addSelect(
            DB::raw('(select u.name from users u where u.id=user_id) as name')
        );
        $result = $query->get();
        
        return $this->successData($result->all());
    }
    
        /**
     * summary of the results.
     *
     * @return \Illuminate\Http\Response
     */
    public function summaryPrevious($id)
    {
        $project = Project::find($id);
        
        $results            = [];
        $results['boxes']   = [];
        $results['diagram'] = [];
        
                //grouping form_instances by project and count the distinct site
        $siteData = DB::select(
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
        where fi.project_id = '.$id.' and qa.deleted_at '.$this->addGuestFilter().' is null and qa.individual_chunk is not null and qa.individual_chunk <> 0 group by qa.form_instance_id,qa.individual_chunk)  as test
        '
        );
        
        $results['boxes'][] = [
            "name"  => "Site",
            "value" => !empty($siteData) ? $siteData[0]->count : 0,
        ];
        
        $results['boxes'][] = [
            "name"  => "Cluster",
            "value" => !empty($clusterData) ? $clusterData[0]->count : 0,
        ];
        
        $results['boxes'][] = [
            "name"  => "Household",
            "value" => !empty($householdData) ? $householdData[0]->count : 0,
        ];
        $results['boxes'][] = [
            "name"  => "Individual",
            "value" => !empty($individualData) ? intval($individualData[0]->sum) : 0
        ];
        
        //if specific date exist return diagram by hours
        if ($dateFrom = request("date_from")) {
            $date0 = \Carbon\Carbon::createFromFormat('d/m/y', $dateFrom);
            $date0 = $date0->startOfDay();
            
            $date1 = $date0->copy();
            $date2 = $date0->copy();
            $date3 = $date0->copy();
            $date4 = $date0->copy();
            $date0->addHour(4);
            $date1->addHour(8);
            $date2->addHour(12);
            $date3->addHour(16);
            $date4->addHour(20);
            $results['diagram'][] = $this->getDiagramItem($id, $date0, 1);
            $results['diagram'][] = $this->getDiagramItem($id, $date1, 1);
            $results['diagram'][] = $this->getDiagramItem($id, $date2, 1);
            $results['diagram'][] = $this->getDiagramItem($id, $date3, 1);
            $results['diagram'][] = $this->getDiagramItem($id, $date4, 1);

        }
        
        //table is always by dates
        $date0 = \Carbon\Carbon::createFromFormat(
            'd/m/y',
            request("date_from") ?: date('d/m/y', $project->date_end->timestamp)
        );
        $date1 = $date0->copy();
        $date2 = $date0->copy();
        $date3 = $date0->copy();
        $date4 = $date0->copy();
        $date5 = $date0->copy();
        $date6 = $date0->copy();
        $date7 = $date0->copy();
        $date1->subDays(6);
        $date2->subDays(5);
        $date3->subDays(4);
        $date4->subDays(3);
        $date5->subDays(2);
        $date6->subDays(1);
        // the following 2 keeps track of the totalValue and totalIndividual 
        // so that it can be returned as sum in the response
        $totalValueCount = 0;
        $totalIndividualCount = 0;
        $results['tables'][] = $this->getDiagramItem($id, $date1);
        $totalValueCount+=$results['tables'][0]['value'];
        $totalIndividualCount+=$results['tables'][0]['individual_count'];
        $results['tables'][] = $this->getDiagramItem($id, $date2);
        $totalValueCount+=$results['tables'][1]['value'];
        $totalIndividualCount+=$results['tables'][1]['individual_count'];
        $results['tables'][] = $this->getDiagramItem($id, $date3);
        $totalValueCount+=$results['tables'][2]['value'];
        $totalIndividualCount+=$results['tables'][2]['individual_count'];
        $results['tables'][] = $this->getDiagramItem($id, $date4);
        $totalValueCount+=$results['tables'][3]['value'];
        $totalIndividualCount+=$results['tables'][3]['individual_count'];
        $results['tables'][] = $this->getDiagramItem($id, $date5);
        $totalValueCount+=$results['tables'][4]['value'];
        $totalIndividualCount+=$results['tables'][4]['individual_count'];
        $results['tables'][] = $this->getDiagramItem($id, $date6);
        $totalValueCount+=$results['tables'][5]['value'];
        $totalIndividualCount+=$results['tables'][5]['individual_count'];
        $results['tables'][] = $this->getDiagramItem($id, $date0);
        $totalValueCount+=$results['tables'][6]['value'];
        $totalIndividualCount+=$results['tables'][6]['individual_count'];
        if (empty($results['diagram'])) {
            $results['diagram'] = $results['tables'];
        }
        // after cloning it for diagram, only then add it to tables response
        // so that it only shows in the tables on the UI
        $results['tables'][] = [
            'date'             => 'Total',
            'value'            => $totalValueCount,
            'individual_count' => $totalIndividualCount,
        ];
        
        return $this->successData($results);
    }
}
