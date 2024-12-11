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

class Question extends BaseModel {
	
	use SoftDeletes;
	
	protected $guarded = [];
	
	protected $dates = ['deleted_at'];
	
	protected $appends = ['response_type_code'];
	
	public function options() {
		return $this->hasMany(QuestionOption::class);
	}
	public function conditions() {
		return $this->hasMany(QuestionGroupCondition::class);
	}
	
	public function getSettingAttribute($value) {
		if (!empty($value) && $value != "[]" && $value != '""') {
			return json_decode($value);
		}
		return NULL;
		
	}
	public function answers(){
		return $this->hasMany(QuestionAnswer::class);
	}
	
	public function responseType() {
		return $this->belongsTo(QuestionResponseType::class, "response_type_id");
	}
        
        public function skipLogic(){
		return $this->hasMany(SkipLogicQuestion::class, 'parent_id', 'id');
	}
        
        public function questionSettingOptions(){
		return $this->hasOne(QuestionSettingOptions::class, 'question_id', 'id');
	}
        
        public function questionSettingAppearance(){
		return $this->hasOne(QuestionSettingAppearance::class, 'question_id', 'id');
	}
        
        public function questionAssignment(){
		return $this->hasOne(QuestionAssignment::class, 'question_id', 'id');
	}
	
	/**
	 * @return null
	 */
	public function getResponseTypeCodeAttribute() {
		if ($this->responseType) {
			return $this->responseType->code;
		}
		else {
			return $this->responseType()->code;
		}
	}
}