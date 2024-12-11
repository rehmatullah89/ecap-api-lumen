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

class QuestionGroup extends \Baum\Node {

	use SoftDeletes;

	// 'parent_id' column name
	protected $parentColumn = 'parent_group';

//	 'lft' column name
	protected $leftColumn = 'lft';

	// 'rgt' column name
	protected $rightColumn = 'rgt';

	// 'depth' column name
	protected $depthColumn = 'depth';
//
	// guard attributes from mass-assignment
	protected $guarded = array('id', 'parent_id','nesting');


	protected $dates = ['deleted_at'];

	public function questions() {
		return $this->hasMany(Question::class)->orderBy('order', 'ASC');
	}
	public function conditions() {
		return $this->hasMany(QuestionGroupCondition::class);
	}

	public function children() {
		return $this->hasMany(QuestionGroup::class,"parent_group");
	}
}
