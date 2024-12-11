<?php

/*
 * This file is part of the IdeaToLife package.
 *
 * (c) Youssef Jradeh <youssef.jradeh@ideatolife.me>
 *
 */

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\WhoController;
use App\Models\ClusterReference;
use App\Models\SiteReference;
use Idea\Helpers\Paging;

class ClusterReferenceController extends WhoController {

	public $filePath = "clusters/";

	protected $permissions = [
		"index"   => ["code" => "cluster_configuration", "action" => "read"],
		"one"     => ["code" => "cluster_configuration", "action" => "read"],
		"store"   => ["code" => "cluster_configuration", "action" => "write"],
		"update"  => ["code" => "cluster_configuration", "action" => "write"],
		"destroy" => ["code" => "cluster_configuration", "action" => "write"],
	];

	/**
	 *
	 * @return array
	 */
	protected static function validationRules() {
		return [
			'index'  => [
				'site_id'    => 'required',
			],
			'store'  => [
				"name"    => "required|unique:cluster_references,name",
				'site_id' => "required|exists:site_references,id",
			],
			'update' => [
                            "name" => "required",
                            'site_id' => 'required|exists:site_references,id',
			],
		];
	}

	/**
	 * Display a listing of the resource.
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function index() {
		$query = request("site_id") ?
			ClusterReference::where("site_id", request("site_id"))
			: ClusterReference::all();

		return $this->successData(new Paging($query));
	}

        /**
	 * Display a listing of the clusters.
	 *
         * * @param  int $site_id
         * * @param  int $governorate_id
         * * @param  str $query
         * 
	 * @return \Illuminate\Http\Response
	 */
	public function clusters() {

                $siteId         = (int)@request("site_id");
                $governorateId  = request("governorate_id");
                $districtId     = (int)request("district_id");
                $searchQuery    = !empty(@request('query')) ? request('query') : "";

                if($governorateId > 0 && $districtId == 0 && $siteId == 0)
                {
                    $sitesData = SiteReference::where("governorate_id", $governorateId)->pluck("id","id")->toArray();

                    if(!empty($sitesData))
                        $query = ClusterReference::whereIn('site_id', $sitesData)
                            ->where(function ($q) use ($searchQuery) {
                                $q->where('name', 'LIKE', "%" . $searchQuery . "%");
                            })
                            ->with(["site.governorate", "site.district"]);
                }
                else if($governorateId > 0 && $districtId > 0 && $siteId == 0)
                {
                    $sitesData = SiteReference::where("governorate_id", $governorateId)->where("district_id", $districtId)->pluck("id","id")->toArray();

                    if(!empty($sitesData))
                        $query = ClusterReference::whereIn('site_id', $sitesData)
                            ->where(function ($q) use ($searchQuery) {
                                $q->where('name', 'LIKE', "%" . $searchQuery . "%");
                            })
                            ->with(["site.governorate", "site.district"]);
                }
                else if($governorateId > 0 && $siteId > 0)
                {
			$query = ClusterReference::where("site_id", request("site_id"))
                        ->where(function ($q) use ($searchQuery) {
                                $q->where('name', 'LIKE', "%" . $searchQuery . "%");
                            })
                        ->with(["site.governorate", "site.district"]);
                }
                else{
                        $query = ClusterReference::where("site_id", '!=', 0)
                        ->where(function ($q) use ($searchQuery) {
                                $q->where('name', 'LIKE', "%" . $searchQuery . "%");
                            })
                        ->with(["site.governorate", "site.district"]);
                }

		return $this->successData(new Paging($query));
	}

        /**
	 * Get districts against district.
	 *
	 * @return \Illuminate\Http\Response
	 */
        public function districts(){
            $governorateId  = (int)@request("governorate_id");        
            $projectId  = (int)@request("project_id");
            $project = \App\Models\Project::find($projectId);
            
            if($projectId> 0 && $project->project_type == 'survey'){                
                $sites = \App\Models\ProjectDetail::where("project_id", $projectId)->pluck("site_id","site_id")->toArray();
                $list = \App\Models\SiteReference::whereIn("id", $sites)->pluck("district_id","district_id")->toArray();
                
                $districts = \App\Models\District::where('governorate_id', $governorateId)->whereIn("id", $list)->get();
            }
            else {
                $districts = \App\Models\District::where('governorate_id', $governorateId)->get();
            }
            
            if (!$districts) 
                return $this->failed("Invalid governorate Id");
            else
                return $this->successData($districts);
        }
        
        /**
	 * Get sites against district.
	 *
	 * @return \Illuminate\Http\Response
	 */
        public function sites(){
            $districtId  = (int)@request("district_id");
            $governorateId  = (int)@request("governorate_id");            
            $sites = SiteReference::where('governorate_id', $governorateId)
                    ->orWhere('district_id', $districtId)->get();

            if (!$sites) 
                return $this->failed("Invalid governorate/district Id");
            else
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
		$cluster = ClusterReference::find($id);
		if (!$cluster) {
			return $this->failed("Invalid cluster Id");
		}

		return $this->successData($cluster);
	}

	/**
	 * Store a newly created resource in site storage.
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function store() {

		$cluster                 = new ClusterReference();
		$cluster->site_id        = request("site_id");
		$cluster->name           = request("name");
		$cluster->lat            = request("lat");
		$cluster->lng            = request("lng");
                $cluster->coordinates    = request("coordinates");
                $cluster->save();
                
		return $this->successData($cluster);
	}

	/**
	 * Update the specified resource in sites reference.
	 *
	 * @param  int $id
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function update($id) {
		$cluster = ClusterReference::find($id);
		if (!$cluster) {
			return $this->failed("Invalid Cluster");
		}
                
                if(ClusterReference::where("name", "LIKE", request("name"))->where("site_id", request("site_id"))->where("id", "!=", $id)->count() > 0){
                    return $this->failed("Cluster Name already exists!");
                }

		$cluster->name           = request("name");
                $cluster->site_id        = request("site_id");
		$cluster->lat            = request("lat");
		$cluster->lng            = request("lng");
                $cluster->coordinates    = request("coordinates");
		$cluster->save();
                
		return $this->successData($cluster);
	}
        
        /**
	 * Display a listing of the resource.
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function multipleClusters() 
        {
             // checking for the search query
            $sites = request("sites");
            $projectId = (int)request("project_id");
            $searchQuery = !empty(request('query')) ? request('query') : "";
            $data = ClusterReference::where(function ($q) use ($searchQuery, $sites, $projectId) {
                $q->where('name', 'LIKE', "%" . $searchQuery . "%");
                
                if(!empty($sites))
                    $q->whereIn('site_id', $sites);
                
                if($projectId>0)
                {
                    $clusters = \App\Models\ProjectDetail::where("project_id", $projectId)->pluck("cluster_id","cluster_id")->toArray();

                    if(!empty($clusters) && (@$clusters[0] != 0))
                        $q->whereIn("id", $clusters);
                }
                                    
            })->take(10)->get();
            
            return $this->successData($data);
	}

	/**
	 * Remove the specified resource from site references.
	 *
	 * @param  int $id
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function destroy($id) {
		try {
			if (!$cluster = ClusterReference::find($id)) {
				return $this->failed("Invalid Cluster");
			}

			if(\App\Models\FormInstance::where('cluster_id', $id)->count() == 0){
                            $cluster->delete();
                        }else
                            return $this->failed("Please remove first related data to remove this resourse.");

			return $this->success('Cluster deleted');
		} catch (\Exception $e) {
			return $this->failed('destroy error');
		}
	}
}