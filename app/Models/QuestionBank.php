<?php
/**
 * Created by Netbeans.
 * User: rehmatullah.bhatti
 * Date: 06/12/19
 * Time: 6:50 PM
 */

namespace App\Models;

use Idea\Base\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;

class QuestionBank extends BaseModel {
	
	use SoftDeletes;
	
	protected $guarded = [];
	
        protected $table = 'question_bank';
        
	protected $dates = ['deleted_at'];
	
	protected $appends = ['response_type_code'];
	
	public function options() {
		return $this->hasMany(QuestionBankOption::class, "question_id");
	}
	
        public function responseType() {
		return $this->belongsTo(QuestionResponseType::class, "response_type_id");
	}
        
	public function getSettingAttribute($value) {
		if (!empty($value) && $value != "[]" && $value != '""') {
			return json_decode($value);
		}
		return NULL;
		
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