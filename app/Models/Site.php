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

class Site extends BaseModel {
	
	use SoftDeletes;
	protected $dates = ['deleted_at'];
	
        public function governorate()
        {
            return $this->belongsTo(Governorate::class, "governorate_id");
        }
    
	public function clusters() {
		return $this->hasMany(Cluster::class,'site_id','id');
	}
        
	public function guestSites() {
		return $this->hasMany(GuestSite::class);
	}
        
        public function teams() {
		return $this->hasMany(Team::class,'site_id','id');
	}
}