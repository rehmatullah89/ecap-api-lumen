<?php
/**
 * Created by Netbeans.
 * User: rehmatullah.bhatti
 * Date: 6/17/19
 * Time: 5:18 PM
 */

namespace App\Models;

use Idea\Base\BaseModel;

class ProjectDetail extends BaseModel
{

    protected $table = 'project_details';

    public function cluster() {
		return $this->belongsTo(ClusterReference::class, "cluster_id");
    }
    
    public function site() {
		return $this->belongsTo(SiteReference::class, "site_id");
    }
        
    public function district() {
            return $this->belongsTo(District::class, "district_id");
    }
    
    public function governorate() {
            return $this->belongsTo(Governorate::class, "governorate_id");
    }
}
