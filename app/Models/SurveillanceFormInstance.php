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

class SurveillanceFormInstance extends BaseModel {
	
	use SoftDeletes;
	
	protected $dates = ['deleted_at'];
	protected $table = "surveillance_form_instances";
}