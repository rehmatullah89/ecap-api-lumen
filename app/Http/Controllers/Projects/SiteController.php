<?php

/*
 * This file is part of the IdeaToLife package.
 *
 * (c) Youssef Jradeh <youssef.jradeh@ideatolife.me>
 *
 */

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\WhoController;
use App\Models\Site;
use App\Models\Cluster;
use Idea\Helpers\Paging;

class SiteController extends WhoController
{
    
    public $filePath = "sites/";
    
    protected $permissions = [
    "index"   => ["code" => "sites", "action" => "read"],
    "one"     => ["code" => "sites", "action" => "read"],
    "store"   => ["code" => "sites", "action" => "write"],
    "update"  => ["code" => "sites", "action" => "write"],
    "destroy" => ["code" => "sites", "action" => "write"],
    ];
    
    /**
     *
     * @return array
     */
    protected static function validationRules() 
    {
        return [
        'index'  => [
        'project_id' => 'required|exists:projects,id',
        ],
        'store'  => [
        "name"           => "required",
        'project_id'     => 'required|exists:projects,id',
        'governorate_id' => 'required|exists:governorates,id',
        'lat'            => [
        'required_with:lng',
        'regex:/^[-]?(([0-8]?[0-9])\.(\d+))|(90(\.0+)?)$/',
        ],
        'lng'            => [
        'required_with:lat',
        'regex:/^[-]?((((1[0-7][0-9])|([0-9]?[0-9]))\.(\d+))|180(\.0+)?)$/',
        ],
        ],
        'update' => [
        "name"           => "required",
        'governorate_id' => 'required|exists:governorates,id',
        'lat'            => [
        'required_with:lng',
        'regex:/^[-]?(([0-8]?[0-9])\.(\d+))|(90(\.0+)?)$/',
        ],
        'lng'            => [
        'required_with:lat',
        'regex:/^[-]?((((1[0-7][0-9])|([0-9]?[0-9]))\.(\d+))|180(\.0+)?)$/',
        ],
        ],
        ];
    }
    
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index() 
    {
        $searchQuery = !empty(@request('query')) ? request('query') : "";
        $projectId = request("project_id");
        $project = \App\Models\Project::find($projectId);
        
        if($projectId> 0 && $project->project_type == 'survey')
            $sites = \App\Models\ProjectDetail::where("project_id", $projectId)->pluck("site_id","site_id")->toArray();
        else
            $sites = \App\Models\ClusterReference::pluck("site_id","site_id")->toArray();
        
        if(empty($sites))
            return $this->failed("Invalid Project Id or There are no Sites available against this Project!");
        
        $data = [];
        if(!empty($sites)){
            $query = \App\Models\SiteReference::with("clusters")
                    ->where(function ($q) use ($searchQuery) {
                        if (!empty($searchQuery)) {
                            $q->where('name', 'LIKE', "%" . $searchQuery . "%");
                        } else {
                            $q->where('id', '!=', 0);
                        }
                    })
                    ->whereIn("id", $sites);
            $data = new Paging($query);
        }
            
        request("per_page", 10);
        
        return $this->successData($data);
    }
    
    /**
     * Display the specified resource.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function one($id) 
    {
        $site = Site::find($id);
        if (!$site) {
            return $this->failed("Invalid site Id");
        }
        return $this->successData($site);
    }
    
    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function store() 
    {
        $site                 = new Site();
        $site->project_id     = request("project_id");
        $site->governorate_id = request("governorate_id");
        $site->name           = request("name");
        $site->description    = request("description");
        $site->frontend_color = request("frontend_color");
        $site->frontend_icon  = request("frontend_icon");
        $site->lat            = request("lat");
        $site->lng            = request("lng");
        $site->url            = request("url");
        $site->save();
        
        return $this->successData($site);
    }
    
    /**
     * Update the specified resource in storage.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function update($id) 
    {
        
        $site = Site::find($id);
        if (!$site) {
            return $this->failed("Invalid Site");
        }
        
        $site->governorate_id = request("governorate_id");
        $site->name           = request("name");
        $site->description    = request("description");
        $site->frontend_color = request("frontend_color");
        $site->frontend_icon  = request("frontend_icon");
        $site->lat            = request("lat");
        $site->lng            = request("lng");
        $site->url            = request("url");
        $site->save();
        
        return $this->successData($site);
    }
    
    /**
     * Update the specified resource in storage.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function updateStatus($id) 
    {
        
        $site = Site::find($id);
        if (!$site) {
            return $this->failed("Invalid Site");
        }
        $site->status = request("status");
        $site->save();
        
        return $this->successData($site);
    }
    
    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function destroy($id) 
    {
        try {
            if (!$site = Site::find($id)) {
                return $this->failed("Invalid Site");
            }
            
            //then delete the row from the database
            $site->delete();
            
            return $this->success('Site deleted');
        } catch (\Exception $e) {
            return $this->failed('destroy error');
        }
    }
}
