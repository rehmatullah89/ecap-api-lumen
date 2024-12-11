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

class Governorate extends BaseModel {
	
	use SoftDeletes;
	protected $dates = ['deleted_at'];
	
	public function sites() {
		return $this->hasMany(Site::class);
	}
        
        public function districts() {
		return $this->hasMany(District::class);
	}
        
        public function children() {
		return $this->hasMany(District::class);
	}
}