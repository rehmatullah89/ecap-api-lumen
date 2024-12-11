<?php

/*
 * This file is part of the IdeaToLife package.
 *
 * (c) Youssef Jradeh <youssef.jradeh@ideatolife.me>
 *
 */

namespace App\Http\Controllers\Forms;

use Excel;
use App\Http\Controllers\WhoController;
use App\Models\Form;
use App\Models\FormCategory;
use App\Models\FormType;
use App\Models\Project;
use App\Models\Question;
use App\Models\Parameter;
use App\Models\QuestionGroup;
use App\Models\QuestionGroupCondition;
use App\Models\QuestionOption;
use App\Models\QuestionAnswer;
use App\Models\SkipLogicQuestion;
use App\Models\SkipLogicQuestionDetail;
use App\Models\QuestionSettingOptions;
use App\Models\QuestionSettingAppearance;
use App\Models\QuestionAssignment;
use App\Models\PushToMobile;

class FormController extends WhoController {

    public $generatedIds = [];//used to link to new question in a new group condition where question is not saved yet

    public $typesToDoNotDelete = [];//types that needs to stay (updated or newly created), all others should be removed

    private $optionsToDoNotDelete = [];//options that needs to stay (updated or newly created), all others should be removed

    public $categoriesToDoNotDelete = [];//categories that needs to stay (updated or newly created), all others should be removed

    public $groupsToDoNotDelete = [];//groups that needs to stay (updated or newly created), all others should be removed

    public $questionsToDoNotDelete = [];//questions that needs to stay (updated or newly created), all others should be removed

    protected $permissions = [
        "one" => ["code" => "forms", "action" => "read"],
        "byProject" => ["code" => "forms", "action" => "read"],
        "store" => ["code" => "forms", "action" => "write"],
    ];

    /**
     *
     * @return array
     */
    protected static function validationRules() {
        return [
            'store' => [
                'id' => 'required|exists:forms,id',
                'project_id' => 'required|exists:projects,id',
                //more validation should be added here, but I am just afraid to break the exist function on the client site
            ],
            'moveGroupToOtherParent' => [
                'group_id' => 'required|exists:question_groups,id',
                'to_parent_id' => 'required|exists:question_groups,id',
            ],
            'moveQuestionToAnotherGroup' => [
                'question_id' => 'required|exists:questions,id',
                'group_id' => 'required|exists:question_groups,id',
            ],
        ];
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function one($id) {

        $form = \App\Types\FormType::getForm($id);
        if (!$form) {
            return $this->failed("Invalid Form Id");
        }
        return $this->successData($form);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function byProject($id) {

        $project = Project::with("form")->find($id);
        if (!$project || empty($project->form)) {
            return $this->failed("Invalid Project");
        }

        $form = \App\Types\FormType::getForm($project->form->id);
        if (!$form) {
            return $this->failed("Invalid Form Id");
        }
        return $this->successData($form);
    }

    /**
     * This is actually the most important function in the system.
     * Here we are actually saving the form and all its relations
     * like group, question , answer and conditions
     */
    public function store() {
        $data = $this->request->all();
        $form = Form::find($data['id']);

        //only one form is allowed for now
        if (!$form) {
            return $this->failed("Invalid Form ID");
        }
        //
        $form->is_mobile = 0;
        $form->project_id = $data['project_id'];
        $form->save();

        if (empty($data['types'])) {
            return $this->success();
        }

        foreach ($data['types'] AS $t) {

            //$type = $this->issetAndNumeric($t, 'id') ? FormType::find($t['id']) : new FormType();
            //$type = $type ?: new FormType();
            
            $type = $this->issetAndNumeric($t, 'id') ? FormType::find($t['id']) : "";

            if(!empty($type))
            {
                $type->name_en = $t['name_en'];
                $type->name_ar = $t['name_ar'];
                $type->name_ku = $t['name_ku'];

                $type->form_id = $form->id;
                $type->save();
                $this->typesToDoNotDelete[] = $type->id;

                if (empty($t['categories'])) {
                    continue;
                }
                foreach ($t['categories'] AS $key => $c) {

                    $category = $this->issetAndNumeric($c, 'id') ? FormCategory::find($c['id']) : new FormCategory();
                    $category = $category ?: new FormCategory();
                    $category->name_en = $c['name_en'];
                    $category->name_ar = $c['name_ar'];
                    $category->name_ku = $c['name_ku'];
                    $category->all_question_required = $c['all_question_required'];
                    $category->all_question_optional = $c['all_question_optional'];
                    $category->form_type_id = $type->id;
                    $category->form_id = $form->id;
                    $category->order = $key;
                    $category->save();
                    $this->categoriesToDoNotDelete[] = $category->id;

                    if (empty($c['groups'])) {
                        continue;
                    }
                    $this->saveGroupQuestion($c['groups'], $form->id, $type->id, $category->id, $form->project_id);
                }
            }
            else
                continue;
        }

        //delete unneeded types
        FormType::where("form_id", $form->id)
            ->whereNotIn('id', $this->typesToDoNotDelete)
            ->delete();
        FormCategory::where("form_id", $form->id)
            ->whereNotIn('id', $this->categoriesToDoNotDelete)
            ->delete();
        QuestionGroup::where("form_id", $form->id)
            ->whereNotIn('id', $this->groupsToDoNotDelete)
            ->delete();
        Question::where("form_id", $form->id)
            ->whereNotIn('id', $this->questionsToDoNotDelete)
            ->delete();

        QuestionGroup::rebuild();

        $form = \App\Types\FormType::getForm($form->id);

        return $this->successData($form);
    }

    private function saveGroupQuestion($groups, $formId, $typeId, $categoryId, $projectId, $parent = null) {

        foreach ($groups AS $groupData) {
            $group = $this->issetAndNumeric($groupData, 'id') ? QuestionGroup::find($groupData['id']) : new QuestionGroup();
            $group = $group ?: new QuestionGroup();

            //if by group id that belongs to other form sent by mistake
            //ignore it and consider it a new group
            if (!empty($group->id) && $group->form_id != $formId) {
                $group = new QuestionGroup();
            }

            //only set form_id,.. if new
            if (empty($group->id)) {
                $group->form_id = $formId;
                $group->form_type_id = $typeId;
                $group->form_category_id = $categoryId;
                $group->parent_group = $parent;
                $group->root_group = !$parent ? 1 : 0;
            }
            $group->name = $groupData['name'];
            $group->order_value = !empty($groupData['order']) ? $groupData['order'] : 0;
            $group->save();
            $this->groupsToDoNotDelete[] = $group->id;

            //Adding questions
            if (!empty($groupData['questions'])) {
                foreach ($groupData['questions'] AS $key => $questionData) {
                    
                    //$newQuestionFlag = $this->issetAndNumeric($questionData, 'id')?0:1;
                    //$question = ($newQuestionFlag == 0) ? Question::find($questionData['id']) : new Question();
                    $question = $this->issetAndNumeric($questionData, 'id') ? Question::find($questionData['id']) : new Question();
                    $question = $question ?: new Question();
                    $question->name_en = $this->checkValue($questionData, 'name_en');
                    $question->name_ar = $this->checkValue($questionData, 'name_ar');
                    $question->name_ku = $this->checkValue($questionData, 'name_ku');
                    $question->question_code = $this->checkValue($questionData, 'question_code');
                    $question->consent = $this->checkValue($questionData, 'consent');
                    $question->mobile_consent = $this->checkValue($questionData, 'mobile_consent');
                    $question->required = $this->checkValue($questionData, 'required', 0);
                    $question->setting = $this->checkJsonValue($questionData, 'setting');
                    $question->multiple = $this->checkValue($questionData, 'multiple');
                    $question->order = $key;
                    $question->question_number = $this->checkValue($questionData, 'question_number');
                    $question->response_type_id = $this->checkValue($questionData, 'response_type_id');
                    //only set form_id,.. if new
                    if (empty($question->id)) {
                        $question->form_id = $formId;
                        $question->question_group_id = $group->id;
                    }
                    $question->save();
                    $this->questionsToDoNotDelete[] = $question->id;

                    //save skip logic, only if its set
                    $this->saveSkipLogic($questionData['skip_logic'],  $projectId, $formId, $question->id);
                    
                    // save question assignment
                    if(isset($questionData['question_assignment']))
                    $this->saveQuestionAssignments($questionData['question_assignment'],  $projectId, $formId, $question->id);

                    //save quesion setting options
                    $this->saveQuestionSettingOptions($questionData['question_setting_options'],  $projectId, $formId, $question->id);

                    //save quesion setting appearance
                    $this->saveQuestionSettingAppearance($questionData['question_setting_appearance'],  $projectId, $formId, $question->id);

                    //if uuid
                    if (!empty($questionData['id']) && strpos($questionData['id'], "-") !== false) {
                        $this->generatedIds[$questionData['id']] = $question->id;
                    }

                    //adding options
                    if (!empty($questionData['options'])) {
                        foreach ($questionData['options'] AS $key => $ooptionData) {
                            $option = $this->issetAndNumeric($ooptionData, 'id') ? QuestionOption::find($ooptionData['id']) : new QuestionOption();
                            $option = $option ?$option: new QuestionOption();
                            
                            /*if($newQuestionFlag)
                                $option = new QuestionOption();
                            else
                                $option = $option ?: new QuestionOption();*/
                            
                            $option->name_en = $this->checkValue($ooptionData, 'name_en');
                            $option->name_ar = $this->checkValue($ooptionData, 'name_ar');
                            $option->name_ku = $this->checkValue($ooptionData, 'name_ku');
                            //$option->description = $this->checkValue($ooptionData, 'description');
                            $option->question_id = $question->id;
                            $option->order_value = $key;
                            $stopCollect = $this->checkValue($ooptionData, 'stop_collect', 0);
                            $option->stop_collect = $stopCollect ? 1 : 0;
                            $option->save();
                            
                            if($option->config_id == 0 || $option->config_id == null){
                                $option->config_id = $option->id;
                                $option->save();
                            }
                            
                            //pushing option ids new-created/existing
                            $this->optionsToDoNotDelete[] = $option->id;
                        }
                        
                        //this will delete only un-sent questions
                        if(!empty($this->optionsToDoNotDelete)){
                            QuestionOption::where("question_id", $question->id)
                            ->whereNotIn('id', $this->optionsToDoNotDelete)
                            ->delete();
                            
                            $this->optionsToDoNotDelete = [];
                        }
                    }
                    else
                        QuestionOption::where("question_id", $question->id)->delete();
                }
            }

            //adding conditions
            if (!empty($groupData['conditions'])) {
                $questionGroupConditionsToDoNotDelete = [];
                foreach ($groupData['conditions'] AS $c) {
                    $condition = $this->issetAndNumeric($c, 'id') ? QuestionGroupCondition::find($c['id']) : new QuestionGroupCondition();
                    $condition = $condition ?: new QuestionGroupCondition();
                    //only set form_id,.. if new
                    if (empty($condition->id)) {
                        $condition->question_group_id = $group->id;
                    }

                    $questionId = $this->checkValue($c, 'question_id');

                    //in case of uuid and it's a new question
                    if (strpos($questionId, '-') !== false && !empty($this->generatedIds[$questionId])) {
                        $condition->question_id = $this->generatedIds[$questionId];
                    } elseif (is_int($questionId)) {
                        $condition->question_id = $questionId;
                    }

                    if (!$condition->question_id) {
                        continue;
                    }
                    $condition->type = $this->checkValue($c, 'type', '=');
                    $condition->value = $this->checkValue($c, 'value', '');
                    $condition->max_value = $this->checkValue($c, 'max_value', '');
                    $condition->operation = $this->checkValue($c, 'operation', 'AND');
                    $condition->order_value = $this->checkValue($c, 'order_value', '0');

                    try {
                        $condition->save();
                        $questionGroupConditionsToDoNotDelete[] = $condition->id;
                    } catch (\Exception $e) {
                    }
                }
                QuestionGroupCondition::where("question_group_id", $group->id)
                    ->whereNotIn('id', $questionGroupConditionsToDoNotDelete)
                    ->delete();
            }

            if (!empty($groupData['children'])) {
                $this->saveGroupQuestion($groupData['children'], $formId, $typeId, $categoryId, $projectId, $group->id);
            }

        }
    }

    /**
     * the following method is used to save question assignment data
     *
     * @param [type] $assignmentData
     * @param [type] $projectId
     * @param [type] $formId
     * @param [type] $questionId
     * @return void
     */
    private function saveQuestionAssignments($assignmentData, $projectId, $formId, $questionId){
            QuestionAssignment::where('question_id', $questionId)->where('project_id', $projectId)->delete();
            $questionAssignment = new QuestionAssignment(); 
            $questionAssignment->clinic  = (int)@$assignmentData['clinic'];
            $questionAssignment->laboratory  = (int)@$assignmentData['laboratory'];
            $questionAssignment->verifier = (int)@$assignmentData['verifier'];
            $questionAssignment->higher_verifier = (int)@$assignmentData['higher_verifier'];
            $questionAssignment->data_collector = (int)@$assignmentData['data_collector'];
            $questionAssignment->project_id   = $projectId;
            $questionAssignment->form_id      = $formId;
            $questionAssignment->question_id    = $questionId;
            $questionAssignment->save( );            
    }
    
    /**
     * the following method is used to save skip logic to the database
     *
     * @param [type] $skipData
     * @param [type] $projectId
     * @param [type] $formId
     * @param [type] $questionId
     * @return void
     */
    private function saveSkipLogic($skipData, $projectId, $formId, $questionId){
        if(count($skipData) > 0)
        {
            $skipLogicQuestionsList = array();
            foreach($skipData as $key => $data)
            {            
                if($questionId == $data['question_id']){
                    return $this->failed("failed", "You cannot use the same questions in the SKIP LOGIC.");
                    exit();
                }
                
                $skipObj = SkipLogicQuestion::find(@$data['id']);
                
                $skipLogicQuestion = (!$skipObj || empty($skipObj->id)) ? new SkipLogicQuestion(): $skipObj; 
                $skipLogicQuestion->question_id  = $data['question_id'];
                $skipLogicQuestion->operator_id  = $data['operator_id'];
                $skipLogicQuestion->condition_id = $data['condition_id'];
                $skipLogicQuestion->project_id   = $projectId;
                $skipLogicQuestion->form_id      = $formId;
                $skipLogicQuestion->parent_id    = $questionId;
                $skipLogicQuestion->save( );

                $skipLogicQuestionsList[$skipLogicQuestion->id] = $skipLogicQuestion->id;
                
                if(isset($data['option_value_id']))
                {
                    $notToDeleteOptionValues = [];
                    //SkipLogicQuestionDetail::where('skip_logic_id', $skipLogicQuestion->id)->delete();
                    
                    if(@is_array($data['option_value_id'])){
                        foreach($data['option_value_id'] as $optionId)
                        {
                            $skipLogicQuestionDetail = SkipLogicQuestionDetail::where("skip_logic_id", $skipLogicQuestion->id)->where("option_value_id",$optionId)->first();
                            $skipLogicQuestionDetail = ($skipLogicQuestionDetail)?$skipLogicQuestionDetail : new SkipLogicQuestionDetail();
                            
                            $skipLogicQuestionDetail->skip_logic_id     = $skipLogicQuestion->id;
                            $skipLogicQuestionDetail->question_id       = $data['question_id'];
                            $skipLogicQuestionDetail->parent_id         = $questionId;
                            $skipLogicQuestionDetail->operator_id       = $data['operator_id'];
                            $skipLogicQuestionDetail->option_value_id   = $optionId;
                            $skipLogicQuestionDetail->option_value      = $data['option_value'];
                            $skipLogicQuestionDetail->save( );
                            
                            $notToDeleteOptionValues[$optionId] = $optionId;
                        }
                    }else{
                            $skipLogicQuestionDetail = SkipLogicQuestionDetail::where("skip_logic_id", $skipLogicQuestion->id)->where("option_value_id",@$data['option_value_id'])->first();
                            $skipLogicQuestionDetail = ($skipLogicQuestionDetail)?$skipLogicQuestionDetail : new SkipLogicQuestionDetail();
                        
                            $skipLogicQuestionDetail->skip_logic_id     = $skipLogicQuestion->id;
                            $skipLogicQuestionDetail->question_id       = $data['question_id'];
                            $skipLogicQuestionDetail->parent_id         = $questionId;
                            $skipLogicQuestionDetail->operator_id       = $data['operator_id'];
                            $skipLogicQuestionDetail->option_value_id   = @$data['option_value_id'];
                            $skipLogicQuestionDetail->option_value      = $data['option_value'];
                            $skipLogicQuestionDetail->save( );
                            
                            $notToDeleteOptionValues[@$data['option_value_id']] = @$data['option_value_id'];
                    }
                    
                    if(!empty($notToDeleteOptionValues)){
                        SkipLogicQuestionDetail::where('skip_logic_id', $skipLogicQuestion->id)->whereNotIn('option_value_id', $notToDeleteOptionValues)->delete();   
                    }
                }else if(isset($data['skip_logic_details']) && count($data['skip_logic_details']) == 0){
                    SkipLogicQuestionDetail::where('skip_logic_id', $skipLogicQuestion->id)->delete();
                }
            }
            
            if(!empty($skipLogicQuestionsList))
            {
                SkipLogicQuestionDetail::where('parent_id', $questionId)->whereNotIn('skip_logic_id', $skipLogicQuestionsList)->delete();                
                SkipLogicQuestion::where('parent_id', $questionId)->whereNotIn('id', $skipLogicQuestionsList)->delete();
            }
            
        }
        else
        {
                SkipLogicQuestionDetail::where('parent_id', $questionId)->delete();                
                SkipLogicQuestion::where('parent_id', $questionId)->delete();
        }
    }

    /**
     * the following method is used to save Question Setting Options
     *
     * @param [type] $optionData
     * @param [type] $projectId
     * @param [type] $formId
     * @param [type] $questionId
     * @return void
     */
    private function saveQuestionSettingOptions($optionData, $projectId, $formId, $questionId){
        QuestionSettingOptions::where('question_id', $questionId)->where("form_id", $formId)->delete();

        $questionOption = new QuestionSettingOptions();

        $questionOption->guide_en       = $optionData['guide_en'];
        $questionOption->guide_ar       = $optionData['guide_ar'];
        $questionOption->guide_ku       = $optionData['guide_ku'];
        $questionOption->note_en        = $optionData['note_en'];
        $questionOption->note_ar        = $optionData['note_ar'];
        $questionOption->note_ku        = $optionData['note_ku'];
        $questionOption->project_id     = $projectId;
        $questionOption->form_id        = $formId;
        $questionOption->question_id    = $questionId;
        $questionOption->save( );
    }

    /**
     * the following method is used to save Question Setting Appearance
     *
     * @param [type] $appearanceData
     * @param [type] $projectId
     * @param [type] $formId
     * @param [type] $questionId
     * @return void
     */
    private function saveQuestionSettingAppearance($appearanceData, $projectId, $formId, $questionId){
        QuestionSettingAppearance::where('question_id', $questionId)->where("form_id", $formId)->delete();

        $questionAppearance = new QuestionSettingAppearance();

        $questionAppearance->font           = $appearanceData['font'];
        $questionAppearance->color          = $appearanceData['color'];
        $questionAppearance->highlight      = $appearanceData['highlight'];
        $questionAppearance->positioning    = $appearanceData['positioning'];
        $questionAppearance->capitalization = $appearanceData['capitalization'];
        $questionAppearance->font_style     = $appearanceData['font_style'];
        $questionAppearance->project_id     = $projectId;
        $questionAppearance->form_id        = $formId;
        $questionAppearance->question_id    = $questionId;
        $questionAppearance->save( );
    }

    /**
     * Store category & Group
     *
     * @return \Illuminate\Http\Response
     */
    public function saveCategory() {
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

        $group                  = new QuestionGroup();
        $group->form_id         = request("form_id");
        $group->form_type_id    = request("form_type_id");
        $group->form_category_id= $formCategory->id;
        $group->root_group      = 1;
        $group->name            = request("name_en")." Group";
        $group->order_value     = 0;
        $group->save();
                
        return $this->successData($group);
    }
    
    /**
     * the following method is used to update Question
     *
     * @param [json] data
     *
     * @return form details
     */
    public function updateQuestion()
    {
        $questionData = request("data");

        if(!empty($questionData))
        {
            $formId    = @$questionData['form_id'];   
            $projectId= Form::where("id",$formId)->pluck("project_id")->first();
            $groupId  = @$questionData['question_group_id'];
            
            $question = $this->issetAndNumeric($questionData, 'id') ? Question::find($questionData['id']) : new Question();
            $question = $question ?: new Question();
            $question->name_en = $this->checkValue($questionData, 'name_en');
            $question->name_ar = $this->checkValue($questionData, 'name_ar');
            $question->name_ku = $this->checkValue($questionData, 'name_ku');
            $question->question_code = $this->checkValue($questionData, 'question_code');
            $question->consent = $this->checkValue($questionData, 'consent');
            $question->mobile_consent = $this->checkValue($questionData, 'mobile_consent');
            $question->required = $this->checkValue($questionData, 'required', 0);
            $question->setting = $this->checkJsonValue($questionData, 'setting');
            $question->multiple = $this->checkValue($questionData, 'multiple');
            $question->order = @$questionData['order'];
            $question->question_number = $this->checkValue($questionData, 'question_number');
            $question->response_type_id = $this->checkValue($questionData, 'response_type_id');
            //only set form_id,.. if new
            if (empty($question->id)) {
                $question->form_id = $formId;
                $question->question_group_id = $groupId;
            }
            $question->save();

            //save skip logic, only if its set
            $this->saveSkipLogic($questionData['skip_logic'],  $projectId, $formId, $question->id);

            // save question assignment
            if(isset($questionData['question_assignment']))
            $this->saveQuestionAssignments($questionData['question_assignment'],  $projectId, $formId, $question->id);
            
            //save quesion setting options
            $this->saveQuestionSettingOptions($questionData['question_setting_options'],  $projectId, $formId, $question->id);

            //save quesion setting appearance
            $this->saveQuestionSettingAppearance($questionData['question_setting_appearance'],  $projectId, $formId, $question->id);

            //adding options
            if (!empty($questionData['options'])) {

                foreach ($questionData['options'] AS $key => $ooptionData) {
                    $option = $this->issetAndNumeric($ooptionData, 'id') ? QuestionOption::find($ooptionData['id']) : new QuestionOption();
                    $option = $option ?: new QuestionOption();
                    $option->name_en = $this->checkValue($ooptionData, 'name_en');
                    $option->name_ar = $this->checkValue($ooptionData, 'name_ar');
                    $option->name_ku = $this->checkValue($ooptionData, 'name_ku');
                    //$option->description = $this->checkValue($ooptionData, 'description');
                    $option->question_id = $question->id;
                    $option->order_value = $key;
                    $stopCollect = $this->checkValue($ooptionData, 'stop_collect', 0);
                    $option->stop_collect = $stopCollect ? 1 : 0;
                    $option->save();
                    
                    if($option->config_id == 0 || $option->config_id == null){
                        $option->config_id = $option->id;
                        $option->save();
                    }
                    
                    //pushing ids of existing/new created options
                    $this->optionsToDoNotDelete[] = $option->id;
                }
                
                //deleting only non existings ids  for options
                    if(!empty($this->optionsToDoNotDelete)){
                        QuestionOption::where("question_id", $question->id)
                        ->whereNotIn('id', $this->optionsToDoNotDelete)
                        ->delete();

                        $this->optionsToDoNotDelete = [];
                    }
            }
            else
                QuestionOption::where("question_id", $question->id)->delete();

            $form = \App\Types\FormType::getForm($formId);
            
            if($form)
                $form = $this->getFormWithExtendedKeys($form,$groupId);

            return $this->successData($form);
        }
        else
            return $this->failed("Invalid Data Sent!");
    }
    
    /**
     * the following method is used to duplicate Question
     *
     * @param [json] data
     *
     * @return form details
     */
    public function duplicateQuestion()
    {
        $formId = (int)@request("form_id");
        $questionId = request("question_id");
        $question = Question::find($questionId);

        if($question)
        {
            $newQuestion = $question->replicate();
            $newQuestion->name_en = $question->name_en." Duplicated";
            $newQuestion->name_ar = $question->name_ar." Duplicated";
            $newQuestion->name_ku = $question->name_ku." Duplicated";
            $newQuestion->save();
                    
            //adding options
            $questionOptions = QuestionOption::where("question_id", $question->id)->get();

            if (count($questionOptions) > 0) {
                foreach($questionOptions as $questionOption){
                    $newQuestionOptions = $questionOption->replicate();
                    $newQuestionOptions->question_id = $newQuestion->id;
                    $newQuestionOptions->save();
                }
            }
           
        }else
            return $this->failed("Invalid Question!");
        
        $form = \App\Types\FormType::getForm($formId);

        return $this->successData($form);
    }

    public function checkValue($array, $name, $default = null) {
        if (isset($array[$name])) {
            return $array[$name];
        }
        return $default;
    }

    public function checkJsonValue($array, $name, $default = null) {
        if (isset($array[$name])) {
            return json_encode($array[$name]);
        }
        return json_encode((array)$default);
    }

    public function generateIdMaps($array) {
        if (isset($array["generated_id"]) && !empty($this->generatedIds[$array["generated_id"]])) {
            return $this->generatedIds[$array["generated_id"]];
        }
        return null;
    }

    public function issetAndNumeric($array, $key) {
        if (!isset($array[$key])) {
            return false;
        } elseif (!is_numeric($array[$key])) {
            return false;
        }

        //if uuid
        if (strpos($array[$key], "-") !== false) {
            return false;
        }

        return $array[$key];
    }

    public function moveGroupToOtherParent() {
        //		\Log::info("group_id---" + request("group_id") + "---to_parent_id---" + request("to_parent_id"));
        $group = QuestionGroup::find(request("group_id"));
        $group->parent_group = request("to_parent_id");
        if ($group->save()) {
            return $this->success();
        }
        return $this->failed();
    }

    public function moveQuestionToAnotherGroup() {
        //		\Log::info("question_id---" + request("question_id") + "---to_group_id---" + request("group_id"));
        $question = Question::find(request("question_id"));
        $question->question_group_id = request("group_id");
        $question->save();
        if ($question->save()) {
            return $this->success();
        }
        return $this->failed();
    }

    /**
     * Update the specified resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function enableFormStatus($id) {

        $form       = Form::find($id);
        
        if (!$form) {
            return $this->failed("Invalid Form");
        }
        
        $form->is_mobile = 0;
        $form->save();
        
        return $this->successData($form);
    }
    
    /**
     * Update the specified resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function pushToMobile() {

        $id         = request("form_id");
        //$status     = request("is_mobile", 0);
        $form       = Form::find($id);
        
        if (!$form) {
            return $this->failed("Invalid Form");
        }
        
        $form->is_mobile = 1;
        $form->save();
        
        $projectId = $form->project_id;
        $form = \App\Types\FormType::getForm($id);
        
        $pushToMobile = new PushToMobile();
        $pushToMobile->project_id = $projectId;
        $pushToMobile->form_id = $id;
        $pushToMobile->version = (int)@PushToMobile::where("form_id", $id)->count()+1;
        $pushToMobile->response_data = $form;
        $pushToMobile->save();
        
        return $this->successData($form);
    }
    
    /**
     * the following method is used to get the form instances that have the give option value
     *
     * @param  [number] $value
     * @return void
     */
    public function getFormInstancesWithOption() {
        /*$data = $this->request->all();
        $query = QuestionAnswer::where('value', $data['value']);
        // if date is selected
        if (!empty($data['date'])) $query->whereDate('created_at', $data['date']);
        // if site is selected OR if cluster is selected
        if (!empty($data['site_id']) || !empty($data['cluster_id'])) $query->whereHas(
            'formInstance', function ($query) use ($data) {
            // if site_id is set, and cluster ID is not set
            if (!empty($data['site_id']) && empty($data['cluster_id'])) $query->where('site_id', $data['site_id']);
            // if cluster_id is set
            if (!empty($data['cluster_id'])) $query->where('cluster_id', $data['cluster_id']);
        }
        );
        return $query->with('formInstance', 'question')->get();*/
        
        $projectId = request("project_id");
        $governorates = request("governorates");
        $districts = request("districts");
        $sites = request("sites");
        $clusters = request("clusters");
        $answers = request("values");
        $startDate = request("date_start");
        $endDate = request("date_end");
        
        if(is_array($answers))
        {
            $list = [];
            foreach($answers as $value){
                if(is_array($value))
                {
                    foreach($value as $val)
                        array_push($list, $val);
                }
                array_push($list, $value);   
            }
            $answers = $list;
        }
        
        $query = QuestionAnswer::where("project_id",$projectId)->whereIn('value', $answers);
        
        // if date range is selected
        if (!empty($startDate) && $startDate != null && !empty($endDate) && $endDate != null){
            if($startDate == $endDate)
            {
                $startDate = $startDate." 00:00:00";
                $endDate = $endDate." 23:59:59";
            } 
            $query->whereBetween('created_at', [$startDate, $endDate]);
        }
        
        if (!empty($governorates) || !empty($districts) || !empty($sites) || !empty($clusters)){
            $query->whereHas('formInstance', function ($query) use ($governorates, $districts, $sites, $clusters) {
                // if site_id is set, and cluster ID is not set
                if (!empty($clusters) && $clusters != null) 
                    $query->whereIn('cluster_id', $clusters);
                
                else if (!empty($sites) && $sites != null) 
                    $query->whereIn('site_id', $sites);
                
                else if (!empty($districts) && $districts != null) 
                    $query->whereIn('district_id', $districts);
                
                else if (!empty($governorates) && $governorates != null) 
                    $query->whereIn('governorate_id', $governorates);
            });
        }
        
        return $this->successData($query->with('formInstance', 'question')->get());
    }

    /**
     * the following method is used to download the project template file
     *
     * @return void
     */
    public function exportProjectTemplate(){
        return response()->download(public_path()."/Project_Template.xls", "Project_Template.xls", ['Content-Type: application/vnd.ms-excel']);
    }
    
    
    /**
     * the following method is used to export form and structure under the form to excel
     *
     * @param  [number] $value
     * @return excel file
     */
    public function exportForm() {
        $formId = request("form_id");
        $projectId = request("project_id");
        
        //paramters data
        $project = Project::find($projectId);
        
        if($project->project_type == 'survey'){
            $paramData[] = array(0=>"id",1=>"name_en");
            $paramData[] = array(0=>"Parameter/Form Type ID",1=>"Parameter Name English");
        }else{
            $paramData[] = array(0=>"id",1=>"name_en",2=>"parameter_type",3=>"parameter_level");
            $paramData[] = array(0=>"Parameter/Form Type ID",1=>"Parameter Name English",2=>"Parameter Type",3=>"Parameter Level");
        }
        $parameters = Parameter::where("project_id", $projectId)->get();
        foreach($parameters as $key=>$param){
            $formTypeId = FormType::where("parameter_id", $param->id)->pluck("id")->first();
            
            if($project->project_type == 'survey')
                array_push($paramData, array(0=>$formTypeId,1=>$param->name_en));
            else
                array_push($paramData, array(0=>$formTypeId,1=>$param->name_en,2=>$param->parameter_type,3=>$param->parameter_level));
        }
        
        //categories data
        $categoryData[] = array(0=>"form_type_id",1=>"id",2=>"name_en",3=>"name_ar",4=>"name_ku",5=>"all_question_required",6=>"all_question_optional",7=>"order");
        $categoryData[] = array(0=>"Form Type id OR Name in English",1=>"Category Id",2=>"Category Name in English",3=>"Category Name in Arabic",4=>"Category Name in Kurdish",5=>"1 if Question is required",6=>"1 if question is optional",7=>"Order Sequence");
        $categories = FormCategory::where("form_id", $formId)->orderBy("form_type_id")->get();
        foreach($categories as $key=>$category){
            array_push($categoryData, array(0=>$category->form_type_id,1=>$category->id,2=>$category->name_en,3=>$category->name_ar,4=>$category->name_ku,5=>$category->all_question_required,6=>$category->all_question_optional,7=>$category->order));
        }
        
        //Questions data
        $questionIds = [];
        $questionData[] = array(0=>"category_id_or_categor_name_en",1=>"id",2=>"name_en",3=>"name_ar",4=>"name_ku",5=>"question_code",6=>"required",7=>"setting",8=>"multiple",9=>"order",10=>"question_number",11=>"response_type_id");
        $questionData[] = array(0=>"Category id OR Category English Name",1=>"Question Id",2=>"Question Name in English",3=>"Question Name in Arabic",4=>"Question Name in Kurdish",5=>"Question Code",6=>"Set 1 for required",7=>"setting",8=>"Set 1 for Multiple",9=>"Order Sequence",10=>"Question Number",11=>"Get value from Response Types List");
        $questions = Question::where("form_id", $formId)->get();
        $questionCats = QuestionGroup::where("form_id", $formId)->pluck("form_category_id","id")->toArray();

        foreach($questions as $key=>$question){
            $questionIds[] = $question->id;
            array_push($questionData, array(0=>$questionCats[$question->question_group_id],1=>$question->id,2=>$question->name_en,3=>$question->name_ar,4=>$question->name_ku,5=>$question->question_code,6=>$question->order,7=>json_encode($question->setting),8=>$question->multiple,9=>$question->order,10=>$question->question_number,11=>$question->response_type_id));
        }
        
        //question option data
        $optionData[] = array(0=>"question_id_or_question_name_en",1=>"id",2=>"name_en",3=>"name_ar",4=>"name_ku",5=>"order_value",6=>"stop_collect");
        $optionData[] = array(0=>"Question Id Or Question English name",1=>"Option Id", 2=>"Option Name in English",3=>"Option Name in Arabic",4=>"Option Name in Kurdish",5=>"Order Sequence Number",6=>"Stop Collecting Set 1");
        $questionOptions = QuestionOption::whereIn("question_id", $questionIds)->get();
        foreach($questionOptions as $key=>$option){
            array_push($optionData, array(0=>$option->question_id,1=>$option->id,2=>$option->name_en,3=>$option->name_ar,4=>$option->name_ku,5=>$option->order_value,6=>$option->stop_collect));
        }
        
        //skip logic data
        $logicData[] = array(0=>"parent_id_or_parent_question_name",1=>"question_id",2=>"operator_id",3=>"condition_id",4=>"option_value_id_or_option_name_en",5=>"option_value");
        $logicData[] = array(0=>"Question Id OR New Question Name",1=>"Sub Question Id", 2=>"Operator",3=>"Condition",4=>"Option Value Id Or New Question Option Value",5=>"Skip Logic Option value if in text form");
        $skipLogics = SkipLogicQuestion::with("skipLogicDetails")->whereIn("parent_id", $questionIds)->get();
        foreach($skipLogics as $key=>$logic){
            foreach($logic->skipLogicDetails as $key2 => $logic)
                array_push($logicData, array(0=>$logic->parent_id,1=>$logic->question_id,2=>$logic->operator_id,3=>$logic->condition_id,4=>$logic->option_value_id,5=>$logic->option_value));
        }
        
        //setting options data
        $settingOptionData[] = array(0=>"question_id_or_qustion_name_eng",1=>"guide_en",2=>"guide_ar",3=>"guide_ku",4=>"note_en",5=>"note_ar",6=>"note_ku");
        $settingOptionData[] = array(0=>"Question id Or New Question Name in English",1=>"Guide in English", 2=>"Guide in Arabic",3=>"Guide in Kurdish",4=>"Note in English",5=>"Note in Arabic",6=>"Note in Kurdish");
        $settingOptions = QuestionSettingOptions::whereIn("question_id", $questionIds)->get();
        foreach($settingOptions as $key=>$option){
            array_push($settingOptionData, array(0=>$option->question_id,1=>$option->guide_en,2=>$option->guide_ar,3=>$option->guide_ku,4=>$option->note_en,5=>$option->note_ar,6=>$option->note_ku));
        }
        
        //setting appearance data
        $settingAppearanceData[] = array(0=>"question_id_or_qustion_name_eng",1=>"font",2=>"color",3=>"highlight",4=>"positioning",5=>"capitalization",6=>"font_style");
        $settingAppearanceData[] = array(0=>"Question id Or Question name in English",1=>"Font Type", 2=>"Color",3=>"Highlight",4=>"Position to Show",5=>"If Text need to Captilize",6=>"Font Style");
        $settingAppearances = QuestionSettingAppearance::whereIn("question_id", $questionIds)->get();
        foreach($settingAppearances as $key=>$appearance){
            array_push($settingAppearanceData, array(0=>$appearance->question_id,1=>$appearance->font,2=>$appearance->color,3=>$appearance->highlight,4=>$appearance->positioning,5=>$appearance->capitalization,6=>$appearance->font_style));
        }
        
        // exporting the excel sheet with the resepctive data
        \Excel::create(
           'ExportForm'.$projectId, function ($excel) use ($paramData, $categoryData, $questionData, $optionData, $logicData, $settingOptionData, $settingAppearanceData) {

                // Set the title
                $excel->setTitle('Form Data');
            
                // Chain the setters
                $excel->setCreator('IdeatoLife')
                    ->setCompany('IdeatoLife');
            
                // creating the sheet and filling it with parameter data
                $excel->sheet(
                    'Parameters', function ($sheet) use ($paramData) {
                        $sheet->rows($paramData);
                        // appplying wrap text on all the E column's cells
/*                        for($i = 1 ; $i <= count($paramData); $i++){
                            $sheet->getStyle('A'.$i)->getAlignment()->setWrapText(true);
                            $sheet->getStyle('B'.$i)->getAlignment()->setWrapText(true);
                            $sheet->getStyle('C'.$i)->getAlignment()->setWrapText(true);
                            $sheet->getStyle('D'.$i)->getAlignment()->setWrapText(true);
                        }
                        $this->prepareHeaders($header, $sheet);*/
                    }
                );
                
                // creating the sheet and filling it with categories data
                $excel->sheet(
                    'Categories', function ($sheet) use ($categoryData) {
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
                
                // creating the sheet and filling it with skip logic data
                $excel->sheet(
                    'SkipLogic', function ($sheet) use ($logicData) {
                        $sheet->rows($logicData);                        
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
        return response()->download("/tmp/"."ExportForm{$projectId}.xls", "ExportForm_{$projectId}_{$formId}.xls", ['Content-Type: application/vnd.ms-excel']);
    }

    
    /**
     * the following method is used to import form data
     *
     * @param  excel file
     * @return void
     */
    public function importForm() 
    {
        $formId = $this->request->form_id;

        if (!$this->request->hasFile("import_file") && $formId > 0) {
            return $this->failed('Invalid Excel File or Form Id');
        }
                
        Excel::load(
            $this->request->file('import_file')
                ->getRealPath(), function ($reader) {
                    $tab = 0;                    
                    $questionsToDelete = [];
                    $questionsNotToDelete = [];
                    $dataList = $reader->toArray();       
                    $formId = $this->request->form_id;
                    $projectId = Form::find($formId)->project_id;
                    
                    if(count($dataList) < 7)
                        return $this->failed('Invalid Excel File.');
                    
                    foreach ($dataList as $key => $row) {                        
                        try {
                            if($tab == 0) //import Parameter Data
                            {
                                    $parametersNotToDelete = [];
                                    if(count($row)>1)
                                    {
                                        foreach($row as $key =>$value)
                                        {
                                            if($key == 0)
                                                continue; 
                                            
                                            if(is_numeric($value['id']))
                                            {
                                                $formType = FormType::find($value['id']);
                                                $parameter = Parameter::where("project_id", $projectId)->find($formType->parameter_id);
                                                if($parameter && !empty($value['name_en']))
                                                {
                                                    $parameter->name_en = $value['name_en'];
                                                    $parameter->name_ar = $value['name_en'];
                                                    $parameter->name_ku = $value['name_en'];
                                                    if(isset($value['parameter_type']) && !empty($value['parameter_type'])){
                                                        $parameter->parameter_type = ($value['parameter_type'] == ""?"collection":$value['parameter_type']);
                                                        $parameter->parameter_level = ($value['parameter_type'] != "collection" && isset($value['parameter_level']))?$value['parameter_level']:"verifier";
                                                    }    
                                                    $parameter->save();
                                                    
                                                    $parametersNotToDelete[] = $parameter->id;
                                                }
                                            }
                                            else if(!empty($value['name_en']))
                                            {
                                                $parameter = Parameter::where("project_id", $projectId)->where("name_en", "LIKE", $value['name_en'])->first();
                                                $parameter = ($parameter)? $parameter : new Parameter();
                                                        
                                                $parameter->project_id = $projectId;
                                                $parameter->allow_edit = 1;
                                                $parameter->name_en = $value['name_en'];
                                                $parameter->name_ar = $value['name_en'];
                                                $parameter->name_ku = $value['name_en'];
                                                if(isset($value['parameter_type']) && !empty($value['parameter_type'])){
                                                    $parameter->parameter_type = ($value['parameter_type'] == ""?"collection":$value['parameter_type']);
                                                    $parameter->parameter_level = ($value['parameter_type'] != "collection" && isset($value['parameter_level']))?$value['parameter_level']:"verifier";                                                    
                                                }
                                                if(isset($value['parameter_type']) && $value['parameter_type'] == "verification" && (isset($value['parameter_level']) || $parameter->parameter_level == 'verifier')){
                                                    $checkParamCount = Parameter::where("project_id", $projectId)->where("parameter_type", $value['parameter_type'])->where("parameter_level", $parameter->parameter_level)->count();
                                                    if($checkParamCount > 0)
                                                        return $this->failed("A verification parameter of same level can not be created!");
                                                }
                                                
                                                $parameter->save();
                                                
                                                $formType = FormType::where("form_id", $formId)->where("parameter_id", $parameter->id)->first();
                                                $formType = ($formType)? $formType : new FormType();
                                                $formType->parameter_id = $parameter->id;
                                                $formType->name_en = ($parameter->name_en == ""?"Param Form Type":$parameter->name_en);
                                                $formType->name_ar = $parameter->name_ar;
                                                $formType->name_ku = $parameter->name_ku;
                                                $formType->parameter_type = ($parameter->parameter_type == ""?"collection":$parameter->parameter_type);
                                                $formType->parameter_level = $parameter->parameter_level;
                                                $formType->form_id = (int)@$formId;
                                                $formType->allow_edit = $parameter->allow_edit;
                                                $formType->loop = $parameter->loop;
                                                $formType->order = (int)@$parameter->order;
                                                $formType->save();
                                                        
                                                $parametersNotToDelete[] = $parameter->id;
                                            }
                                        }                                        
                                    }
                                    
                                    Parameter::where("project_id", $projectId)->whereNotIn("id", $parametersNotToDelete)->delete();                                    
                            }
                            else if($tab == 1) //import Categories Data
                            {
                                    $categoriesNotToDelete = [];
                                    if(count($row)>1)
                                    {
                                        foreach($row as $key =>$value)
                                        {
                                            if($key == 0)
                                                continue; 
                                            
                                            if(is_numeric($value['id']) && !empty($value['name_en']))
                                            {
                                                $formCategory = FormCategory::where("form_id", $formId)->find($value['id']);
                                                
                                                if($formCategory){
                                                    $formCategory->form_id = $formId;
                                                    $formCategory->form_type_id = (int)@$value['form_type_id'];
                                                    $formCategory->name_en = $value['name_en'];
                                                    $formCategory->name_ar = ($value['name_ar'] == ""?$value['name_en']:$value['name_ar']);
                                                    $formCategory->name_ku = ($value['name_ku'] == "")?$value['name_en']:$value['name_ku'];
                                                    $formCategory->all_question_required = (int)@$value['all_question_required'];
                                                    $formCategory->all_question_optional = (int)@$value['all_question_optional'];
                                                    $formCategory->order = (int)@$value['order'];
                                                    $formCategory->save();
                                                    
                                                    $categoriesNotToDelete[] = $formCategory->id;
                                                }
                                            }
                                            else{
                                                
                                                if(is_numeric($value['form_type_id'])){
                                                        $formCategory = FormCategory::where("form_id", $formId)->where("form_type_id", $value['form_type_id'])->where("name_en", $value['name_en'])->first();
                                                        $formCategory = ($formCategory)? $formCategory : new FormCategory();
                                                
                                                        if($formCategory){
                                                            $formCategory->form_id = $formId;
                                                            $formCategory->form_type_id = $value['form_type_id'];
                                                            $formCategory->name_en = empty($value['name_en'])?"new category":$value['name_en'];
                                                            $formCategory->name_ar = ($value['name_ar'] == ""?$formCategory->name_en:$value['name_ar']);
                                                            $formCategory->name_ku = ($value['name_ku'] == "")?$formCategory->name_en:$value['name_ku'];
                                                            $formCategory->all_question_required = (int)@$value['all_question_required'];
                                                            $formCategory->all_question_optional = (int)@$value['all_question_optional'];
                                                            $formCategory->order = (int)@$value['order'];
                                                            $formCategory->save();
                                                        }
                                                }
                                                else{
                                                    
                                                    $parameter = Parameter::where("project_id", $projectId)->where("name_en", "LIKE", $value['form_type_id'])->first();
                                                    $formType = FormType::where("form_id", $formId)->where("name_en", "LIKE", $value['form_type_id'])->first();
                                                    $formType = ($formType)? $formType : new FormType();
                                                    
                                                    if($parameter)
                                                    {                                                        
                                                        $formType->parameter_id = $parameter->id;
                                                        $formType->name_en = ($parameter->name_en == ""?"Param Form Type":$parameter->name_en);
                                                        $formType->name_ar = $parameter->name_ar;
                                                        $formType->name_ku = $parameter->name_ku;
                                                        $formType->parameter_type = ($parameter->parameter_type == ""?"collection":$parameter->parameter_type);
                                                        $formType->parameter_level = $parameter->parameter_level;
                                                        $formType->form_id = (int)@$formId;
                                                        $formType->allow_edit = $parameter->allow_edit;
                                                        $formType->loop = $parameter->loop;
                                                        $formType->order = (int)@$parameter->order;
                                                        $formType->save();
                                                        
                                                        $formCategory = FormCategory::where("form_id", $formId)->where("form_type_id", $formType->id)->where("name_en", $value['name_en'])->first();
                                                        $formCategory = ($formCategory)? $formCategory : new FormCategory();
                                                        
                                                        $formCategory->form_id = $formId;
                                                        $formCategory->form_type_id = $formType->id;
                                                        $formCategory->name_en = empty($value['name_en'])?"new category":$value['name_en'];
                                                        $formCategory->name_ar = ($value['name_ar'] == ""?$formCategory->name_en:$value['name_ar']);
                                                        $formCategory->name_ku = ($value['name_ku'] == "")?$formCategory->name_en:$value['name_ku'];
                                                        $formCategory->all_question_required = (int)@$value['all_question_required'];
                                                        $formCategory->all_question_optional = (int)@$value['all_question_optional'];
                                                        $formCategory->order = (int)@$value['order'];
                                                        $formCategory->save();
                                                    }
                                                    
                                                }
                                                    
                                                $categoriesNotToDelete[] = $formCategory->id;
                                            }
                                        }
                                        
                                    }
                                    FormCategory::where("form_id", $formId)->whereNotIn("id", $categoriesNotToDelete)->delete();
                            }
                            else if($tab == 2) //import Questions Data
                            {                                    
                                    if(count($row)>1)
                                    {
                                        foreach($row as $key =>$value)
                                        {
                                            if($key == 0)
                                                continue; 
                                            
                                            if(is_numeric($value['id']))
                                            {
                                                $question = Question::where("form_id", $formId)->find($value['id']);
                                                
                                                if($question){
                                                    $question->name_en = $value['name_en'];
                                                    $question->name_ar = $value['name_ar'];
                                                    $question->name_ku = $value['name_ku'];
                                                    $question->question_code = $value['question_code'];
                                                    $question->required = is_numeric($value['required'])?$value['required']:0;
                                                    $question->multiple = is_numeric($value['multiple'])?$value['multiple']:0;
                                                    $question->order = (int)@$value['order'];
                                                    $question->response_type_id = is_numeric($value['response_type_id'])?$value['response_type_id']:1;
                                                    $question->form_id = (int)@$formId;
                                                    //$question->question_group_id = (int)$value['question_group_id'];
                                                    $question->save();

                                                    $questionsNotToDelete[] = $question->id;
                                                }
                                            }
                                            else{
                                                
                                                if(is_numeric($value['category_id_or_categor_name_en']))
                                                    $category = FormCategory::where("form_id", $formId)->find($value['category_id_or_categor_name_en']);
                                                else 
                                                    $category = FormCategory::where("form_id", $formId)->where("name_en", "LIKE", $value['category_id_or_categor_name_en'])->first();                                                    
                                                
                                                if($category)
                                                {
                                                    $groupId = (int)QuestionGroup::where("form_id", $formId)->where("form_category_id", $category->id)->pluck("id")->first();
                                                    
                                                    if($groupId == 0){
                                                        $group = new QuestionGroup();
                                                        $group->name = 'Group '.$category->name_en;
                                                        $group->form_id = $formId;
                                                        $group->form_type_id = $category->form_type_id;
                                                        $group->form_category_id = $category->id;
                                                        $group->order_value = 1;
                                                        $group->root_group = 1;
                                                        $group->save();
                                                        $groupId = $group->id;
                                                    }
                                                    
                                                    $question = Question::where("form_id", $formId)->where("question_group_id", $groupId)->where("name_en", $value['name_en'])->first();
                                                    $question = ($question)? $question : new Question();
                                                        
                                                    $question->name_en = $value['name_en'];
                                                    $question->name_ar = $value['name_ar'];
                                                    $question->name_ku = $value['name_ku'];
                                                    $question->question_code = $value['question_code'];
                                                    $question->required = is_numeric($value['required'])?$value['required']:0;
                                                    $question->multiple = is_numeric($value['multiple'])?$value['multiple']:0;
                                                    $question->order = (int)@$value['order'];
                                                    $question->response_type_id = is_numeric($value['response_type_id'])?$value['response_type_id']:1;
                                                    $question->form_id = (int)@$formId;
                                                    $question->question_group_id = (int)@$groupId;
                                                    $question->save();
                                                    
                                                    $questionsNotToDelete[] = $question->id;
                                                }                                                                                            
                                            }
                                        }
                                        
                                    }
                                    $questionsToDelete = Question::where("form_id", $formId)->whereNotIn("id", $questionsNotToDelete)->pluck("id")->toArray();
                                    Question::where("form_id", $formId)->whereNotIn("id", $questionsNotToDelete)->delete();
                            }
                            else if($tab == 3) //Question Options Data
                            {
                                    $questionsOptionsNotToDelete = [];
                                    if(count($row)>1)
                                    {
                                        foreach($row as $key =>$value)
                                        {
                                            if($key == 0)
                                                continue; 
                                            
                                            if(is_numeric($value['id']))
                                            {
                                                $option = QuestionOption::where("question_id", $value['question_id_or_question_name_en'])->find($value['id']);
                                                if($option){
                                                    $option->name_en = $value['name_en'];
                                                    $option->name_ar = $value['name_ar'];
                                                    $option->name_ku = $value['name_ku'];
                                                    $option->question_id = (int)@$value['question_id_or_question_name_en'];
                                                    $option->order_value = (int)@$value['order_value'];
                                                    $option->stop_collect = (int)@$value['stop_collect'];
                                                    //$option->description = $value['description'];
                                                    $option->save();
                                                    
                                                    if($option->config_id == 0 || $option->config_id == null){
                                                        $option->config_id = $option->id;
                                                        $option->save();
                                                    }
                                                    
                                                    $questionsOptionsNotToDelete[] = $option->id;
                                                }
                                            }
                                            else
                                            {
                                                if(is_numeric($value['question_id_or_question_name_en']))
                                                    $question = Question::where("form_id", $formId)->find($value['question_id_or_question_name_en']);
                                                else
                                                    $question = Question::where("form_id", $formId)->where("name_en", "LIKE", $value['question_id_or_question_name_en'])->first();
                                                
                                                if($question){
                                                    $option = new QuestionOption();
                                                    $option->name_en = $value['name_en'];
                                                    $option->name_ar = $value['name_ar'];
                                                    $option->name_ku = $value['name_ku'];
                                                    $option->question_id = (int)@$question->id;
                                                    $option->order_value = (int)@$value['order_value'];
                                                    $option->stop_collect = (int)@$value['stop_collect'];
                                                    //$option->description = $value['description'];
                                                    $option->save();
                                                    
                                                    if($option->config_id == 0 || $option->config_id == null){
                                                        $option->config_id = $option->id;
                                                        $option->save();
                                                    }
                                                    
                                                    $questionsOptionsNotToDelete[] = $option->id;
                                                }
                                            }
                                        }                                        
                                    }
                                    
                                    QuestionOption::whereNotIn("id", $questionsOptionsNotToDelete)->orWhereIn("question_id", $questionsToDelete)->delete();
                            }
                            else if($tab == 4) //import Question SkipLogic Data
                            {
                                    if(count($row)>1)
                                    {
                                        SkipLogicQuestion::whereIn("parent_id", $questionsToDelete)->delete();
                                        SkipLogicQuestionDetail::whereIn("parent_id", $questionsToDelete)->delete();
                                        $prevQuestion = 0;
                                        foreach($row as $key =>$value)
                                        {
                                            if($key == 0)
                                                continue; 
                                            
                                            if(is_numeric($value['parent_id_or_parent_question_name']))
                                                $parentId = $value['parent_id_or_parent_question_name'];
                                            else{
                                                $question = Question::where("form_id", $formId)->where("name_en", "LIKE", $value['parent_id_or_parent_question_name'])->first();
                                                $parentId = $question->id;
                                            }   
                                            
                                            if($parentId > 0)
                                            {
                                                if(is_numeric($value['option_value_id_or_option_name_en']))
                                                    $optionValueId = (int)@$value['option_value_id_or_option_name_en'];
                                                else{
                                                    $questionOption = QuestionOption::where("question_id", $parentId)->where("name_en", "LIKE", $value['option_value_id_or_option_name_en'])->first();
                                                    $optionValueId = (int)@$questionOption->id;
                                                }
                                                        
                                                if(is_numeric($optionValueId)){
                                                    if(($prevQuestion == 0 || $prevQuestion != $value['question_id']) && (int)$value['question_id']>0){
                                                        $skipLogicQuestion = new SkipLogicQuestion();
                                                        $skipLogicQuestion->question_id = $value['question_id'];
                                                        $skipLogicQuestion->operator_id = (int)$value['operator_id'];
                                                        $skipLogicQuestion->condition_id = (int)$value['condition_id'];
                                                        $skipLogicQuestion->project_id = $projectId;
                                                        $skipLogicQuestion->form_id = $formId;
                                                        $skipLogicQuestion->parent_id = $parentId;
                                                        $skipLogicQuestion->save();

                                                        $prevQuestion = $value['question_id'];
                                                    }

                                                    if(isset($skipLogicQuestion) && @$skipLogicQuestion->id > 0){
                                                        $skipLogicQuestionDetail = new SkipLogicQuestionDetail();
                                                        $skipLogicQuestionDetail->skip_logic_id = $skipLogicQuestion->id;
                                                        $skipLogicQuestionDetail->question_id = $value['question_id'];
                                                        $skipLogicQuestionDetail->parent_id = $parentId;
                                                        $skipLogicQuestionDetail->operator_id = (int)@$value['operator_id'];
                                                        $skipLogicQuestionDetail->option_value_id = (int)@$optionValueId;
                                                        $skipLogicQuestionDetail->option_value = $value['option_value'];
                                                        $skipLogicQuestionDetail->save(); 
                                                    }
                                                }
                                            }                                            
                                        }                                            
                                    }
                            }
                            else if($tab == 5) //Question Setting Options Data
                            {
                                    QuestionSettingOptions::where("form_id", $formId)->delete();
                                    if(count($row)>1)
                                    {
                                        foreach($row as $key =>$value)
                                        {
                                            if($key == 0)
                                                continue; 
                                            
                                            if(is_numeric($value['question_id_or_qustion_name_eng']))
                                                $question = Question::where("form_id", $formId)->find($value['question_id_or_qustion_name_eng']);
                                            else
                                                $question = Question::where("form_id", $formId)->where("name_en", "LIKE", $value['question_id_or_qustion_name_eng'])->first();

                                            if($question){
                                                $questionOption = new QuestionSettingOptions();
                                                $questionOption->guide_en = $value['guide_en'];
                                                $questionOption->guide_ar = $value['guide_ar'];
                                                $questionOption->guide_ku = $value['guide_ku'];
                                                $questionOption->note_en =  $value['note_en'];
                                                $questionOption->note_ar = $value['note_ar'];
                                                $questionOption->note_ku = $value['note_ku'];
                                                $questionOption->project_id = $projectId;
                                                $questionOption->form_id = $formId;
                                                $questionOption->question_id = $question->id;
                                                $questionOption->save();
                                            }
                                        
                                        }
                                    }
                            }
                            else if($tab == 6) //Question Setting Appearance Data
                            {
                                    QuestionSettingAppearance::where("form_id", $formId)->delete();
                                    if(count($row)>1)
                                    {
                                        foreach($row as $key =>$value)
                                        {
                                            if($key == 0)
                                                continue; 
                                            
                                            if(is_numeric($value['question_id_or_qustion_name_eng']))
                                                $question = Question::where("form_id", $formId)->find($value['question_id_or_qustion_name_eng']);
                                            else
                                                $question = Question::where("form_id", $formId)->where("name_en", "LIKE", $value['question_id_or_qustion_name_eng'])->first();

                                            if($question){
                                                $questionAppearance = new QuestionSettingAppearance();
                                                $questionAppearance->font = $value['font'];
                                                $questionAppearance->color = $value['color'];
                                                $questionAppearance->highlight = $value['highlight'];
                                                $questionAppearance->positioning = $value['positioning'];
                                                $questionAppearance->capitalization = $value['capitalization'];
                                                $questionAppearance->font_style = $value['font_style'];
                                                $questionAppearance->project_id = $projectId;
                                                $questionAppearance->form_id = $formId;
                                                $questionAppearance->question_id = $question->id;
                                                $questionAppearance->save();
                                            }
                                        }
                                    }                                    
                            }
                            
                            $tab ++;

                        } catch (\Exception $exception) {
                            throw $exception;
                            continue;
                        }
                    }
                }
        );
        
        return $this->success();        
    }
    
    /*
    * getFormWithExtendedKeys
    */
    private function getFormWithExtendedKeys($form, $groupId)
    {
        $categoryId = QuestionGroup::find($groupId)->form_category_id;
        if(isset($form['types'])){
            foreach($form['types'] as $type)
            {
                if(isset($type->categories))
                foreach($type->categories as $category)
                {
                    if(isset($category->id) && $category->id == $categoryId)
                       $category->expanded = true;
                    else
                        $category->expanded = false;
                }
            }
        }
        return $form;
    }
    
    /**
     * the following method is used to prepare headers for the respective excel sheet
     *
     * @param  [type] $headers
     * @return void
     */
    private function prepareHeaders($headers, $sheet)
    {
                                // make the headers as bold
        foreach($headers as $key =>$header){
            switch ($key) {
            case 0:
                $this->formatHeaders('A1', $sheet);
                break;
            case 1:
                $this->formatHeaders('B1', $sheet);
                break;
            case 2:
                $this->formatHeaders('C1', $sheet);
                break;
            case 3:
                $this->formatHeaders('D1', $sheet);
                break;
            case 4:
                $this->formatHeaders('E1', $sheet);
                break;
            case 5:
                $this->formatHeaders('F1', $sheet);
                break;
            case 6:
                $this->formatHeaders('G1', $sheet);
                break;
            case 7:
                $this->formatHeaders('H1', $sheet);
                break;
            default:
                echo "Your favorite color is neither red, blue, nor green!";
            }
        }
    }
    
    /**
     * the following method is used to format the cell headers
     *
     * @param  [type] $header
     * @param  [type] $sheet
     * @return void
     */
    private function formatHeaders($header, $sheet)
    {
        $sheet->cells(
            $header, function ($cells) {
                $cells->setFont(
                    array(
                    'family'     => 'Calibri',
                    'size'       => '16',
                    'bold'       =>  true
                    )
                );
            }
        );
    }
}
