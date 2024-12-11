<?php

namespace App\Models;


use Idea\Base\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;

class Group extends BaseModel {
	
    use SoftDeletes;
	protected $table = 'groups';
	protected $dates = ['deleted_at'];
        
	public function members() {
		return $this->hasMany(GroupMember::class, "group_id");
	}

}