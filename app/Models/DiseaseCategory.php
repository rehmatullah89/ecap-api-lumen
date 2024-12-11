<?php
/**
 * Created by PhpStorm.
 * User: rehmatullah.bhatti
 * Date: 6/13/19
 * Time: 7:09 PM
 */

namespace App\Models;


use Idea\Base\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;

class DiseaseCategory extends BaseModel {
	
	use SoftDeletes;
	protected $dates = ['deleted_at'];
	protected $table = "disease_categories";
        
        public function diseases(){
            return $this->hasMany(DiseaseBank::class, 'disease_category_id');
        }
}