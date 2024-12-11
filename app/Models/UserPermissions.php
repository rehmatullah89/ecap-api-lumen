<?php
/**
 * Created by netBeans.
 * User: rehmatullah.bhatti
 * Date: 7/03/19
 * Time: 6:34 PM
 */

namespace App\Models;


use Idea\Base\BaseModel;

class UserPermissions extends BaseModel {
	
    protected $table = 'user_permissions';

    public function actions() {
            return $this->belongsToMany(Action::class, 'id', 'permission_id');
    }
	
    public function permission()
    {
        return $this->belongsTo(SystemPermission::class);
    }

    public function action()
    {
        return $this->belongsTo(Action::class);
    }
}