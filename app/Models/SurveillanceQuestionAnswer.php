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

class SurveillanceQuestionAnswer extends BaseModel
{
    
    use SoftDeletes;
    
    protected $dates = ['deleted_at'];
	protected $table = "surveillance_question_answers";
	
    public function formInstance()
    {
        return $this->hasOne(SurveillanceFormInstance::class, 'id', 'surveillance_form_instance_id');
    }

    public function question(){
        return $this->hasOne(Question::class, 'id', 'question_id');

    }
}