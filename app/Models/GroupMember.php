<?php
/**
 * Created by PhpStorm.
 * User: rehmatullah.bhatti
 * Date: 07/05/19
 * Time: 3:13 PM
 */

namespace App\Models;


use Idea\Base\BaseModel;

class GroupMember extends BaseModel {
	
	protected $table = 'group_members';
        
	public function group() {
		return $this->belongsTo(Group::class);
	}

}