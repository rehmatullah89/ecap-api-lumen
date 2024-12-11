<?php
/**
 * Created by netBeans.
 * User: rehmatullah.bhatti
 * Date: 7/03/19
 * Time: 6:34 PM
 */

namespace App\Models;


use Idea\Base\BaseModel;

class ProjectPermission extends BaseModel {
	
    protected $table = 'project_permissions';

    public function permission()
    {
        return $this->belongsTo(SystemPermission::class);
    }
    
    public function permissionProject()
    {
        return $this->belongsTo(Permission::class, 'permission_id');
    }

    public function action()
    {
        return $this->belongsTo(Action::class);
    }
}