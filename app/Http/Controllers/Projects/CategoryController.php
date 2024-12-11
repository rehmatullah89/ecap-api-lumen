<?php

/*
 * This file is part of the IdeaToLife package.
 *
 * (c) Youssef Jradeh <youssef.jradeh@ideatolife.me>
 *
 */

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\WhoController;
use App\Models\FormCategory;
use Idea\Helpers\Paging;

class CategoryController extends WhoController {

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
                'form_type_id' => 'required|exists:form_types,id',
            ],
            'store' => [
                "name_en" => "required",
                "name_ar" => "sometimes",
                "name_ku" => "sometimes",
                'form_id' => 'required|exists:forms,id',
                'form_type_id' => 'required|exists:form_types,id',
                'all_question_required' => 'boolean',
                'all_question_optional' => 'boolean',
            ],
            'update' => [
                "name_en" => "required",
                "name_ar" => "sometimes",
                "name_ku" => "sometimes",
                'form_id' => 'required|exists:forms,id',
                'form_type_id' => 'required|exists:form_types,id',
                'all_question_required' => 'boolean',
                'all_question_optional' => 'boolean',
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
            $query = FormCategory::where("form_type_id", request("form_type_id"))
                ->whereHas(
                    'guestSites', function ($query) use ($userId) {
                    $query->where('user_id', $userId);
                });
        } else {
            $query = FormCategory::where("form_type_id", request("form_type_id"));
        }

        return $this->successData(new Paging($query));
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function one($id) {
        $formCategory = FormCategory::find($id);
        if (!$formCategory) {
            return $this->failed("Invalid form category Id");
        }
        return $this->successData($formCategory);
    }
    
    /**
     * Display the category questions
     *
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function categoryQuestions() {
        $diseaseId = request("disease_id");
        $projectId = request("project_id");
        
        $form = \App\Models\Form::where("project_id", $projectId)->first();
        $disease = \App\Models\DiseaseBank::where("id", $diseaseId)->first();
        $formCategory = FormCategory::where("form_id", $form->id)->where("name_en", "LIKE", $disease->appearance_name_en)->first();
        
        if (!$formCategory) {
            return $this->failed("Invalid disease/ category Id");
        }
        
        $formTypeId = (int)@$formCategory->form_type_id;
        $formData = \App\Types\FormType::getForm($form->id);
        $tempFormData['id'] = $formData['id'];
        $tempFormData['project_id'] = $formData['project_id'];
        $tempFormData['is_mobile'] = $formData['is_mobile'];
        
        foreach ($formData->types as $type){
            if($type->name_en != 'Parameter Disease')
                $tempFormData['types'][] = $type;
        }
        
        $FormTypeData = \App\Models\FormType::find($formTypeId);
        $FormTypeData['categories'] = FormCategory::where("id", $formCategory->id)->with("groups.questions")->get();
        
        array_unshift($tempFormData['types'], $FormTypeData);             
        
        return $this->successData($tempFormData);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function store() {
        $formCategory = new FormCategory();
        $formCategory->form_id = request("form_id");
        $formCategory->form_type_id = request("form_type_id");
        $formCategory->name_ar = request("name_ar", '');
        $formCategory->name_en = request("name_en");
        $formCategory->name_ku = request("name_ku", '');
        $formCategory->all_question_required = request("all_question_required", 0);
        $formCategory->all_question_optional = request("all_question_optional", 0);
        $formCategory->order = request("order", 1);
        $formCategory->save();

        $form = Form::find($formCategory->form_id);
        $form->is_mobile = 0;
        $form->save();
            
        return $this->successData($formCategory);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function update($id) {

        $formCategory = FormCategory::find($id);
        if (!$formCategory) {
            return $this->failed("Invalid Form Category");
        }
        
        $type = \App\Models\FormType::find($formCategory->form_type_id); 
        if($type->name_en == 'Parameter Disease' && $formCategory->name_en != request("name_en"))
            return $this->failed("Can not Update Category Name for Disease Parameter");

        $formCategory->form_id = request("form_id");
        $formCategory->form_type_id = request("form_type_id");
        $formCategory->name_ar = request("name_ar", '');
        $formCategory->name_en = request("name_en");
        $formCategory->name_ku = request("name_ku", '');
        $formCategory->all_question_required = request("all_question_required", 0);
        $formCategory->all_question_optional = request("all_question_optional", 0);
        $formCategory->order = request("order", 1);
        $formCategory->save();

        $form = Form::find($formCategory->form_id);
        $form->is_mobile = 0;
        $form->save();
        
        return $this->successData($formCategory);
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
        
        $referenceCategory = \App\Models\CategoryReference::find($categoryId);
        if (!$referenceCategory) {
            return $this->failed("Invalid Reference Category");
        }
        
        if(FormCategory::where("name_en", "LIKE", $referenceCategory->category_en)->where("form_id", request("form_id"))->where("form_type_id", request("form_type_id"))->count() > 0)
            return $this->failed("Specified category has already been imported!", "failed");
            
        $category = new FormCategory();
        $category->form_id = request("form_id");
        $category->form_type_id = request("form_type_id");
        $category->name_en = $referenceCategory->category_en;
        $category->name_ar = ($referenceCategory->category_ar == ""?$referenceCategory->category_en:$referenceCategory->category_ar);
        $category->name_ku = ($referenceCategory->category_ku == ""?$referenceCategory->category_en:$referenceCategory->category_ku);
        $category->save();
        
        $categoryQuestions = \App\Models\CategoryQuestion::where("category_id", $categoryId)->pluck("question_id","question_id")->toArray();
        $questions = \App\Models\QuestionBank::whereIn("id", $categoryQuestions)->get();
        
        if(!empty($questions) && count($questions) > 0)
        {
            $group = new \App\Models\QuestionGroup();
            $group->form_id = request("form_id");
            $group->form_type_id = request("form_type_id");
            $group->form_category_id = $category->id;
            $group->name = "Group {$referenceCategory->category_en}";
            $group->order_value = 0;
            $group->root_group = 1;
            //$group->parent_group = 1;
            $group->save();
            
            foreach($questions as $key => $questionBank)
            {//echo "<pre>";print_r($questionBank);exit;
                $question                   = new \App\Models\Question();         
                $question->name_en          = $questionBank->name_en;
                $question->name_ar          = $questionBank->name_ar;
                $question->name_ku          = $questionBank->name_ku;
                $question->form_id          = request("form_id");
                $question->question_code    = $questionBank->question_code;
                $question->consent          = $questionBank->consent;
                $question->mobile_consent   = $questionBank->mobile_consent;
                $question->required         = ($questionBank->required == "")?0:$questionBank->required;
                $question->multiple         = ($questionBank->multiple == "")?0:$questionBank->multiple;
                $question->setting          = json_encode($questionBank->setting);        
                $question->order            = ($questionBank->order == "")?0:$questionBank->order;
                $question->question_number  = ($questionBank->question_number == "")?"":$questionBank->question_number;
                $question->response_type_id = ($questionBank->response_type_id == "")?0:$questionBank->response_type_id;
                $question->question_group_id = $group->id;                
                $question->save();
              
                $questionBankOptions = \App\Models\QuestionBankOption::where("question_id",$questionBank->id)->get();

                if($questionBankOptions)
                {
                    foreach($questionBankOptions as $id => $optionData)
                    {
                        $questionOptions                 = new \App\Models\QuestionOption(); 
                        $questionOptions->name_en        = $optionData['name_en'];
                        $questionOptions->name_ar        = $optionData['name_ar'];
                        $questionOptions->name_ku        = $optionData['name_ku'];
                        $questionOptions->question_id    = $question->id;
                        $questionOptions->stop_collect   = (int)@$optionData['stop_collect'];
                        $questionOptions->save();        
                        
                        if($questionOptions->config_id == 0 || $questionOptions->config_id == null){
                            $questionOptions->config_id = $questionOptions->id;
                            $questionOptions->save();
                        }
                    }            
                }                
            }
        }
        
        //$form = \App\Types\FormType::getForm(request("form_id"));
        $form = \App\Models\Form::find(request("form_id"));
        $form->is_mobile = 0;
        $form->save();

        return $this->successData(FormCategory::with("groups.questions.options")->find($category->id));
    }
    
    /**
     * Export the specified resource in storage.
     *
     * @param int $categoryId
     *
     * @return \Illuminate\Http\Response
     */
    public function exportCategory()
    {
        $categoryId = request("category_id");
        
        //categories data
        $categoryData[] = array(0=>"id",1=>"Category Name English",2=>"Category Name Arabic",3=>"Category Name in Kurdish");
        $categories = \App\Models\FormCategory::where("id", $categoryId)->get();
        foreach($categories as $key=>$category){
            array_push($categoryData, array(1=>$category->id,2=>$category->name_en,3=>$category->name_ar,4=>$category->name_ku));
        }
        
        //Questions data
        $questionIds = [];
        $questionData[] = array(0=>"Question Id",1=>"Question in English",2=>"Question in Arabic",3=>"Question in Kurdish",4=>"Question Code",5=>"Consent",6=>"Mobile Consent",7=>"Required",8=>"Setting",9=>"Multiple",10=>"Order Sequence",11=>"Question Number",12=>"Response Type");
        $categoryGroups = \App\Models\QuestionGroup::where("form_category_id", $categoryId)->pluck("id")->toArray();
        $questions = \App\Models\Question::whereIn("question_group_id", $categoryGroups)->get();
        $responseTypes = \App\Models\QuestionResponseType::pluck("name","id")->toArray();
        
        foreach($questions as $key=>$question){
            $questionIds[] = $question->id;
            array_push($questionData, array(0=>$question->id,1=>$question->name_en,2=>$question->name_ar,3=>$question->name_ku,4=>$question->question_code,5=>$question->consent,6=>$question->mobile_consent,7=>$question->order,8=>json_encode($question->setting),9=>($question->multiple == 1?'Yes':'No'),10=>$question->order,11=>$question->question_number,12=>$responseTypes[$question->response_type_id]));
        }
        
        //question option data
        $optionData[] = array(0=>"Question Id",1=>"Option Id", 2=>"Option Name in English",3=>"Option Name in Arabic",4=>"Option Name in Kurdish",5=>"Order Sequence Number",6=>"Stop Collecting");
        $questionOptions = \App\Models\QuestionOption::whereIn("question_id", $questionIds)->get();
        foreach($questionOptions as $key=>$option){
            array_push($optionData, array(0=>$option->question_id,1=>$option->id,2=>$option->name_en,3=>$option->name_ar,4=>$option->name_ku,5=>$option->order_value,6=>($option->stop_collect == 1?'Yes':'No')));
        }
        
        //setting options data
        $settingOptionData[] = array(0=>"Question Id",1=>"Guide in English", 2=>"Guide in Arabic",3=>"Guide in Kurdish",4=>"Note in English",5=>"Note in Arabic",6=>"Note in Kurdish");
        $settingOptions = \App\Models\QuestionSettingOptions::whereIn("question_id", $questionIds)->get();
        foreach($settingOptions as $key=>$option){
            array_push($settingOptionData, array(0=>$option->question_id,1=>$option->guide_en,2=>$option->guide_ar,3=>$option->guide_ku,4=>$option->note_en,5=>$option->note_ar,6=>$option->note_ku));
        }
        
        //setting appearance data
        $settingAppearanceData[] = array(0=>"Question Id",1=>"Font Type", 2=>"Color",3=>"Highlight",4=>"Position to Show",5=>"If Text need to Captilize",6=>"Font Style");
        $settingAppearances = \App\Models\QuestionSettingAppearance::whereIn("question_id", $questionIds)->get();
        foreach($settingAppearances as $key=>$appearance){
            array_push($settingAppearanceData, array(0=>$appearance->question_id,1=>$appearance->font,2=>$appearance->color,3=>$appearance->highlight,4=>$appearance->positioning,5=>$appearance->capitalization,6=>$appearance->font_style));
        }

        // exporting the excel sheet with the resepctive data
        \Excel::create(
           'ExportCategory', function ($excel) use ($categoryData, $questionData, $optionData, $settingOptionData, $settingAppearanceData) {

                // Set the title
                $excel->setTitle('Category Data');
            
                // Chain the setters
                $excel->setCreator('IdeatoLife')
                    ->setCompany('IdeatoLife');
            
                // creating the sheet and filling it with categories data
                $excel->sheet(
                    'Category', function ($sheet) use ($categoryData) {
                        $sheet->rows($categoryData);                        
                    }
                );
                
                // creating the sheet and filling it with questions data
                $excel->sheet(
                    'Questions', function ($sheet) use ($questionData) {
                        $sheet->rows($questionData);                        
                    }
                );
                
                // creating the sheet and filling it with questions option data
                $excel->sheet(
                    'QuestionOptions', function ($sheet) use ($optionData) {
                        $sheet->rows($optionData);                        
                    }
                );
                
                // creating the sheet and filling it with setting Option data
                $excel->sheet(
                    'SettingOptions', function ($sheet) use ($settingOptionData) {
                        $sheet->rows($settingOptionData);                        
                    }
                );
                
                // creating the sheet and filling it with setting Option data
                $excel->sheet(
                    'SettingAppearance', function ($sheet) use ($settingAppearanceData) {
                        $sheet->rows($settingAppearanceData);                        
                    }
                );
                
            }
        )->store('xls', "/tmp");
        
        //download excel file
        $file = "/tmp/ExportCategory.xls";
        return response()->download($file, "ExportCategory_{$categoryId}.xls", ['Content-Type: application/vnd.ms-excel']);
        
    }
    
    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function destroy($id) {
        try {
            if (!$formCategory = FormCategory::find($id)) {
                return $this->failed("Invalid Form Category");
            }
            
            $form = Form::find($formCategory->form_id);
            $form->is_mobile = 0;
            $form->save();

            if($form){
                $disease = \App\Models\DiseaseBank::where("appearance_name_en", "LIKE", $formCategory->name_en)->first();
                
                if($disease)
                    DiseaseDetail::where("project_id", $form->project_id)->where("disease_id", $disease->id)->delete(); 
            }
            
            //then delete the row from the database
            $formCategory->delete();
            

            
            
            return $this->success('Form Category deleted');
        } catch (\Exception $e) {
            return $this->failed('destroy error');
        }
    }
}
