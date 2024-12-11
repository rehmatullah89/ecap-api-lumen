<?php

/*
 *
 * (c) Rehmat Ullah <rehmatullah.bhatti@ideatolife.me>
 *
 */

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\WhoController;
use Idea\Helpers\Paging;
use App\Models\ResultFilter;

class ResultFiltersController extends WhoController {

    public $filePath = "sites/";

    protected $permissions = [
        "index" =>   ["code" => "results", "action" => "read"],
        "one" =>     ["code" => "results", "action" => "read"],
        "store" =>   ["code" => "results", "action" => "write"],
        "update" =>  ["code" => "results", "action" => "write"],
        "destroy" => ["code" => "results", "action" => "write"],
    ];

    /**
     *
     * @return array
     */
    protected static function validationRules() {
        return [
            'index' => [
                "project_id" => "required",
            ],
            'store' => [
                "project_id" => "required",
                "title" => "required",
            ],
            'update' => [
                "project_id" => "required",
                "title" => "required",
            ],
        ];
    }

    /**
     * Display a listing of the reference category.
     *
     * @return \Illuminate\Http\Response
     */
    public function index() {
        
        $projectId = request('project_id');
        $filters = ResultFilter::where("project_id", $projectId)->pluck("id")->toArray();
        
        $data = [];

        if(!empty($filters))
            foreach($filters as $id)
                $data[] = $this->getData($id);
            
        return $this->successData($data);
    }

    /**
     * Display the specified reference category.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function one($id) {              
        return $this->successData($this->getData($id));
    }
    
    /**
     * $param $id
     * return filter object
     * Get Filter Data
     */
    private function getData($id){
        $filter = ResultFilter::find($id);
        
        if(!empty($filter['site_ids']))
            $filter['site_ids'] = \App\Models\SiteReference::whereIn("id", explode (",", $filter['site_ids']))->get();
        
        if(!empty($filter['cluster_ids']))
            $filter['cluster_ids'] = \App\Models\ClusterReference::whereIn("id", explode (",", $filter['cluster_ids']))->get();
        
        if(!empty($filter['question_ids']))
            $filter['question_ids'] = \App\Models\Question::whereIn("id", explode (",", $filter['question_ids']))->get();
        
        if(!empty($filter['collector_ids']))
            $filter['collector_ids'] = \App\Models\User::whereIn("id", explode (",", $filter['collector_ids']))->get();  
        
        return $filter;
    }

    /**
     * Store a newly created reference category in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function store() {
        $projectId = request("project_id");

        if(!($projectId > 0))
          return $this->failed("Invalid Project Id.");
            
        $resultFilter = new ResultFilter();
        $resultFilter->project_id = $projectId;     
        $resultFilter->title = request("title");
        
        if(!empty(request("date_from")))
        $resultFilter->date_from = \Carbon\Carbon::createFromFormat(
            'd/m/Y',
            request("date_from")
        )->toDateTimeString();
        
        if(!empty(request("date_to")))
        $resultFilter->date_to = \Carbon\Carbon::createFromFormat(
            'd/m/Y',
            request("date_to")
        )->toDateTimeString();
                
        $resultFilter->site_ids = (request("site_ids") != "")?implode(",", request("site_ids")):request("site_ids");
        $resultFilter->cluster_ids = (request("cluster_ids") != "")?implode(",", request("cluster_ids")):request("cluster_ids");
        $resultFilter->question_ids = (request("question_ids") != "")?implode(",", request("question_ids")):request("question_ids");
        $resultFilter->collector_ids = (request("collector_ids") != "")?implode(",", request("collector_ids")):request("collector_ids");
        $resultFilter->save();
        
        $data = [];
        $filters = ResultFilter::where("project_id", $projectId)->pluck("id")->toArray();

        if(!empty($filters))
            foreach($filters as $id)
                $data[] = $this->getData($id);

        return $this->successData($data);
    }

    /**
     * Update the specified reference category in storage.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function update($id) {
        
        $projectId = (int)request("project_id");
        $resultFilter = ResultFilter::find($id);
        
        if($projectId == 0 || !$resultFilter)
            return $this->failed("Invalid Project Id or Invalid Filter Combination.");
         
        $resultFilter->project_id = $projectId;     
        $resultFilter->title = request("title");        
        $resultFilter->date_from = null;
        $resultFilter->date_to = null;
                
        if(!empty(request("date_from")))
        $resultFilter->date_from = \Carbon\Carbon::createFromFormat(
            'd/m/Y',
            request("date_from")
        )->toDateTimeString();
        
        if(!empty(request("date_to")))
        $resultFilter->date_to = \Carbon\Carbon::createFromFormat(
            'd/m/Y',
            request("date_to")
        )->toDateTimeString();
                
        $resultFilter->site_ids = (request("site_ids") != "")?implode(",", request("site_ids")):request("site_ids");
        $resultFilter->cluster_ids = (request("cluster_ids") != "")?implode(",", request("cluster_ids")):request("cluster_ids");
        $resultFilter->question_ids = (request("question_ids") != "")?implode(",", request("question_ids")):request("question_ids");
        $resultFilter->collector_ids = (request("collector_ids") != "")?implode(",", request("collector_ids")):request("collector_ids");
        $resultFilter->save();
            
        $filter = $this->getData($id);
        $filter['filters'] = ['site_ids'=>request("site_ids"), 'cluster_ids'=>request("cluster_ids"), 'question_ids'=>request("question_ids"), 'collector_ids'=>request("collector_ids")];
        
        return $this->successData($filter);
    }
    
    /**
     * Remove the specified reference category from storage.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function destroy($id) {
        try {
            if (!$resultFilter = ResultFilter::find($id)) {
                return $this->failed("Invalid Result Filter");
            }
            //then delete the row from the database
            $resultFilter->delete();

            return $this->success('Result Filter deleted');
        } catch (\Exception $e) {
            return $this->failed('destroy error');
        }
    }
}
