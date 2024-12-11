<?php
/**
 * Created by SublimeText.
 * User: rehmatulla.bhati
 * Date: 6/21/19
 * Time: 11:48 AM
 */

namespace App\Models;


use Idea\Base\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;

class SiteReference extends BaseModel {

	use SoftDeletes;
	protected $table = 'site_references';
	protected $dates = ['deleted_at'];

        public function governorate()
        {
            return $this->belongsTo(Governorate::class, "governorate_id");
        }
        
        public function district()
        {
            return $this->belongsTo(District::class, "district_id");
        }

	public function clusters() {
		return $this->hasMany(ClusterReference::class,'site_id','id');
	}
        
        public function children() {
		return $this->hasMany(ClusterReference::class,'site_id','id');
	}
        
        public function guestSites() {
		return $this->hasMany(GuestSite::class);
	}
        
        public function teams() {
		return $this->hasMany(Team::class,'site_id','id');
	}
        
        public function projects() {
		return $this->hasMany(ProjectDetail::class,'site_id');
	}
}