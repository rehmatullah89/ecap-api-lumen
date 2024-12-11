<?php

/*
 * This file is part of the IdeaToLife package.
 *
 * (c) Youssef Jradeh <youssef.jradeh@ideatolife.me>
 *
 */

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\WhoController;
use App\Models\Cluster;
use App\Models\Site;
use Idea\Helpers\Paging;

class ClusterController extends WhoController {
	
	public $filePath = "clusters/";
	
	protected $permissions = [
		"index"   => ["code" => "clusters", "action" => "read"],
		"one"     => ["code" => "clusters", "action" => "read"],
		"store"   => ["code" => "clusters", "action" => "write"],
		"update"  => ["code" => "clusters", "action" => "write"],
		"destroy" => ["code" => "clusters", "action" => "write"],
	];
	
	/**
	 *
	 * @return array
	 */
	protected static function validationRules() {
		return [
			'index'  => [
				/*'site_id'    => 'required_without_all:project_id|exists:sites,id',
				'project_id' => 'required_without_all:site_id|exists:projects,id',*/
			],
			'store'  => [
				"name"    => "required",
				'site_id' => 'required|exists:sites,id',
				'lat'     => [
					'required_with:lng',
					'regex:/^[-]?(([0-8]?[0-9])\.(\d+))|(90(\.0+)?)$/',
				],
				'lng'     => [
					'required_with:lat',
					'regex:/^[-]?((((1[0-7][0-9])|([0-9]?[0-9]))\.(\d+))|180(\.0+)?)$/',
				],
			],
			'update' => [
				"name" => "required",
				'lat'  => [
					'required_with:lng',
					'regex:/^[-]?(([0-8]?[0-9])\.(\d+))|(90(\.0+)?)$/',
				],
				'lng'  => [
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
            $projectId = request("project_id");
            $searchQuery = !empty(@request('query')) ? request('query') : "";
            $project = \App\Models\Project::find($projectId);
            
            if($projectId> 0 && $project->project_type == 'survey')
                $clusters = \App\Models\ProjectDetail::where("project_id", $projectId)->pluck("cluster_id")->toArray();
            else
                $clusters = \App\Models\ClusterReference::pluck("id","id")->toArray();
        
            $data = [];
            if(request("project_id") > 0 && request("site_id") > 0){
                $query = \App\Models\ClusterReference::where(function ($q) use ($searchQuery) {
                        if (!empty($searchQuery)) {
                            $q->where('name', 'LIKE', "%" . $searchQuery . "%");
                        } else {
                            $q->where('id', '!=', 0);
                        }
                    })
                    ->whereIn("id", $clusters);
                    
                $query->where("site_id", request("site_id")); 
                    
                $data = new Paging($query);
            }
            else if(!empty($clusters) && request("project_id") > 0){
                $query = \App\Models\ClusterReference::where(function ($q) use ($searchQuery) {
                        if (!empty($searchQuery)) {
                            $q->where('name', 'LIKE', "%" . $searchQuery . "%");
                        } else {
                            $q->where('id', '!=', 0);
                        }
                    })
                    ->whereIn("id", $clusters);
                $data = new Paging($query);
            }
            else if(request("site_id") > 0){
                $query = \App\Models\ClusterReference::where("site_id", request("site_id"));
                $data = new Paging($query);
            }
                
            request("per_page", 10);
            return $this->successData($data);
	}
        
        /**
	 * Display a listing of the clusters.
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function clusters() {
            
		$siteId         = (int)@request("site_id");
                $governorateId  = request("governorate_id");
                $searchQuery    = !empty(@request('query')) ? request('query') : "";
                
                if($governorateId > 0 && $siteId == 0 && @request("project_id") > 0){
		    
                    $sitesDate = Site::where("project_id", request("project_id"))->where("governorate_id", $governorateId)->pluck("id")->toArray();	
                    
                    if(!empty($sitesDate))
                        $query = Cluster::whereIn('site_id', [implode(",", $sitesDate)])
                            ->where(function ($q) use ($searchQuery) {
                                $q->where('name', 'LIKE', "%" . $searchQuery . "%");
                            })
                            ->with("site.governorate");
                }
                else if($governorateId > 0 && $siteId > 0){
			$query = Cluster::where("site_id", request("site_id"))
                        ->where(function ($q) use ($searchQuery) {
                                $q->where('name', 'LIKE', "%" . $searchQuery . "%");
                            })
                        ->with("site.governorate");
                }
                else if(@request("project_id") > 0){
                        $query = Cluster::where("project_id", request("project_id"))
                        ->where(function ($q) use ($searchQuery) {
                                $q->where('name', 'LIKE', "%" . $searchQuery . "%");
                            })
                        ->with("site.governorate");
                }
		
		return $this->successData(new Paging($query));
	}
        
        /**
	 * Display a listing of the clusters.
	 *
	 * @return \Illuminate\Http\Response
	 */
        public function sites(){
            $governorateId  = (int)@request("governorate_id");
            $sites = Site::where('governorate_id', request("governorate_id"))->get();
            
            if (!$sites) {
                    return $this->failed("Invalid governorate Id");
            }

            return $this->successData($sites);
        }
	
	/**
	 * Display the specified resource.
	 *
	 * @param  int $id
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function one($id) {
		
		$cluster = Cluster::find($id);
		if (!$cluster) {
			return $this->failed("Invalid cluster Id");
		}
		
		return $this->successData($cluster);
	}
	
	/**
	 * Store a newly created resource in storage.
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function store() {
		
		$cluster                 = new Cluster();
		$cluster->site_id        = request("site_id");
		$cluster->project_id     = Site::find(request("site_id"))->project_id;
		$cluster->name           = request("name");
		$cluster->description    = request("description");
		$cluster->frontend_color = request("frontend_color");
		$cluster->frontend_icon  = request("frontend_icon");
		$cluster->lat            = request("lat");
		$cluster->lng            = request("lng");
                $cluster->url            = request("url");
		$cluster->save();
		
		return $this->successData($cluster);
	}
	
	/**
	 * Update the specified resource in storage.
	 *
	 * @param  int $id
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function update($id) {
		
		$cluster = Cluster::find($id);
		if (!$cluster) {
			return $this->failed("Invalid Cluster");
		}
		
		$cluster->name           = request("name");
                $cluster->site_id        = request("site_id");
		$cluster->description    = request("description");
		$cluster->frontend_color = request("frontend_color");
		$cluster->frontend_icon  = request("frontend_icon");
		$cluster->lat            = request("lat");
		$cluster->lng            = request("lng");
                $cluster->url            = request("url");
		$cluster->save();
		
		return $this->successData($cluster);
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
			if (!$cluster = Cluster::find($id)) {
				return $this->failed("Invalid Cluster");
			}
			
			//then delete the row from the database
			$cluster->delete();
			
			return $this->success('Cluster deleted');
		} catch (\Exception $e) {
			return $this->failed('destroy error');
		}
	}
}