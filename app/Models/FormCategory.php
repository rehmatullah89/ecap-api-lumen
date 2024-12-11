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

class FormCategory extends BaseModel {
	
	use SoftDeletes;
	protected $guarded = [];
	
	protected $dates = ['deleted_at'];
	
	public function groups() {
		return $this->hasMany(QuestionGroup::class);
	}
	public function type() {
		return $this->belongsTo(FormType::class,'form_type_id');
	}
}