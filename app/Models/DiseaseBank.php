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

class DiseaseBank extends BaseModel {
	
	use SoftDeletes;
	protected $dates = ['deleted_at'];
	protected $table = "disease_bank";
        
        public function questions() {
		return $this->hasMany(DiseaseBankQuestion::class,'disease_bank_id','id');
	}
        
        /*
        * this function is to get relation with icd codes
        */
       public function icdCode()
       {
           return $this->belongsTo(IcdCode::class, "icd_code_id", "id");
       }
       
        /*
        * this function is to get relation with icd codes
        */
        public function diseaseCategory()
        {
            return $this->belongsTo(DiseaseCategory::class, "disease_category_id", "id");
        }
}