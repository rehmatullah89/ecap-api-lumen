<?php
/**
 * Created by PhpStorm.
 * User: rehmatullah.bhatti
 * Date: 6/13/19
 * Time: 7:09 PM
 */

namespace App\Models;


use Idea\Base\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;

class CategoryReference extends BaseModel {
	
	use SoftDeletes;
	protected $dates = ['deleted_at'];
	
    
	public function questions() {
		return $this->hasMany(CategoryQuestion::class,'category_id','id');
	}
        
        public function categoryQuestions() {
		return $this->belongsTo(CategoryQuestion::class,'question_id');
	}
}