<?php
/**
 * Created by PhpStorm.
 * User: youssef.jradeh
 * Date: 5/24/18
 * Time: 1:32 AM
 */

namespace App\Models;

use Carbon\Carbon;
use Idea\Base\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends BaseModel
{
    protected $dates = ['updated_at', 'date_start', 'date_end'];//'created_at'

    protected $appends = ['completion_status'];
    
    public $hideTimestamp = false;
    
    use SoftDeletes;

    public function form()
    {
        return $this->hasOne(Form::class);
    }

    public function sites()
    {
        //return $this->hasMany(Site::class);
        return $this->belongsToMany(SiteReference::class, "project_details", "project_id", "site_id")->groupBy("project_id")->groupBy("site_id");
    }

    // public function activeSites()
    // {
    //     return $this->hasMany(SiteReference::class)->where("status", 1);
    // }

    // public function inactiveSites()
    // {
    //     return $this->hasMany(SiteReference::class)->where("status", 0);
    // }

    //TODO: to be removed and used as per discssion on Saturday Call 19th October between Rehmat , Shahrukh & Shuja
    public function activeSites()
    {
        return $this->hasMany(Site::class)->where("status", 1);
    }

    //TODO: to be removed and used as per discssion on Saturday Call 19th October between Rehmat , Shahrukh & Shuja
    public function inactiveSites()
    {
        return $this->hasMany(Site::class)->where("status", 0);
    }

    public function referenceSites()
    {
        return $this->belongsToMany(SiteReference::class, "project_details", "project_id", "site_id");
    }
    
    public function projectDisease()
    {
        return $this->belongsToMany(DiseaseBank::class, "disease_details", "project_id", "disease_id");
    }

    public function createdBy()
    {
        return $this->hasOne(User::class, "id", "created_by");
    }
    
    public function icdCode()
    {
        return $this->belongsTo(IcdCode::class, "icd_code");
    }

    public function teams()
    {
        return $this->hasMany(Team::class);
    }

    public function members()
    {
        return $this->hasMany(ProjectMember::class, "project_id"); //->whereNull("team_id")
    }

    public function clusters()
    {
        return $this->hasMany(ClusterReference::class);
    }

    public function indicators()
    {
        return $this->hasMany(Indicator::class);
    }

    public function projectDetails()
    {
        return $this->hasMany(ProjectDetail::class, "project_id");
    }

    public function getCompletionStatusAttribute()
    {
        if(!$this->date_start || !$this->date_end)
        {
            return -1;
        }
        
        if ($this->date_start->gt(Carbon::now())) {
            return -1;
        }
        if ($this->date_end->lt(Carbon::now())) {
            return 1;
        }
        
        return 0;
    }

}
