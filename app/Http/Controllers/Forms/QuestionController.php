<?php

/*
 * This file is part of the IdeaToLife package.
 *
 * (c) Youssef Jradeh <youssef.jradeh@ideatolife.me>
 *
 */

namespace App\Http\Controllers\Forms;

use App\Http\Controllers\WhoController;
use App\Models\FormCategory;
use App\Models\Project;
use App\Models\Question;
use Idea\Helpers\Paging;

class QuestionController extends WhoController
{
    
    protected $permissions = [
    "one" => ["code" => "forms", "action" => "read"],
    ];
    
    /**
     *
     * @return array
     */
    protected static function validationRules() 
    {
        return [];
    }
    
    
    public function questionNumber($id) 
    {
        $questions = Question::where("form_id", $id)
                             ->where("response_type_id", 4)
                             ->get();
        return $this->successData($questions);
    }
    
    
    public function categories($id) 
    {
        
        $project = Project::with("form")->find($id);
        if (!$project || empty($project->form)) {
            return $this->failed("Invalid Project");
        }
        
        $categories = FormCategory::with("type")
                                  ->where("form_id", $project->form->id)
                                  ->get();
        
        //get all project's category , splitted by type name
        $toReturn = [];
        foreach ($categories AS $category) {
            if (!empty($category->type->name) && !in_array(
                $category->type->name, [
                'Cluster',
                'Site',
                ]
            )
            ) {
                $toReturn[$category->type->name][] = $category;
            }
        }
        ksort($toReturn);
        return $this->successData($toReturn);
    }
    
    //get all question of a specific project
    public function questions() 
    {
        $id = request("project_id");
        $searchQuery = !empty(@request('query')) ? request('query') : "";
        $project = Project::with("form")->find($id);
        
        if (!$project || empty($project->form)) {
            return $this->failed("Invalid Project");
        }
        
        $questions = Question::with("options")
                            ->where("form_id", $project->form->id)
                            ->where(function ($q) use ($searchQuery) {
                                if (!empty($searchQuery)) {
                                    $q->where('name_en', 'LIKE', "%" . $searchQuery . "%");
                                } else {
                                    $q->where('id', '!=', 0);
                                }
                            })
                            ->orderBy("name_en");

        return $this->successData(new Paging($questions));
    }
    
    //get all question of a specific project
    public function questionsProject($id) 
    {
        $searchQuery = !empty(@request('query')) ? request('query') : "";
        $project = Project::with("form")->find($id);
        
        if (!$project || empty($project->form)) {
            return $this->failed("Invalid Project");
        }
       
        $loopQuestions = [];
        $loopFormTypes = \App\Models\FormType::where("form_id",$project->form->id)->where("loop",1)->get();
        if(isset($loopFormTypes) && !empty($loopFormTypes)){
            foreach($loopFormTypes as $formType){
                $loopGroups = \App\Models\QuestionGroup::where("form_type_id",$formType->id)->pluck("id","id")->toArray();
                $questions = Question::whereIn("question_group_id",$loopGroups)->pluck("id","id")->toArray();
                
                if($formType->question_id > 0)
                    $loopQuestions[] = $formType->question_id;
                        
                foreach($questions as $questionId){
                    if($formType->question_id != $questionId)
                        $loopQuestions[] = $questionId;
                }
            }
        }
        
        $questions = Question::with("options")
                            ->where("form_id", $project->form->id)
                            ->where(function ($q) use ($searchQuery,$loopQuestions) {
                                if (!empty($searchQuery)) {                                    
                                    if(!empty($loopQuestions))
                                        $q->whereNotIn('id', $loopQuestions)->where('name_en', 'LIKE', "%" . $searchQuery . "%");
                                    else
                                        $q->where('name_en', 'LIKE', "%" . $searchQuery . "%");
                                        
                                } else {
                                    if(!empty($loopQuestions))
                                        $q->whereNotIn('id', $loopQuestions);
                                    else
                                        $q->where('id', '!=', 0);
                                }
                            })->orderBy("id")->get();
                            
        if(!empty($loopQuestions))
        {
            $loopQuestionObjs = Question::with("options")->where("form_id", $project->form->id)
                            ->whereIn('id', $loopQuestions)->get();
            $pointInsert = 0;
            $newQuestions = [];
            foreach($questions as $question){
                if($pointInsert == 5){
                    foreach($loopQuestionObjs as $loopQuest)
                        $newQuestions[] = $loopQuest;
                }
                $newQuestions[] = $question;
                $pointInsert ++;
            }
            $questions = $newQuestions;
        }
        
        return $this->successData($questions);
    }

    /**
     * the following method is used to "export form" functionality for the front-end
     *
     * @param  [type] $id
     * @return void
     */
    public function exportFormQuestions()
    {
        // finding the project details
        $project = Project::with("form")->find(request('project_id'));
        // finding the respective categories in the form
        $categories = FormCategory::with("type")
        ->where("form_id", $project->form->id)
        ->with('groups', 'groups.questions.options', 'groups.questions.conditions', 'groups.questions.conditions.option')
        ->get();

        $data = [];
        $headers = [];
        $headers[0] = "Main Category";
        $headers[1] = "Category";
        $headers[2] = "Question English";
        $headers[3] = "Question Arabic";
        $headers[4] = "Question Kurdish";
        $headers[5] = "Possible Answers English";
        $headers[6] = "Possible Answers Arabic";
        $headers[7] = "Possible Answers Kurdish";
        $headers[8] = "Condition ? Y/N";
        $headers[9] = "Condition";
        array_push($data, $headers);
        foreach ($categories as $category) {
            // iteraing over the question groups
            foreach($category->groups as $questionGroup){
                // iterating over all questions
                foreach($questionGroup->questions as $question){
                    $row = [];
                    $row[0] = $category->type->name_en; // Main Category
                    $row[1] = $category->name_en; // Category
                    $row[2] = $question->name_en; // Question Name
                    $row[3] = $question->name_ar; // Question Name shown on mobile which is in Arabic mostly
                    $row[4] = $question->name_ku; // Question Name shown on mobile which is in Arabic mostly
                    if($question->options) {
                        $optionsStringEnglish = '';
                        $optionsStringArabic = '';
                        $optionsStringKurdish = '';
                        // iterating over all the options of the current question
                        foreach($question->options as $option){
							$optionsStringEnglish .= " ".$option->name_en. " \r";
							$optionsStringArabic .= " ".$option->name_ar. " \r";
							$optionsStringKurdish .= " ".$option->name_ku. " \r";
                        }
                        $row[5] =  $optionsStringEnglish; // Question Name shown on mobile which is in Arabic mostly
                        $row[6] =  $optionsStringArabic; // Question Name shown on mobile which is in Arabic mostly
                        $row[7] =  $optionsStringKurdish; // Question Name shown on mobile which is in Arabic mostly
                    }else{
                        $row[5] = '';
                        $row[6] = '';
                        $row[7] = '';
                    }
                    $row[8] = count($question->conditions) > 0 ? 'Y' : 'N'; // if conditions exists or not
                    // constructing conditions string
                    $conditionString = "";
                    foreach($question->conditions as $condition){
                        // if question_type =
                        if($condition->type === '=' && $condition->option) {
                            if($conditionString) {
                                $conditionString .=" IF '".$condition->option->name."' \r";
                            }else{
                                $conditionString .= $condition->operation." IF '".$condition->option->name."' \r";
                            }
                        }else{
                            // for operators other than =
                            $conditionString .=  $condition->operation." IF '".$condition->type."' '".$condition->value."' \r";
                        }
                    }
                    $row[9] = $conditionString;
                    array_push($data, $row);
                }
            }
        }
        // exporting the excel sheet with the resepctive data
        \Excel::create(
            $project->name.'-form', function ($excel) use ($project, $data, $headers) {


                // Set the title
                $excel->setTitle('Form data submission of the project ' . $project->name);
            
                // Chain the setters
                $excel->setCreator('IdeatoLife')
                    ->setCompany('IdeatoLife');
            
                // creating the sheet and filling it with accumalated data
                $excel->sheet(
                    'Form', function ($sheet) use ($project, $data, $headers) {
                        $sheet->rows($data);
                        // appplying wrap text on all the E column's cells
                        for($i = 1 ; $i <= count($data); $i++){
                            $sheet->getStyle('A'.$i)->getAlignment()->setWrapText(true);
                            $sheet->getStyle('B'.$i)->getAlignment()->setWrapText(true);
                            $sheet->getStyle('C'.$i)->getAlignment()->setWrapText(true);
                            $sheet->getStyle('D'.$i)->getAlignment()->setWrapText(true);
                            $sheet->getStyle('D'.$i)->getAlignment()->applyFromArray(
                                array('horizontal' => 'right')
                            );
                            $sheet->getStyle('E'.$i)->getAlignment()->setWrapText(true);
                            $sheet->getStyle('E'.$i)->getAlignment()->applyFromArray(
                                array('horizontal' => 'right')
                            );
                            $sheet->getStyle('F'.$i)->getAlignment()->setWrapText(true);
                            $sheet->getStyle('F'.$i)->getAlignment()->applyFromArray(
                                array('horizontal' => 'right')
                            );
                            $sheet->getStyle('G'.$i)->getAlignment()->setWrapText(true);
                            $sheet->getStyle('H'.$i)->getAlignment()->setWrapText(true);
                            $sheet->getStyle('I'.$i)->getAlignment()->setWrapText(true);
                            $sheet->getStyle('J'.$i)->getAlignment()->setWrapText(true);

                        }
                        $this->prepareHeaders($headers, $sheet);
                    }
                );
            }
        )->store('xls', "/tmp");
        
        //PDF file is stored under project/public/download/info.pdf
        $file = "/tmp/".$project->name.'-form'.".xls";
            
        $headers = [
            'Content-Type: application/vnd.ms-excel',
        ];
            
        return response()->download($file, $project->name."-form.xls", $headers);
        // )->store('xls', public_path('excel/exports'))->download('xls');
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
            case 8:
                $this->formatHeaders('I1', $sheet);
                break;
            case 9:
                $this->formatHeaders('J1', $sheet);
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
