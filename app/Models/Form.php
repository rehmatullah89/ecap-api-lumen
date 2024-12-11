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

class Form extends BaseModel {
	
	use SoftDeletes;
	protected $guarded = [];
	
	protected $dates = ['deleted_at'];
	
	public function types() {
		return $this->hasMany(FormType::class);
	}

	public function questions(){
		return $this->hasMany(Question::class);
	}
        
        public function groups(){
		return $this->hasMany(QuestionGroup::class);
	}
        
        public function categories(){
		return $this->hasMany(FormCategory::class);
	}
}