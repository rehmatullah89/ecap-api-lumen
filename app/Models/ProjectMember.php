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

class ProjectMember extends BaseModel {
	
    use SoftDeletes;
	protected $table = 'project_members';
	protected $dates = ['deleted_at'];
        
	public function team() {
		return $this->belongsTo(Team::class);
	}
        
        public function user() {
		return $this->belongsTo(User::class);
	}
}