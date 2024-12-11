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

class OptionsOperator extends BaseModel {
	
	use SoftDeletes;
	protected $dates = ['deleted_at'];
	
	/*public function sites() {
		return $this->hasMany(Site::class);
	}*/
}