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
use App\Models\CategoryReference;

class CategoryReferenceController extends WhoController {

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
                "category_en" => "required|unique:category_references,category_en",
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
     * Display a listing of the reference category.
     *
     * @return \Illuminate\Http\Response
     */
    public function index() {
        
        $searchQuery = !empty(request('query')) ? request('query') : "";
        $query = CategoryReference::withCount('questions')->where(function ($q) use ($searchQuery) {
            $q->where('category_en', 'LIKE', "%" . $searchQuery . "%");
            $q->orWhere('category_ar', 'LIKE', "%" . $searchQuery . "%");
            $q->orWhere('category_ku', 'LIKE', "%" . $searchQuery . "%");
        })->orderBy("id", "desc");
        
        return $this->successData(new Paging($query));
    }

    /**
     * Display the specified reference category.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function one($id) {
        $referenceCategory = CategoryReference::find($id);
        if (!$referenceCategory) {
            return $this->failed("Invalid refernce category Id");
        }
        
        $categoryQuestions = CategoryQuestion::where("category_id", $id)->pluck("question_id")->toArray(); 
        $referenceCategory['questions'] = \App\Models\QuestionBank::with("options")->whereIn("id", $categoryQuestions)->get();
        $referenceCategory['questions_detail'] = $categoryQuestions;
        
        return $this->successData($referenceCategory);
    }

    /**
     * Store a newly created reference category in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function store() {
        $referenceCategory = new CategoryReference();
        $referenceCategory->category_en = request("category_en");
        $referenceCategory->category_ar = request("category_ar");
        $referenceCategory->category_ku = request("category_ku");
        $referenceCategory->save();

        $questions = request('questions');//json_decode(, true);
        
        if(isset($questions) && !empty(@$questions))
        foreach($questions as $index => $questionId)
        {
            $categoryQuestion = new CategoryQuestion();
            $categoryQuestion->question_id = $questionId;
            $categoryQuestion->category_id = $referenceCategory->id;
            $categoryQuestion->save( );
        }
            
        return $this->successData(CategoryReference::with("questions.questionDetails")->find($referenceCategory->id));
    }

    /**
     * Update the specified reference category in storage.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function update($id) {

        $referenceCategory = CategoryReference::find($id);
        if (!$referenceCategory) {
            return $this->failed("Invalid Reference Category");
        }
        
        $referenceCategory->category_en = request("category_en");
        $referenceCategory->category_ar = request("category_ar");
        $referenceCategory->category_ku = request("category_ku");
        $referenceCategory->save();

        CategoryQuestion::where("category_id", $id)->delete();
        
        $questions = request('questions'); //json_decode(request('questions'), true);
        
        if(isset($questions) && !empty(@$questions))
        foreach($questions as $index => $questionId)
        {
            $categoryQuestion = new CategoryQuestion();
            $categoryQuestion->question_id = $questionId;
            $categoryQuestion->category_id = $id;
            $categoryQuestion->save( );
        }
            
        return $this->successData(CategoryReference::with(["questions.questionDetails.options"])->find($id));
    }
    
    /**
     * Import the specified resource in storage.
     *
     * @param int $categoryId
     *
     * @return \Illuminate\Http\Response
     */
    public function importCategory() 
    {
        $categoryId = request("category_id");
        $projectId  = request("project_id");
        
        $category = \App\Models\FormCategory::find($categoryId);
        if (!$category) {
            return $this->failed("Invalid Category");
        }
        
        $existCategory = (int)CategoryReference::where("category_en", "LIKE", $category->name_en)->count();
        
        if($existCategory > 0)
            return $this->failed("Reference Category Already Exist.");
        
        $referenceCategory = new CategoryReference();
        $referenceCategory->category_en = ($category->name_en == ""?"temp{$categoryId}":$category->name_en);
        $referenceCategory->category_ar = $category->name_ar;
        $referenceCategory->category_ku = $category->name_ku;
        $referenceCategory->save();
        
        $groups = \App\Models\QuestionGroup::where("form_category_id", $categoryId)->pluck("id","id")->toArray();
        $questions = Question::whereIn("question_group_id", $groups)->get();
        
        if(!empty($questions) && count($questions) > 0)
        {
            foreach($questions as $question)
            {
                $questionBank                   = new \App\Models\QuestionBank();         
                $questionBank->name_en          = $question->name_en;
                $questionBank->name_ar          = $question->name_ar;
                $questionBank->name_ku          = $question->name_ku;
                $questionBank->question_code    = $question->question_code;
                $questionBank->consent          = $question->consent;
                $questionBank->mobile_consent   = $question->mobile_consent;
                $questionBank->required         = ($question->required == "")?0:$question->required;
                $questionBank->multiple         = ($question->multiple == "")?0:$question->multiple;
                $questionBank->setting          = ($question->setting == "")?"[]":json_encode($question->setting);        
                $questionBank->order            = ($question->order == "")?0:$question->order;
                $questionBank->question_number  = ($question->question_number == "")?"":$question->question_number;
                $questionBank->response_type_id = ($question->response_type_id == "")?0:$question->response_type_id;
                $questionBank->save();
                
                $categoryQuestion = new CategoryQuestion();
                $categoryQuestion->question_id = $questionBank->id;
                $categoryQuestion->category_id = $referenceCategory->id;
                $categoryQuestion->save( );

                $questionOptions = \App\Models\QuestionOption::where("question_id",$question->id)->get();

                if(isset($questionOptions) && count($questionOptions) >0)
                {
                    foreach($questionOptions as $id => $optionData)
                    {
                        $questionBankOption                 = new \App\Models\QuestionBankOption(); 
                        $questionBankOption->name_en        = $optionData['name_en'];
                        $questionBankOption->name_ar        = $optionData['name_ar'];
                        $questionBankOption->name_ku        = $optionData['name_ku'];
                        $questionBankOption->question_id    = $questionBank->id;
                        $questionBankOption->stop_collect   = (int)@$optionData['stop_collect'];
                        $questionBankOption->save();                 
                    }            
                }                
            }
        }
        
        return $this->successData(CategoryReference::with("questions.questionDetails")->find($referenceCategory->id));
    }

    /**
     * Remove the specified reference category from storage.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function destroy($id) {
        try {
            if (!$referenceCategory = CategoryReference::find($id)) {
                return $this->failed("Invalid Reference Category");
            }

            CategoryQuestion::where("category_id", $id)->delete();
            //then delete the row from the database
            $referenceCategory->delete();

            return $this->success('Reference Category deleted');
        } catch (\Exception $e) {
            return $this->failed('destroy error');
        }
    }
}
