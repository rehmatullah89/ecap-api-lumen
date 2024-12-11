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

class SkipLogicQuestionDetail extends BaseModel {
	
	use SoftDeletes;
        protected $table = 'skip_logic_question_details';
	protected $dates = ['deleted_at'];
	
	/*public function sites() {
		return $this->hasMany(Site::class);
	}*/
}