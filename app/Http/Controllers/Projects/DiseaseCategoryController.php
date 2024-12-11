<?php

/*
 *
 * (c) Rehmat Ullah <rehmatullah.bhatti@ideatolife.me>
 *
 */

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\WhoController;
use Idea\Helpers\Paging;
use App\Models\Question;
use App\Models\CategoryQuestion;
use App\Models\DiseaseCategory;

class DiseaseCategoryController extends WhoController {

    public $filePath = "sites/";

    protected $permissions = [
        "index" =>   ["code" => "category_library", "action" => "read"],
        "one" =>     ["code" => "category_library", "action" => "read"],
        "store" =>   ["code" => "category_library", "action" => "write"],
        "update" =>  ["code" => "category_library", "action" => "write"],
        "destroy" => ["code" => "category_library", "action" => "write"],
    ];

    /**
     *
     * @return array
     */
    protected static function validationRules() {
        return [
            'store' => [
                "category_en" => "required|unique:disease_categories,category_en",
                "category_ar" => "sometimes",
                "category_ku" => "sometimes",
            ],
            'update' => [
                "category_en" => "required",
                "category_ar" => "sometimes",
                "category_ku" => "sometimes",
            ],
        ];
    }

    /**
     * Display a listing of the disease category.
     *
     * @return \Illuminate\Http\Response
     */
    public function index() {
        
        $searchQuery = !empty(request('query')) ? request('query') : "";
        $query = DiseaseCategory::where(function ($q) use ($searchQuery) {
            $q->where('category_en', 'LIKE', "%" . $searchQuery . "%");
            $q->orWhere('category_ar', 'LIKE', "%" . $searchQuery . "%");
            $q->orWhere('category_ku', 'LIKE', "%" . $searchQuery . "%");
        })->orderBy("id", "desc");
        
        return $this->successData(new Paging($query));
    }

    /**
     * Display the specified disease category.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function one($id) {
        $diseaseCategory = DiseaseCategory::find($id);
        if (!$diseaseCategory) {
            return $this->failed("Invalid disease category Id");
        }
        
        return $this->successData($diseaseCategory);
    }
    
     /**
     * Display the list of disease by category for project.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function projectDiseases()
    {
        $projectId = (int)@request("project_id");        
        if ($projectId == 0) {
            return $this->failed("Invalid Project Id");
        }
                
        $categoryList = \App\Models\DiseaseDetail::where("project_id", request("project_id"))->pluck("disease_category_id","disease_category_id")->toArray(); 
        $diseaseList = \App\Models\DiseaseDetail::where("project_id", request("project_id"))->pluck("disease_id","disease_id")->toArray(); 
        $dataList = \App\Models\DiseaseCategory::with("diseases")->whereIn("id", $categoryList)->orderBy("id")->get();
        
        foreach($dataList as $data){
            
            if(isset($data->diseases)){
                foreach($data->diseases as $disease)
                {
                    if(in_array($disease->id, $diseaseList))
                        $disease->checked = true;
                    else 
                        $disease->checked = false;                     
                }
            }
        }

        return $this->successData($dataList);
    }

    /**
     * Store a newly created disease category in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function store() {
        $diseaseCategory = new DiseaseCategory();
        $diseaseCategory->category_en = request("category_en");
        $diseaseCategory->category_ar = request("category_ar");
        $diseaseCategory->category_ku = request("category_ku");
        $diseaseCategory->save();
            
        return $this->successData($diseaseCategory);
    }

    /**
     * Update the specified disease category in storage.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function update($id) {

        $diseaseCategory = DiseaseCategory::find($id);
        if (!$diseaseCategory) {
            return $this->failed("Invalid Disease Category");
        }
        
        $diseaseCategory->category_en = request("category_en");
        $diseaseCategory->category_ar = request("category_ar");
        $diseaseCategory->category_ku = request("category_ku");
        $diseaseCategory->save();
  
        return $this->successData($diseaseCategory);
    }
    
    /**
     * Remove the specified disease category from storage.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function destroy($id) {
        try {
            
            if (!$diseaseCategory = DiseaseCategory::find($id)) {
                return $this->failed("Invalid Disease Category");
            }

            $diseaseCategory->delete();
            return $this->success('Disease Category deleted');
            
        } catch (\Exception $e) {
            return $this->failed('destroy error');
        }
    }
}
