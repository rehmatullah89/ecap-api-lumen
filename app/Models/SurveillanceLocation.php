<?php
/**
 * Created by PhpStorm.
 * User: rehmatullah.bhatti
 * Date: 6/13/19
 * Time: 7:09 PM
 */

namespace App\Models;


use Idea\Base\BaseModel;

class SurveillanceLocation extends BaseModel {
	
    protected $table = "surveillance_locations";
        
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
    
    public function governorate()
    {
        return $this->belongsTo(Governorate::class);
    }
    
    public function district()
    {
        return $this->belongsTo(District::class);
    }
    
    public function site()
    {
        return $this->belongsTo(SiteReference::class);
    }
    
    public function cluster()
    {
        return $this->belongsTo(ClusterReference::class);
    }
    
}