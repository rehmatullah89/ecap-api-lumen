<?php
/**
 * Created by Netbeans.
 * User: rehmatullah.bhatti
 * Date: 6/18/19
 * Time: 7:09 PM
 */

namespace App\Models;


use Idea\Base\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;

class CategoryQuestion extends BaseModel {
	
	use SoftDeletes;
        protected $table = 'category_questions';
        protected $dates = ['deleted_at'];
	
        public function questionDetails() {
		return $this->belongsTo(QuestionBank::class,'question_id');
	}
}