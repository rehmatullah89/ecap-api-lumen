<?php

/*
 * This file is part of the IdeaToLife package.
 *
 * (c) Youssef Jradeh <youssef.jradeh@ideatolife.me>
 *
 */

namespace App\Http\Controllers\Result;

use App\Http\Controllers\WhoController;
use Illuminate\Support\Facades\DB;

class PerformanceController extends WhoController {
	
	protected $permissions = [
		"byTeam" => ["code" => "trackings", "action" => "read"],
	];
	
	/**
	 *
	 * @return array
	 */
	protected static function validationRules() {
		return [
		];
	}
	
	/**
	 * summary of the results.
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function byTeam($id) {
		
		//custom query to group by question_id`,value
		$query = 'select SUM(fi.individual_count) as individual_count,MAX(fi.id) as submission_id,MAX(fi.lng) as lng,MAX(fi.lat) as lat, min(fi.date_start) as start, max(fi.date_end) as end, '
                        . ' MAX(s.name) as team, '
                        . ' MAX(c.name) as cluster, '
                        . ' MAX(u.name) as member, '
                        . ' MAX(s.name) as site ' .
		         ' from form_instances fi ' .
		         ' inner join users u on fi.user_id=u.id ' .
		         ' inner join site_references s on fi.site_id=s.id ' .
		         ' inner join cluster_references c on fi.cluster_id=c.id ';
		
		$query .= ' where fi.project_id = ' . $id . ' and fi.instance_type = "collection" and fi.deleted_at is null AND fi.date_end IS NOT NULL AND fi.date_start IS NOT NULL ';
		
		if ($userId = request("user_id")) {
			$query .= " and fi.user_id=" . $userId;
		}
                
                $governorateId = request("governorate_id");
                $districtId = request("district_id");
                $siteId = request("site_id");
                $clusterId = request("cluster_id");
                
                if ($clusterId != null && $clusterId != 'null') {
			$query .= " and fi.cluster_id=" . $clusterId;
		}
                else if ($siteId != null && $siteId != 'null') {
			$query .= " and fi.site_id=" . $siteId;
		}
                else if ($districtId != null && $districtId != 'null') {
			$query .= " and fi.district_id=" . $districtId;
		}
                else if ($governorateId != null && $governorateId != 'null') {
			$query .= " and fi.governorate_id=" . $governorateId;
		}
		
		$dateFrom = \Carbon\Carbon::createFromFormat('d/m/y', request("date"))
		                          ->startOfDay()
		                          ->toDateTimeString();
		$dateTo   = \Carbon\Carbon::createFromFormat('d/m/y', request("date"))
		                          ->endOfDay()
		                          ->toDateTimeString();
		$query    .= " AND fi.date_start >= '$dateFrom' AND fi.date_start< '$dateTo' ";
		
		$query   .= ' group by fi.id';
		
		$results = DB::select($query);
		
		//if empty return nothing
		if (empty($results)) {
			return $this->successData();
		}
		
		return $this->successData($this->_group_by($results, "member"));
	}
	
	function _group_by($array, $key) {
		$return = [];
		foreach ($array as $val) {
			$return[$val->{$key}][] = $val;
		}
		return $return;
	}
	
}