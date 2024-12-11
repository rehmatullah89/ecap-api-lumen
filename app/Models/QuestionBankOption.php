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

class QuestionBankOption extends BaseModel {
	
	use SoftDeletes;
	protected $guarded = [];
	protected $table = 'question_bank_options';
	
	protected $dates = ['deleted_at'];
}