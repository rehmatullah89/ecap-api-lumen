<?php

/*
 * This file is part of the IdeaToLife package.
 *
 * (c) Youssef Jradeh <youssef.jradeh@ideatolife.me>
 *
 */

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\WhoController;
use App\Models\Form;
use App\Models\FormType;
use App\Models\Parameter;
use App\Models\Site;
use App\Models\Question;
use Idea\Helpers\Paging;

class ParameterController extends WhoController {

    public $filePath = "sites/";

    protected $permissions = [
        "index" => ["code" => "sites", "action" => "read"],
        "one" => ["code" => "sites", "action" => "read"],
        "store" => ["code" => "sites", "action" => "write"],
        "update" => ["code" => "sites", "action" => "write"],
        "destroy" => ["code" => "sites", "action" => "write"],
    ];

    /**
     *
     * @return array
     */
    protected static function validationRules() {
        return [
            'index' => [
                'project_id' => 'required|exists:projects,id',
            ],
            'store' => [
                "name_en" => "required",
                "name_ar" => "sometimes",
                "name_ku" => "sometimes",
                'project_id' => 'required|exists:projects,id',
                'allow_edit' => 'boolean',
                'loop' => 'boolean',
            ],
            'update' => [
                'name_en'      => 'required',
                "name_ar" => "sometimes",
                "name_ku" => "sometimes",
                'project_id' => 'required|exists:projects,id',
                'allow_edit' => 'boolean',
                'loop' => 'boolean',
            ],
        ];
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index() {
        // if user is guest or super_guest then only show the sites to which he/she belongs
        if ($this->user && ($this->user->hasRole('guest') || $this->user->hasRole('super_guest'))) {
            $userId = $this->user->id;
            $query = Parameter::where("project_id", request("project_id"))
                ->whereHas(
                    'guestSites', function ($query) use ($userId) {
                    $query->where('user_id', $userId);
                })->get();
        } else {
            $query = Parameter::where("project_id", request("project_id"))->get();
        }

        return $this->successData($query);
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function one($id) {
        $parameter = Parameter::find($id);
        if (!$parameter) {
            return $this->failed("Invalid Form Type Id");
        }
        
        /*$typeId = (int) FormType::where("parameter_id", $id)->where("loop", 1)->pluck("id")->first();
        $result = \DB::select(
                "select q.name_en as question_en,q.name_ar as question_ar, q.name_ku as question_ku 
                from questions q, question_groups qg
                where q.question_group_id = qg.id AND qg.form_type_id = $typeId
                and qg.deleted_at IS NULL and q.deleted_at IS NULL Order By qg.id ASC LIMIT 1"
            );
        $parameter['question_en'] = @$result[0]->question_en;
        $parameter['question_ar'] = @$result[0]->question_ar;        
        $parameter['question_ku'] = @$result[0]->question_ku;*/
        
        return $this->successData($parameter);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function store() {
        
        $uniqueParam = Parameter::where("name_en", '=', request("name_en"))
                                ->where("project_id", '=', request("project_id"))
                                ->get();        
        if(count($uniqueParam) > 0)
            return $this->failed("The name has already been taken!", "failed");
        
        $parameterLevel = @request("parameter_level");
        
        if(($parameterLevel != "" && $parameterLevel != null) && Parameter::where("project_id", '=', request("project_id"))->where("parameter_level", $parameterLevel)->count() > 0)
            return $this->failed("Can not create parameter where level already exist.", "failed");
        
        $parameter = new Parameter();
        $parameter->project_id = request("project_id");
        $parameter->name_ar = request("name_ar");
        $parameter->name_en = request("name_en");
        $parameter->name_ku = request("name_ku");
        $parameter->parameter_type = request("parameter_type", "collection");
        $parameter->parameter_level = request("parameter_level");
        $parameter->question_en = request("question_en");
        $parameter->question_ar = request("question_ar");
        $parameter->question_ku = request("question_ku");
        $parameter->allow_edit = request("allow_edit", 1);
        $parameter->loop = request("loop", 0);
        $parameter->order = request("order", 1);
        $parameter->save();

        // get Project Forms
        $projectForms = Form::where('project_id', request("project_id"))->get();

        foreach ($projectForms as $projectForm) {
            $formType = new FormType();
            $formType->parameter_id = $parameter->id;
            $formType->name_en = $parameter->name_en;
            $formType->name_ar = $parameter->name_ar;
            $formType->name_ku = $parameter->name_ku;
            $formType->form_id = $projectForm->id;
            $formType->allow_edit = $parameter->allow_edit;
            $formType->parameter_type = $parameter->parameter_type;
            $formType->parameter_level = $parameter->parameter_level;
            $formType->loop = $parameter->loop;
            $formType->order = $parameter->order;
            $formType->question_id = request("question_id");
            $formType->question_en = request("question_en");
            $formType->question_ar = request("question_ar");
            $formType->question_ku = request("question_ku");
            $formType->save();
            
            $projectForm->is_mobile = 0;
            $projectForm->save();
            
            if(trim(request("question_en")) != "" && $formType->loop == 1)
                $this->addQuestionData($projectForm->id, $formType->id, request("question_en"), request("question_ar"), request("question_ku"));
        }

        return $this->successData($parameter);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function update($id) {
        $parameter = Parameter::find($id);
        if (!$parameter || $parameter->allow_edit == 0) {
            return $this->failed("Invalid Parameter");
        }

        $uniqueParam = Parameter::where("name_en", 'LIKE', request("name_en"))
                                ->where("project_id", request("project_id"))
                                ->where("id", '!=', $id)
                                ->get();
        
        if(count($uniqueParam) > 0)
            return $this->failed("The name has already been taken!", "failed");
        
        if(request("parameter_level") != ""  && Parameter::where("project_id", '=', request("project_id"))->where("parameter_level", request("parameter_level"))->where("id", '!=', $id)->count() > 0)
            return $this->failed("Can not update to parameter where level already exist.", "failed");
                
        $loop = request("loop", 0);
        
        $parameter->project_id = request("project_id");
        $parameter->name_ar = request("name_ar");
        $parameter->name_en = request("name_en");
        $parameter->name_ku = request("name_ku");
        
        if(!empty(request("parameter_type"))){
            $parameter->parameter_type = request("parameter_type", "collection");
            $parameter->parameter_level = request("parameter_level");
        }
        
        $parameter->question_ar = ($loop)?request("question_ar"):"";
        $parameter->question_en = ($loop)?request("question_en"):"";
        $parameter->question_ku = ($loop)?request("question_ku"):""; 
        $parameter->loop = $loop;
        $parameter->order = request("order", 1);
        $parameter->save();

        //update form types
        $formTypes = FormType::where('parameter_id', $parameter->id)->get();        
        
        foreach ($formTypes as $formType) {
            $formType->name_en = $parameter->name_en;
            $formType->name_ar = $parameter->name_ar;
            $formType->name_ku = $parameter->name_ku;
            $formType->allow_edit = $parameter->allow_edit;
            $formType->loop = $loop;
            $formType->order = $parameter->order;
            
            if(!empty(request("parameter_type"))){
                $formType->parameter_type = $parameter->parameter_type;
                $formType->parameter_level = $parameter->parameter_level;
            }
            
            $formType->question_en = ($loop)?request("question_en"):"";
            $formType->question_ar = ($loop)?request("question_ar"):"";
            $formType->question_ku = ($loop)?request("question_ku"):"";                
            $formType->save();
            
            $form = Form::find($formType->form_id);
            $form->is_mobile = 0;
            $form->save();

            $this->updateQuestionData($formType->form_id, $formType->id, request("question_en"), request("question_ar"), request("question_ku"), $loop);
        }

        return $this->successData($parameter);
    }

        /**
     * @param $formId,$typeId, $q_en, $q_ar, $q_ku
     *
     * @return void
     */
    public function addQuestionData($formId, $typeId, $q_en, $q_ar, $q_ku)
    {
        //create form category for sites
        $category = new \App\Models\FormCategory();
        $category->name_en = 'Question Information Category';
        $category->name_ar = 'Question Information Category';
        $category->name_ku = 'Question Information Category';
        $category->form_type_id = $typeId;
        $category->form_id = $formId;
        $category->order = 1;
        $category->loop = 1;
        $category->save();
        
        //question group
        $group = new \App\Models\QuestionGroup();
        $group->name = 'Question Information Group';
        $group->form_id = $formId;
        $group->form_type_id = $typeId;
        $group->form_category_id = $category->id;
        $group->order_value = 1;
        $group->root_group = 1;
        $group->save();
        
        //name of the question
        $question = new \App\Models\Question();
        $question->name_en = $q_en;
        $question->name_ar = $q_ar;
        $question->name_ku = $q_ku;
        $question->question_code = $q_en;
        $question->required = 1;
        $question->order = 1;
        $question->response_type_id = 4;
        $question->form_id = $formId;
        $question->question_group_id = $group->id;
        $question->save();
        
        $formType = FormType::find($typeId);
        $formType->question_id = $question->id;
        $formType->question_en = $question->name_en;
        $formType->question_ar = $question->name_ar;
        $formType->question_ku = $question->name_ku;
        $formType->save();
    }
    
        /**
     * @param $formId, $typeId, $q_en, $q_ar, $q_ku, $loop
     *
     * @return void
     */
    public function updateQuestionData($formId, $typeId, $q_en, $q_ar, $q_ku, $loop)
    {        
        $result = \DB::select(
            "select q.id as id,  qg.id as groupId, qg.form_category_id as categoryId
            from questions q, question_groups qg, form_categories fc
            where q.question_group_id = qg.id AND fc.id=qg.form_category_id AND qg.form_type_id = $typeId AND qg.form_id = $formId AND fc.loop = 1
            and qg.deleted_at IS NULL and q.deleted_at IS NULL Order By qg.id ASC LIMIT 1"
        );

        $questionId = (int)@$result[0]->id;
        $groupId = (int)@$result[0]->groupId;
        $categoryId = (int)@$result[0]->categoryId;
        
        if($loop == 1)
        {
            if($questionId > 0){
                $question = Question::find($questionId);
                $question->name_en = $q_en;
                $question->name_ar = $q_ar;
                $question->name_ku = $q_ku;
                $question->question_code = $q_en;
                $question->save();
                
                $formType = FormType::find($typeId);
                $formType->question_id = $question->id;
                $formType->question_en = $question->name_en;
                $formType->question_ar = $question->name_ar;
                $formType->question_ku = $question->name_ku;
                $formType->save();
            }
            else
                $this->addQuestionData($formId, $typeId, request("question_en"), request("question_ar"), request("question_ku"));
        }
        else{
            \App\Models\Question::where("id", $questionId)->delete();
            \App\Models\QuestionGroup::where("id", $groupId)->delete();
            \App\Models\FormCategory::where("id", $categoryId)->delete();
        }
            
    }
    
    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function destroy($id) 
    {
        try {
            if (!$parameter = Parameter::find($id)) {
                return $this->failed("Invalid Site");
            }
            
            //then delete the row from the database
            if(FormType::where("parameter_id", $id)->delete())
                $parameter->delete();
            else
                return $this->failed(FormType::where("parameter_id", $id)->delete());
            
            return $this->success('Parameter deleted');
        } catch (\Exception $e) {
            return $this->failed('destroy error');
        }
    }
}
