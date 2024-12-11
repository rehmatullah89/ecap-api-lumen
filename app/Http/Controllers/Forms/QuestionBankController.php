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
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\QuestionBank;
use App\Models\QuestionBankOption;
use Idea\Helpers\Paging;

class QuestionBankController extends WhoController
{

    protected $permissions = [
		"index"   => ["code" => "question_bank", "action" => "read"],
		"one"     => ["code" => "question_bank", "action" => "read"],
		"store"   => ["code" => "question_bank", "action" => "write"],
		"update"  => ["code" => "question_bank", "action" => "write"],
		"destroy" => ["code" => "question_bank", "action" => "write"],
	];

        /**
     *
     * @return array
     */
    protected static function validationRules() 
    {
        return [
            'store'  => [
            "name_en"           => "required",
            'response_type_id'  => "required",        
            ],
            'update' => [
            "name_en"           => "required",
            'response_type_id'  => "required",        
            ],
        ];
    }
    
        /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index() 
    {
        $searchQuery = !empty(@request('query')) ? request('query') : "";
        
        if(!empty($searchQuery))
        {
            $query = QuestionBank::where(function ($q) use ($searchQuery) {
                    $q->where('name_en', 'LIKE', "%" . $searchQuery . "%");
                    $q->orWhere('name_ar', 'LIKE', "%" . $searchQuery . "%");
                    $q->orWhere('name_ku', 'LIKE', "%" . $searchQuery . "%");
                })        
                ->with(['options', 'responseType'])
                ->orderBy('id', 'Desc');
        }
        else
            $query = QuestionBank::with(['options', 'responseType'])->orderBy('id', 'Desc');
        
        return $this->successData(new Paging($query));
    }
    
    /**
     * Store a newly created & updates resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function store()
    {
        $id = @request("id");
        
        $question  = @is_numeric($id)?QuestionBank::find($id):new QuestionBank(); 
        
        $question->name_en          = request('name_en');
        $question->name_ar          = request('name_ar');
        $question->name_ku          = request('name_ku');
        $question->question_code    = request('question_code');
        $question->consent          = request('consent');
        $question->mobile_consent   = request('mobile_consent');
        $question->required         = request('required', 0);
        $question->multiple         = request('multiple', 0);
        $question->setting          = json_encode(request('setting'));        
        $question->order            = request('order', 0);
        $question->question_number  = request('question_number');
        $question->response_type_id = request('response_type_id', 0);
        $question->save();
         
        //adding options
        $optionsData  = request('options');
        
        if(@is_numeric($id))
            QuestionBankOption::where('question_id', $id)->delete();
        
        if (!empty($optionsData)) {
            foreach ($optionsData AS $optionData) {                
                $option                 = new QuestionBankOption();
                $option->name_en        = $optionData['name_en'];
                $option->name_ar        = $optionData['name_ar'];
                $option->name_ku        = $optionData['name_ku'];
                $option->question_id    = $question->id;
                $option->stop_collect   = (int)@$optionData['stop_collect'];
                $option->save();
            }
        }
       
        return $this->successData($question);
    }

    
     /**
     * Import the specified resource in storage.
     *
     * @param int $questionId
     *
     * @return \Illuminate\Http\Response
     */
    public function importQuestion($questionId) 
    {
        $questionId = ($questionId > 0)?$questionId:@request("id");
        
        $question = Question::find($questionId);
        if (!$question) {
            return $this->failed("Invalid Question");
        }
        
        $questionBank                   = new QuestionBank();         
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
        
        $questionOptions = QuestionOption::where("question_id",$questionId)->get();
       
        if(isset($questionOptions) && count($questionOptions) >0)
        {
            foreach($questionOptions as $id => $optionData)
            {
                $questionBankOption                 = new QuestionBankOption(); 
                $questionBankOption->name_en        = $optionData['name_en'];
                $questionBankOption->name_ar        = $optionData['name_ar'];
                $questionBankOption->name_ku        = $optionData['name_ku'];
                $questionBankOption->question_id    = $questionBank->id;
                $questionBankOption->stop_collect   = (int)@$optionData['stop_collect'];
                $questionBankOption->save();                 
            }            
        }
        
        return $this->successData($questionBank);
    }
            
     /**
     * Update the specified resource in storage.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function update($id) 
    {        
        $question = QuestionBank::find($id);
        if (!$question) {
            return $this->failed("Invalid Bank Question");
        }
        
        $question->name_en          = request('name_en');
        $question->name_ar          = request('name_ar');
        $question->name_ku          = request('name_ku');
        $question->question_code    = request('question_code');
        $question->consent          = request('consent');
        $question->mobile_consent   = request('mobile_consent');
        $question->required         = request('required', 0);
        $question->multiple         = request('multiple', 0);
        $question->setting          = json_encode(request('setting'));        
        $question->order            = request('order', 0);
        $question->question_number  = request('question_number');
        $question->response_type_id = request('response_type_id', 0);
        $question->save();
        
        //adding options
        $optionsData  = request('options');
        QuestionBankOption::where('question_id', $id)->delete();
        
        if (!empty($optionsData)) {
            foreach ($optionsData AS $optionData) {                
                $option                 = new QuestionBankOption();
                $option->name_en        = $optionData['name_en'];
                $option->name_ar        = $optionData['name_ar'];
                $option->name_ku        = $optionData['name_ku'];
                $option->question_id    = $question->id;
                $option->stop_collect   = (int)@$optionData['stop_collect'];
                $option->save();
            }
        }
       
        return $this->successData($question);
    }
 
    /**
     * Search the specified resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function searchQuestionBank()
    {
        // checking for the search query
        $searchQuery = !empty(request('query')) ? request('query') : "";
        $query = QuestionBank::where(function ($q) use ($searchQuery) {
            $q->where('name_en', 'LIKE', "%" . $searchQuery . "%");
            $q->orWhere('name_ar', 'LIKE', "%" . $searchQuery . "%");
            $q->orWhere('name_ku', 'LIKE', "%" . $searchQuery . "%");
        })->with(['options', 'responseType']);
        
        return $this->successData(new Paging($query));        
    }
    
    /**
     * Export the specified resource from storage.
     *
     * @param int $categoryId
     *
     * @return \Illuminate\Http\Response
     */
    public function exportQuestionBank()
    {
        //Questions data
        $questionIds = [];
        $questionData[] = array(0=>"id",1=>"name_en",2=>"name_ar",3=>"name_ku",4=>"setting",5=>"response_type_id");
        $questionData[] = array(0=>"Question Id",1=>"Question Name in English",2=>"Question Name in Arabic",3=>"Question Name in Kurdish",4=>"setting",5=>"Get value from Response Types List");
        $questions = QuestionBank::get();

        foreach($questions as $key=>$question){
            $questionIds[] = $question->id;
            array_push($questionData, array(0=>$question->id,1=>$question->name_en,2=>$question->name_ar,3=>$question->name_ku,4=>json_encode($question->setting),5=>$question->response_type_id));
        }
        
        //question option data
        $optionData[] = array(0=>"question_id_or_question_name_en",1=>"id",2=>"name_en",3=>"name_ar",4=>"name_ku",5=>"order_value",6=>"stop_collect");
        $optionData[] = array(0=>"Question Id Or Question English name",1=>"Option Id", 2=>"Option Name in English",3=>"Option Name in Arabic",4=>"Option Name in Kurdish",5=>"Order Sequence Number",6=>"Stop Collecting Set 1");
        $questionOptions = QuestionBankOption::whereIn("question_id", $questionIds)->get();
        foreach($questionOptions as $key=>$option){
            array_push($optionData, array(0=>$option->question_id,1=>$option->id,2=>$option->name_en,3=>$option->name_ar,4=>$option->name_ku,5=>$option->order_value,6=>$option->stop_collect));
        }
        
        //setting options data
        $responseTypeData[] = array(0=>"Id",1=>"Response Type Code", 2=>"Name");
        $responseTypes = \App\Models\QuestionResponseType::where("id","!=",20)->get();
        foreach($responseTypes as $response){
            array_push($responseTypeData, array(0=>$response->id,1=>$response->code,2=>$response->name));
        }
        
        // exporting the excel sheet with the resepctive data
        \Excel::create(
           'ExportQuestionBank', function ($excel) use ($questionData, $optionData, $responseTypeData) {

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
                    'ResponseType', function ($sheet) use ($responseTypeData) {
                        $sheet->rows($responseTypeData);                        
                    }
                );
                
            }
        )->store('xls', "/tmp");
        
        //download excel file
        $file = "/tmp/ExportQuestionBank.xls";
        return response()->download($file, "ExportQuestionBank.xls", ['Content-Type: application/vnd.ms-excel']);
        
    }
    
    /**
     * Import the specified resource in storage.
     *
     * @param import_file
     *
     * @return \Illuminate\Http\Response
     */
    public function importQuestionBank() 
    {        
        if (!$this->request->hasFile("import_file")) {
            return $this->failed('Invalid Excel File');
        }
                
        Excel::load(
            $this->request->file('import_file')
                ->getRealPath(), function ($reader) {
                    $tab = 0;                    
                    
                    $dataList = $reader->toArray();       
                    
                    if(count($dataList) < 3)
                        return $this->failed('Invalid Excel File.');
                    
                    foreach ($dataList as $key => $row) {                        
                        try {
                            if($tab == 0) //import Questions Data
                            {                                    
                                    if(count($row)>1)
                                    {
                                        foreach($row as $key =>$value)
                                        {
                                            if($key == 0)
                                                continue; 
                                            
                                            if(is_numeric($value['id']))
                                            {
                                                $question = QuestionBank::find($value['id']);
                                                
                                                if($question){
                                                    $question->name_en = $value['name_en'];
                                                    $question->name_ar = $value['name_ar'];
                                                    $question->name_ku = $value['name_ku'];
                                                    $question->response_type_id = is_numeric($value['response_type_id'])?$value['response_type_id']:1;                                                    
                                                    $question->save();
                                                }
                                            }
                                            else{                                                
                                                    $question = QuestionBank::where("name_en", $value['name_en'])->first();
                                                    $question = ($question)? $question : new QuestionBank();
                                                        
                                                    $question->name_en = $value['name_en'];
                                                    $question->name_ar = $value['name_ar'];
                                                    $question->name_ku = $value['name_ku'];
                                                    $question->required = 0;
                                                    $question->multiple = 0;
                                                    $question->order = 1;
                                                    $question->response_type_id = is_numeric($value['response_type_id'])?$value['response_type_id']:1;
                                                    $question->save();                                                                                                                                    
                                            }
                                        }                                        
                                    }                                    
                            }
                            else if($tab == 1) //Question Options Data
                            {
                                    if(count($row)>1)
                                    {
                                        foreach($row as $key =>$value)
                                        {
                                            if($key == 0)
                                                continue; 
                                            
                                            if(is_numeric($value['id']))
                                            {
                                                $option = QuestionBankOption::where("question_id", $value['question_id_or_question_name_en'])->find($value['id']);
                                                if($option){
                                                    $option->name_en = $value['name_en'];
                                                    $option->name_ar = $value['name_ar'];
                                                    $option->name_ku = $value['name_ku'];
                                                    $option->question_id = (int)@$value['question_id_or_question_name_en'];
                                                    $option->order_value = (int)@$value['order_value'];
                                                    $option->stop_collect = (int)@$value['stop_collect'];
                                                    $option->save();
                                                    
                                                }
                                            }
                                            else
                                            {
                                                if(is_numeric($value['question_id_or_question_name_en']))
                                                    $question = QuestionBank::find($value['question_id_or_question_name_en']);
                                                else
                                                    $question = QuestionBank::where("name_en", "LIKE", $value['question_id_or_question_name_en'])->first();
                                                
                                                if($question){
                                                    $option = new QuestionBankOption();
                                                    $option->name_en = $value['name_en'];
                                                    $option->name_ar = $value['name_ar'];
                                                    $option->name_ku = $value['name_ku'];
                                                    $option->question_id = (int)@$question->id;
                                                    $option->order_value = (int)@$value['order_value'];
                                                    $option->stop_collect = (int)@$value['stop_collect'];
                                                    $option->save();                                                    
                                                }
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

    /**
     * download resource from storage.
     *
     * @return file
     */
    public function downloadQuestionTemplate() 
    {
        return response()->download(public_path()."/Question_Bank_Template.xls", "Question_Bank_Template.xls", ['Content-Type: application/vnd.ms-excel']);
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
            if (!$question = QuestionBank::find($id)) {
                return $this->failed("Invalid Bank Question");
            }
            
            //then delete the row from the database
            QuestionBankOption::where('question_id', $id)->delete();

            $question->delete();
            
            return $this->success('Bank Question deleted');
        } catch (\Exception $e) {
            return $this->failed('destroy error');
        }
    }
}
