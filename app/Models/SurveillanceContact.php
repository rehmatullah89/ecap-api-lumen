<?php
/**
 * Created by PhpStorm.
 * User: rehmatullah.bhatti
 * Date: 6/13/19
 * Time: 7:09 PM
 */

namespace App\Models;


use Idea\Base\BaseModel;

class SurveillanceContact extends BaseModel {
	
    protected $table = "surveillance_contacts";
 
        /*
     * this function is set user relation with roles
     * a user can have many roles
     */
    public function contacts()
    {
        return $this->hasMany(
            SurveillanceContact::class,
            'user_id',
            'user_id'
        );
    }
}