<?php
/**
 * Created by PhpStorm.
 * User: youssef.jradeh
 * Date: 5/24/18
 * Time: 1:32 AM
 */

namespace App\Models;


use Idea\Base\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;

class Team extends BaseModel {
	
	use SoftDeletes;
	
	protected $dates = ['deleted_at'];
	
	public function clusters() {
		return $this->belongsToMany(ClusterReference::class, 'team_clusters', 'team_id', 'cluster_id');
	}
	
	public function site() {
		return $this->belongsTo(SiteReference::class, 'site_id', 'id');
	}
	
}