<?php
/**
 * Created by Netbeans.
 * User: rehmatullah.bhatti
 * Date: 6/18/19
 * Time: 7:09 PM
 */

namespace App\Models;


use Idea\Base\BaseModel;

class DiseaseBankQuestion extends BaseModel {
	
        protected $table = 'disease_bank_questions';
	
        public function questionDetails() {
		return $this->belongsTo(QuestionBank::class,'question_id');
	}
}