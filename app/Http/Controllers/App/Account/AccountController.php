<?php

/*
 * This file is part of the IdeaToLife package.
 *
 * (c) Youssef Jradeh <youssef.jradeh@ideatolife.me>
 *
 */

namespace App\Http\Controllers\App\Account;

use Idea\Base\BaseController;
use Idea\Types\ExceptionType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\SurveillanceLocation;
use App\Models\ProjectLocationDetail;
use Idea\Models\User;
use Carbon\Carbon;


class AccountController extends BaseController {

	use ExceptionType;

	/**
	 * @param \Illuminate\Http\Request $request
	 */
	public function __construct(Request $request) {
		parent::__construct($request);

		//five requests per second only
		$this->middleware('throttle:5,1');
	}

	protected static function validationRules() {
		return [
			'changePassword' => [
				'current_password' => 'required',
				'new_password'     => 'required',
			],
		];
	}

	/**
	 * Function to change password
	 *
	 * @int user_id
	 * @string current_password
	 * @string new_password
	 * @return static
	 */
	public function changePassword() {
		if (!$this->user) {
			return $this->failed("idea::general.invalid_user_password");
		}

		if (!Hash::check(request('current_password'), $this->user->password)) {
			return $this->failed("idea::general.invalid_user_password");
		}

		$current = Carbon::now();

		//update user request
		$this->user->password_changed_at = $current;
		$this->user->password            = Hash::make($this->in('new_password'));
		$this->user->save();

		return $this->success();
	}

        /**
	 * returns user locations according to type
	 *
	 * @int user_id
	 * @string project_id
	 * @string user_type
	 */
        public function getUserLocations()
        {

                $user = ($this->user->id > 0)?$this->user->id:(int)@request("user_id");
                $projectId = request("project_id");
                $userType = request("user_type");
                $userObject = User::find($user);

                if (!$userObject) {
                    return $this->failed("No user exist against this id.");
                }

                $dataList = [];
                if(ProjectLocationDetail::where("project_id", $projectId)->where("user_id", $user)->count() > 0 && $userType != 'verifier')
                {
                    $selectedGovernorates = ProjectLocationDetail::where("project_id", $projectId)->where("user_id", $user)->orderBy("governorate_id")->pluck("governorate_id", "governorate_id")->toArray();
                    $selectedDistricts = ProjectLocationDetail::where("project_id", $projectId)->where("user_id", $user)->pluck("district_id", "district_id")->toArray();
                    $selectedSites = ProjectLocationDetail::where("project_id", $projectId)->where("user_id", $user)->pluck("site_id", "site_id")->toArray();
                    $selectedClusters = ProjectLocationDetail::where("project_id", $projectId)->where("user_id", $user)->pluck("cluster_id", "cluster_id")->toArray();

                    foreach($selectedGovernorates as $governorateId)
                    {
                        $dDataList = [];
                        $governorate = \App\Models\Governorate::find($governorateId);
                        $gDataList = array('id'=>$governorate->id, 'name'=>$governorate->name." ("."Governorate)");

                        $governorateDistricts = \App\Models\District::where("governorate_id", $governorate->id)->pluck("id","id")->toArray();
                        foreach($selectedDistricts as $districtId)
                        {
                            if(in_array($districtId, $governorateDistricts))
                            {
                                $sDataList = [];
                                $district = \App\Models\District::find($districtId);
                                $dNewDataList = array('id'=>$district->id, 'name'=>$district->name." ("."District)", 'governorate_id'=>$district->governorate_id);

                                $districtSites = \App\Models\SiteReference::where("district_id", $districtId)->pluck("id","id")->toArray();
                                foreach($selectedSites as $siteId)
                                {
                                    if(in_array($siteId, $districtSites))
                                    {
                                        $cDataList = [];
                                        $site = \App\Models\SiteReference::find($siteId);
                                        $sNewDataList = array('id'=>$site->id, 'name'=>$site->name." ("."Site)", 'governorate_id'=>$site->governorate_id, 'district_id'=>$site->district_id);

                                        $siteClusters = \App\Models\ClusterReference::where("site_id", $siteId)->pluck("id","id")->toArray();
                                        foreach($selectedClusters as $clusterId)
                                        {
                                            if(in_array($clusterId, $siteClusters))
                                            {
                                                $cluster = \App\Models\ClusterReference::find($clusterId);
                                                $cDataList[] = array('id'=>$cluster->id, 'name'=>$cluster->name." ("."Cluster)", 'site_id'=>$cluster->site_id);
                                            }
                                        }
                                        $sDataList[] = $sNewDataList + array('children'=>$cDataList);
                                    }
                                }
                                $dDataList[] = $dNewDataList + array('children'=>$sDataList);
                            }
                        }
                        $dataList[] = $gDataList + array('children'=>$dDataList);
                    }
                }
                else if(SurveillanceLocation::where("user_id", $user)->count() > 0 && $userType != 'verifier')
                {
                    $selectedGovernorates = SurveillanceLocation::where("user_id", $user)->orderBy("governorate_id")->pluck("governorate_id", "governorate_id")->toArray();
                    $selectedDistricts = SurveillanceLocation::where("user_id", $user)->pluck("district_id", "district_id")->toArray();
                    $selectedSites = SurveillanceLocation::where("user_id", $user)->pluck("site_id", "site_id")->toArray();
                    $selectedClusters = SurveillanceLocation::where("user_id", $user)->pluck("cluster_id", "cluster_id")->toArray();

                    foreach($selectedGovernorates as $governorateId)
                    {
                        $dDataList = [];
                        $governorate = \App\Models\Governorate::find($governorateId);
                        $gDataList = array('id'=>$governorate->id, 'name'=>$governorate->name." ("."Governorate)");

                        $governorateDistricts = \App\Models\District::where("governorate_id", $governorate->id)->pluck("id","id")->toArray();
                        foreach($selectedDistricts as $districtId)
                        {
                            if(in_array($districtId, $governorateDistricts))
                            {
                                $sDataList = [];
                                $district = \App\Models\District::find($districtId);
                                $dNewDataList = array('id'=>$district->id, 'name'=>$district->name." ("."District)", 'governorate_id'=>$district->governorate_id);

                                $districtSites = \App\Models\SiteReference::where("district_id", $districtId)->pluck("id","id")->toArray();
                                foreach($selectedSites as $siteId)
                                {
                                    if(in_array($siteId, $districtSites))
                                    {
                                        $cDataList = [];
                                        $site = \App\Models\SiteReference::find($siteId);
                                        $sNewDataList = array('id'=>$site->id, 'name'=>$site->name." ("."Site)", 'governorate_id'=>$site->governorate_id, 'district_id'=>$site->district_id);

                                        $siteClusters = \App\Models\ClusterReference::where("site_id", $siteId)->pluck("id","id")->toArray();
                                        foreach($selectedClusters as $clusterId)
                                        {
                                            if(in_array($clusterId, $siteClusters))
                                            {
                                                $cluster = \App\Models\ClusterReference::find($clusterId);
                                                $cDataList[] = array('id'=>$cluster->id, 'name'=>$cluster->name." ("."Cluster)", 'site_id'=>$cluster->site_id);
                                            }
                                        }
                                        $sDataList[] = $sNewDataList + array('children'=>$cDataList);
                                    }
                                }
                                $dDataList[] = $dNewDataList + array('children'=>$sDataList);
                            }
                        }
                        $dataList[] = $gDataList + array('children'=>$dDataList);
                    }
                }else{

                    $clusters = \App\Models\ClusterReference::pluck("id", "id")->toArray();
                    $sites = \App\Models\ClusterReference::pluck("site_id", "site_id")->toArray();
                    $districts = \App\Models\SiteReference::whereIn("id", $sites)->pluck("district_id", "district_id")->toArray();
                    $governorates = \App\Models\District::whereIn("id", $districts)->pluck("governorate_id", "governorate_id")->toArray();

                    foreach($governorates as $governorateId)
                    {
                        $dDataList = [];
                        $governorate = \App\Models\Governorate::find($governorateId);
                        $gDataList = array('id'=>$governorate->id, 'name'=>$governorate->name);

                        $governorateDistricts = \App\Models\District::where("governorate_id", $governorate->id)->pluck("id","id")->toArray();
                        foreach($districts as $districtId)
                        {
                            if(in_array($districtId, $governorateDistricts))
                            {
                                $sDataList = [];
                                $district = \App\Models\District::find($districtId);
                                $dNewDataList = array('id'=>$district->id, 'name'=>$district->name, 'governorate_id'=>$district->governorate_id);

                                $districtSites = \App\Models\SiteReference::where("district_id", $districtId)->pluck("id","id")->toArray();
                                foreach($sites as $siteId)
                                {
                                    if(in_array($siteId, $districtSites))
                                    {
                                        $cDataList = [];
                                        $site = \App\Models\SiteReference::find($siteId);
                                        $sNewDataList = array('id'=>$site->id, 'name'=>$site->name, 'governorate_id'=>$site->governorate_id, 'district_id'=>$site->district_id);

                                        $siteClusters = \App\Models\ClusterReference::where("site_id", $siteId)->pluck("id","id")->toArray();
                                        foreach($clusters as $clusterId)
                                        {
                                            if(in_array($clusterId, $siteClusters))
                                            {
                                                $cluster = \App\Models\ClusterReference::find($clusterId);
                                                $cDataList[] = array('id'=>$cluster->id, 'name'=>$cluster->name, 'site_id'=>$cluster->site_id);
                                            }
                                        }
                                        $sDataList[] = $sNewDataList + array('children'=>$cDataList);
                                    }
                                }
                                $dDataList[] = $dNewDataList + array('children'=>$sDataList);
                            }
                        }
                        $dataList[] = $gDataList + array('children'=>$dDataList);
                    }
                }

                return $this->successData($dataList);
        }
}