<?php
/**
 * Created by Netbeans.
 * User: rehmatullah.bhatti
 * Date: 6/10/19
 * Time: 7:02 PM
 */

namespace App\Models;

use Idea\Base\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;

class QuestionSettingAppearance extends BaseModel
{

    use SoftDeletes;
    protected $table = 'question_setting_appearance';
    protected $dates = ['deleted_at'];

}
