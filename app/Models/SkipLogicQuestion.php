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

class SkipLogicQuestion extends BaseModel
{

    use SoftDeletes;
    protected $table = 'skip_logic_questions';
    protected $dates = ['deleted_at'];


    public function skipLogicDetails()
    {
        return $this->hasMany(SkipLogicQuestionDetail::class, 'skip_logic_id', 'id');
    }
}
