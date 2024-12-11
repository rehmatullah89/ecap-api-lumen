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
use App\Models\DiseaseBankQuestion;
use App\Models\DiseaseBank;
use App\Models\DiseaseDetail;

class DiseaseBankController extends WhoController {

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
                "appearance_name_en" => "required|unique:disease_bank,appearance_name_en",
                "icd_code_id" => "required",
                "disease_category_id" => "required",
                "disease_group" => "required",
                "disease_type" => "required",
            ],
            'update' => [
                "appearance_name_en" => "required",
                "icd_code_id" => "required",
                "disease_category_id" => "required",
                "disease_group" => "required",
                "disease_type" => "required",
            ],
        ];
    }

    /**
     * Display a listing of the Disease Bank.
     *
     * @return \Illuminate\Http\Response
     */
    public function index() {
        
        $searchQuery = !empty(request('query')) ? request('query') : "";
        $query = DiseaseBank::with(["icdCode", "diseaseCategory"]);
        
        if(!empty($searchQuery)){
            $query->whereHas(
                        'icdCode', function ($q) use ($searchQuery) {
                        $q->where('disease_name', 'LIKE', "%" . $searchQuery . "%");
                        $q->orWhere('code', 'LIKE', "%" . $searchQuery . "%");
                    }
                );
        }
        
        if(request("disease_category_id") && request("disease_category_id") != 'null')
            $query->where("disease_category_id", request("disease_category_id"));
        
        if(request("disease_group") && request("disease_group") != 'null')
            $query->where("disease_group", request("disease_group"));
        
        if(request("disease_type") && request("disease_type") != 'null')
            $query->where("disease_type", request("disease_type"));
        
        if(request("district_confirmation") > 0 && request("district_confirmation") != 'null')
            $query->whereNotNull("district_confirmation")->where("district_confirmation" , "!=", "");
        
        if(request("laboratory_confirmation") > 0 && request("laboratory_confirmation") != 'null')
            $query->whereNotNull("laboratory_confirmation")->where("laboratory_confirmation" , "!=", "");
        
        if(request("clinical_confirmation") > 0 && request("clinical_confirmation") != 'null')
            $query->whereNotNull("clinical_confirmation")->where("clinical_confirmation" , "!=", "");
        
        if(request("higher_confirmation") > 0 && request("higher_confirmation") != 'null')
            $query->whereNotNull("higher_confirmation")->where("higher_confirmation" , "!=", "");
        
        $query->orderBy("id", "desc");
        
        return $this->successData(new Paging($query));
    }

    /**
     * Display the specified Disease Bank.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function one($id) {
        $diseaseBank = DiseaseBank::with(["icdCode", "diseaseCategory"])->find($id);
        if (!$diseaseBank) {
            return $this->failed("Invalid refernce category Id");
        }
        
        $diseaseBankQuestions = DiseaseBankQuestion::where("disease_bank_id", $id)->pluck("question_id")->toArray(); 
        $diseaseBank['questions'] = \App\Models\QuestionBank::with("options")->whereIn("id", $diseaseBankQuestions)->get();
        $diseaseBank['questions_detail'] = $diseaseBankQuestions;
        
        return $this->successData($diseaseBank);
    }
    
    /**
     * Get Project Diseases.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function projectDiseases() {
        $list = request("list");
        if ((int)@request("project_id") == 0) {
            return $this->failed("Invalid Project Id");
        }                
        $diseaseAll = DiseaseDetail::where("project_id", request("project_id"))->pluck("disease_id","disease_id")->toArray(); 

        if($list)
            return $this->successData(DiseaseBank::whereIn("id", $diseaseAll)->get());
        else
            return $this->successData(new Paging(DiseaseBank::with(["icdCode","diseaseCategory"])->whereIn("id", $diseaseAll)));
    }

    /**
     * Store a newly created Disease Bank in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function store() {
        $diseaseBank = new DiseaseBank();
        $diseaseBank->disease_color = request("disease_color");
        $diseaseBank->icd_code_id = (int)request("icd_code_id");
        $diseaseBank->disease_category_id = (int)request("disease_category_id");
        $diseaseBank->disease_group = request("disease_group");
        $diseaseBank->disease_type = request("disease_type");
        $diseaseBank->appearance_name_en = request("appearance_name_en");
        $diseaseBank->appearance_name_ar = request("appearance_name_ar");
        $diseaseBank->appearance_name_ku = request("appearance_name_ku");
        $diseaseBank->district_confirmation = request("district_confirmation");
        $diseaseBank->laboratory_confirmation = request("laboratory_confirmation");
        $diseaseBank->clinical_confirmation = request("clinical_confirmation");
        $diseaseBank->higher_confirmation = request("higher_confirmation");        
        $diseaseBank->save();

        $questions = request('questions');        
        if(isset($questions) && !empty(@$questions))
        foreach($questions as $index => $questionId)
        {
            $diseaseBankQuestion = new DiseaseBankQuestion();
            $diseaseBankQuestion->question_id = $questionId;
            $diseaseBankQuestion->disease_bank_id = $diseaseBank->id;
            $diseaseBankQuestion->save( );
        }
            
        return $this->successData(DiseaseBank::with("questions.questionDetails")->find($diseaseBank->id));
    }

    /**
     * Update the specified Disease Bank in storage.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function update($id) {

        $diseaseBank = DiseaseBank::find($id);
        if (!$diseaseBank) {
            return $this->failed("Invalid Disease Bank");
        }
        
        if(DiseaseBank::where("appearance_name_en", "LIKE", request("appearance_name_en"))->where("id", "!=", $id)->count() > 0){
            return $this->failed("Disease Name already exists!");
        }

        if(DiseaseBank::where("appearance_name_en", "!=", request("appearance_name_en"))->where("id", $id)->count() > 0){
            $data = \DB::select("Select GROUP_CONCAT(id SEPARATOR ',') as ids from form_types where name_en LIKE 'Parameter Disease'");
            $data = explode(",", $data[0]->ids);
            
            if(count($data)>0){
                \DB::table('form_categories')->whereIn("form_type_id", $data)->where("name_en", "LIKE", $diseaseBank->appearance_name_en)->update(['name_en' => request("appearance_name_en")]);
            }
        }
        
        $diseaseBank->disease_color = request("disease_color");
        $diseaseBank->icd_code_id = (int)request("icd_code_id");
        $diseaseBank->disease_category_id = (int)request("disease_category_id");
        $diseaseBank->disease_group = request("disease_group");
        $diseaseBank->disease_type = request("disease_type");
        $diseaseBank->appearance_name_en = request("appearance_name_en");
        $diseaseBank->appearance_name_ar = request("appearance_name_ar");
        $diseaseBank->appearance_name_ku = request("appearance_name_ku");
        $diseaseBank->district_confirmation = request("district_confirmation");
        $diseaseBank->laboratory_confirmation = request("laboratory_confirmation");
        $diseaseBank->clinical_confirmation = request("clinical_confirmation");
        $diseaseBank->higher_confirmation = request("higher_confirmation");  
        $diseaseBank->save();

        DiseaseBankQuestion::where("disease_bank_id", $id)->delete();
        
        $questions = request('questions');
        
        if(isset($questions) && !empty(@$questions))
        foreach($questions as $index => $questionId)
        {
            $diseaseBankQuestion = new DiseaseBankQuestion();
            $diseaseBankQuestion->question_id = $questionId;
            $diseaseBankQuestion->disease_bank_id = $id;
            $diseaseBankQuestion->save( );
        }
        
        
            
        return $this->successData(DiseaseBank::with("questions.questionDetails")->find($id));
    }
    
    /**
     * Remove the specified Disease Bank from storage.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function destroy($id) {
        try {
            if (!$diseaseBank = DiseaseBank::find($id)) {
                return $this->failed("Invalid Disease Bank Item");
            }

            DiseaseBankQuestion::where("disease_bank_id", $id)->delete();
            //then delete the row from the database
            $diseaseBank->delete();

            return $this->success('Disease Bank Item Deleted');
        } catch (\Exception $e) {
            return $this->failed('destroy error');
        }
    }
}
