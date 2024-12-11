<?php
/**
 * Update results crone
 *
 * (c) RehmatUllah <rehmatullahbhatti@gmail.com>
 *
 */

namespace App\Console\Commands;

use App\Models\Project;
use App\Models\Question;
use App\Models\SurveillanceReportCounter;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\SurveillanceFormInstance;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;


class SendNotifications extends Command
{
        /**
         * The console command name.
         *
         * @var string
         */
        protected $name = 'send:notifications';

        /**
         * The console command description.
         *
         * @var string
         */
        protected $description = 'Send daily and weekly and monthly notifications!';

        /**
        * Create a new command instance.
        *
        * @return void
        */
        public function __construct()
        {
            parent::__construct();
        }

        /**
         * Execute the console command.
         *
         * @return void
         */
        public function handle()
        {
            ini_set('memory_limit','-1');
            Log::info('------Starting Email Notifications!------'); // for lumen.log

            $weekEnd = date("Y-m-d", strtotime('sunday'));
            $monthEnd = date("Y-m-t");

            $this->sendDayEndNotifications();

            if($weekEnd == date("Y-m-d"))
                $this->sendWeekEndNotifications();

            if($monthEnd == date("Y-m-d"))
                $this->sendMonthEndNotifications();

        }//function ends

		/*
        * send daily Notifications
        **/
        function sendDayEndNotifications()
        {
            $query = \DB::select(DB::raw("SELECT id, user_id, confirmation_level, (SELECT name from projects where id=dv.project_id) as project_name, "
                                                    . " (SELECT name from users where id=dv.confirmed_by) as verifier, "
                                                    . " (SELECT TIMESTAMPDIFF(HOUR,NOW(),dv.date_end)) as hours, "
                                                    . " (SELECT appearance_name_en from disease_bank where id=dv.disease_id) as disease_name, "
                                                    . " (SELECT disease_color from disease_bank where id=dv.disease_id) as disease_color, "
                                                    . " dv.surveillance_form_instance_id, dv.project_id, dv.disease_id, dv.date_start, dv.date_end, dv.created_at, dv.confirmation_status "
                                                    . " FROM disease_verifications dv "
                                            . " Where dv.notified IS NULL AND dv.confirmation_level IS NOT NULL AND (dv.confirmation_status is null or dv.confirmation_status <> 'discard') "
                                            . " GROUP BY dv.project_id, dv.surveillance_form_instance_id Having hours < 0"));

            $verificationList = ['DL'=>'District Level', 'LL'=>'Laboratory Level', 'CL'=>'Clinic Level', 'HL'=>'Higher Verifier Level'];
            $levelIdsList = ['DL'=>13,'LL'=>14,'CL'=>15,'HL'=>16];

            foreach($query as $key => $dataObj)
            {
                if(isset($dataObj->project_id))
                {
                    $project = Project::find($dataObj->project_id);
                    $collector = \App\Models\User::find($dataObj->user_id);
                    $user = \App\Models\User::find($project->created_by);
                    $levelId = (int)@$levelIdsList[$dataObj->confirmation_level];

                    $userIds = \App\Models\ProjectUserTitles::where("project_id",$dataObj->project_id)->where("title_id",$levelId)->pluck("user_id","user_id")->toArray();
                    $verifiers = \App\Models\User::whereIn("id",$userIds)->pluck("name","name")->toArray();

                    $view = 'emails.daily-notifications';
                    $data['user'] = $user->name;
                    $data['formId'] = $dataObj->surveillance_form_instance_id;
                    $data['diseaseName'] = $dataObj->disease_name;
                    $data['projectName'] = $dataObj->project_name;
                    $data['verifierName'] = implode(",", $verifiers); //$dataObj->verifier;
                    $data['verificationLevel'] = @$verificationList[$dataObj->confirmation_level];
                    $subject = ' Exceeding the verification duration Alert - '.@$dataObj->project_name.' - ('.implode(",", $verifiers).') - '.@$verificationList[@$dataObj->confirmation_level];

                    Mail::send($view, $data, function ($message) use ($user, $collector, $subject) {
                        $message->from($collector->email, $collector->name);
                        //$message->replyTo($collector->email, $collector->name);
                        $message->to($user->email)->subject($subject);
                    });

                    $diseaseVerification = \App\Models\DiseaseVerification::find($dataObj->id);
                    $diseaseVerification->notified = 'Yes';
                    $diseaseVerification->save();
                }
            }
        }

        /*
        * send weekly Notifications
        **/
        function sendWeekEndNotifications()
        {
            $dateStart = date('Y-m-d 00:00:00', strtotime("this week"));
            $dateEnd = date("Y-m-d H:i:s");
            $projectIds = \App\Models\SurveillanceFormInstance::where("instance_type","collection")->whereBetween('created_at', [$dateStart, $dateEnd])->pluck("project_id", "project_id")->toArray();

            foreach($projectIds as $projectId)
            {
                $project = Project::find($projectId);
                $user = \App\Models\User::find($project->created_by);
                $userIds = \App\Models\SurveillanceFormInstance::where("project_id",$projectId)->where("instance_type","collection")->whereBetween('created_at', [$dateStart, $dateEnd])->pluck("user_id", "user_id")->toArray();
                foreach($userIds as $userId)
                {
                    $this->exportWeeklyCollections($projectId,$userId);
                    $collector = \App\Models\User::find($userId);
                    $clusterId = \App\Models\SurveillanceFormInstance::where("user_id",$userId)->where("project_id",$projectId)->where("instance_type","collection")->whereBetween('created_at', [$dateStart, $dateEnd])->first()->cluster_id;
                    $cluster = \App\Models\ClusterReference::find($clusterId);
                    $weekNo = (int)date('W', strtotime(date("Y-m-d")));

                    $view = 'emails.weekly-notifications';
                    $data['user'] = @$user->name;
                    $data['weekNo'] = $weekNo;
                    $data['projectName'] = @$project->name;
                    $data['collector'] = @$collector->name;
                    $data['clusterName'] = @$cluster->name;
                    $subject = ' Weekly Summary Report for Week#'.$weekNo.' - '.@$project->name.' - '.@$collector->name.' - '.@$cluster->name;

                    Mail::send($view, $data, function ($message) use ($user, $collector, $subject) {
                        $message->from($collector->email, $collector->name);
                        //$message->replyTo($collector->email, $collector->name);
                        $message->to($user->email)->subject($subject);
                        $message->attach(storage_path("tmp")."/collector-report.xls");
                    });
                }
            }
        }

        /*
        * send monthly Notifications
        **/
        function sendMonthEndNotifications()
        {
            $dateStart = date("Y-m-01 00:00:00");
            $dateEnd = date("Y-m-d H:i:s");
            $projectIds = \App\Models\SurveillanceFormInstance::where("instance_type","collection")->whereBetween('created_at', [$dateStart, $dateEnd])->pluck("project_id", "project_id")->toArray();

            foreach($projectIds as $projectId)
            {
                $project = Project::find($projectId);
                $user = \App\Models\User::find($project->created_by);
                $userIds = \App\Models\SurveillanceFormInstance::where("project_id",$projectId)->where("instance_type","collection")->whereBetween('created_at', [$dateStart, $dateEnd])->pluck("user_id", "user_id")->toArray();
                foreach($userIds as $userId)
                {
                    $this->exportMonthlyCollections($projectId,$userId);
                    $collector = \App\Models\User::find($userId);
                    $clusterId = \App\Models\SurveillanceFormInstance::where("user_id",$userId)->where("project_id",$projectId)->where("instance_type","collection")->whereBetween('created_at', [$dateStart, $dateEnd])->first()->cluster_id;
                    $cluster = \App\Models\ClusterReference::find($clusterId);
                    $weekNo = (int)date('W', strtotime(date("Y-m-d")));

                    $view = 'emails.monthly-notifications';
                    $data['user'] = @$user->name;
                    $data['monthNo'] = date("F");
                    $data['yearNo'] = date("Y");
                    $data['projectName'] = @$project->name;
                    $data['collector'] = @$collector->name;
                    $data['clusterName'] = @$cluster->name;
                    $subject = ' Monthly Summary Report for Week#'.$weekNo.' - '.@$project->name.' - '.@$collector->name.' - '.@$cluster->name;

                    Mail::send($view, $data, function ($message) use ($user, $collector, $subject) {
                        $message->from($collector->email, $collector->name);
                        //$message->replyTo($collector->email, $collector->name);
                        $message->to($user->email)->subject($subject);
                        $message->attach(storage_path("tmp")."/collector-report.xls");
                    });
                }
            }
        }

        /**
        * export user weekly Collections
        ***/
        public function exportWeeklyCollections($projectId,$userId)
        {
            $dataItems = $this->weeklyCollectionList($userId,$projectId);
            $additionalValue = (int)@$dataItems["additional"];
            $western_arabic = array('0','1','2','3','4','5','6','7','8','9');
            $eastern_arabic = array('٠','١','٢','٣','٤','٥','٦','٧','٨','٩');

            $diseaseColors = [];
            $excelData[] = [0=>'',1=>'',2=>'',3=>'',4=>'',5=>'',6=>'',7=>''];
            $excelData[] = [0=>'',1=>'',2=>'',3=>'',4=>'',5=>'',6=>'',7=>''];
            $excelData[] = [0=>'',1=>'',2=>'',3=>'',4=>'',5=>'',6=>'',7=>''];
            $excelData[] = [0=>'',1=>'',2=>'',3=>'',4=>'',5=>'',6=>'',7=>''];
            foreach($dataItems['records'] as $data){
               $excelData[] = [0=>'',1=>'',2=>'',3=>($data['category_en']."/ ".$data['category_ar']),4=>'',5=>'',6=>'',7=>''];
               $counter = 1;
               foreach($data['items'] as $item){
                   $diseaseColors["{$item['disease_en']}"] = $item['disease_color'];
                   $excelData[] = [0=>'',1=>($counter),2=>$item['disease_en'],3=>$item['male_below5'],4=>$item['female_below5'],5=>$item['male_above5'],6=>$item['female_above5'],7=>$item['disease_ar'],8=>str_replace($western_arabic, $eastern_arabic, $counter++)];
               }
            }
            $sumValues = $dataItems['total_male_below5'] + $dataItems['total_female_below5'] + $dataItems['total_male_above5'] + $dataItems['total_female_above5'] + $additionalValue;
            $excelData[] = [0=>'',1=>'',2=>'',3=>'',4=>'',5=>'',6=>'',7=>''];
            $excelData[] = [0=>'',1=>'',2=>'Total Number of All Consultation',3=>$dataItems['total_male_below5'],4=>$dataItems['total_female_below5'],5=>$dataItems['total_male_above5'],6=>$dataItems['total_female_above5'],7=>'عدد المراجعين الكلى'];
            $excelData[] = [0=>'',1=>'',2=>'Additional consultation',3=>'',4=>$additionalValue,5=>'',6=>'',7=>'عدد المراجعات الاخرى'];
            $excelData[] = [0=>'',1=>'',2=>'Total',3=>'',4=>$sumValues,5=>'',6=>'',7=>'مجموع الكلي'];

            \Excel::load(public_path()."/template/collector-report.xls", function ($excel) use ($excelData,$diseaseColors)
            {
                // creating the sheet and filling it with questions data
                    $excel->sheet(//black, red, orange , green , blue
                        'Collections', function ($sheet) use ($excelData,$diseaseColors) {
                            //$sheet->rows($excelData);
                            $colorList = ['red'=>'FF0000', 'blue'=>'0000FF', 'orange'=>'FFA500', 'green'=>'008000', 'black'=>'000000'];
                            for($i = 1 ; $i <= count($excelData); $i++)
                            {
                                if($i < 4)
                                    continue;

                                $data = @$excelData[$i];
                                $color = @$diseaseColors[@$data[2]];
                                $color = ($color != ""?$colorList[$color]:"000000");

                                $sheet->cells('A'.$i, function($cells) use ($data) {
                                    $cells->setValue(@$data[0]);
                                });
                                $sheet->cells('B'.$i, function($cells) use ($data) {
                                    $cells->setValue(@$data[1]);
                                });
                                $sheet->cells('C'.$i, function($cells) use ($data, $color) {
                                    $cells->setValue(@$data[2]);
                                    $cells->setFontColor("{$color}");
                                });
                                $sheet->cells('D'.$i, function($cells) use ($data) {
                                    $cells->setValue(@$data[3]);
                                });
                                $sheet->cells('E'.$i, function($cells) use ($data) {
                                    $cells->setValue(@$data[4]);
                                });
                                $sheet->cells('F'.$i, function($cells) use ($data) {
                                    $cells->setValue(@$data[5]);
                                });
                                $sheet->cells('G'.$i, function($cells) use ($data) {
                                    $cells->setValue(@$data[6]);
                                });
                                $sheet->cells('H'.$i, function($cells) use ($data, $color) {
                                    $cells->setValue(@$data[7]);
                                    $cells->setFontColor("{$color}");
                                });
                                $sheet->cells('I'.$i, function($cells) use ($data) {
                                    $cells->setValue(@$data[8]);
                                });
                            }
                        }
                    );

            })->store('xls', storage_path('tmp'));
        }

        /**
        * user weekly Collection listing
        **/
        public function weeklyCollectionList($userId=0,$projectId=0)
        {
            $dateStart = date('Y-m-d 00:00:00', strtotime("this week"));
            $dateEnd = date("Y-m-d H:i:s");

            $reportAdditional = \App\Models\SurveillanceReportAdditional::where("project_id",$projectId)
                                                            ->where("user_id",$userId)
                                                            ->whereBetween('created_at', [$dateStart, $dateEnd])
                                                            ->sum("additional");

            $form = \App\Models\Form::where("project_id", $projectId)->first();
            $diseaseAll = \App\Models\DiseaseDetail::where("project_id", $projectId)->pluck("disease_id","disease_id")->toArray();
            $collectedDiseases = \App\Models\SurveillanceFormInstance::where("project_id",$projectId)->where("user_id", $userId)->where("instance_type","collection")->whereBetween('created_at', [$dateStart, $dateEnd])->pluck("disease_id", "disease_id")->toArray();
            $categoryIds = \App\Models\DiseaseBank::whereIn("id",$diseaseAll)->pluck("disease_category_id","disease_category_id")->toArray();
            $categories = \App\Models\DiseaseCategory::whereIn("id", $categoryIds)->get();

            $itemList = [];
            $totalMaleBelow5 = 0;
            $totalMaleAbove5 = 0;
            $totalFemaleBelow5 = 0;
            $totalFemaleAbove5 = 0;
            foreach($categories as $category)
            {
                $categoryDiseases = \App\Models\DiseaseBank::where("disease_category_id","!=",$category->id)->pluck("id","id")->toArray();
                $categoryDiseases = array_diff($diseaseAll, $categoryDiseases);
                $diseaseCountList = [];
                foreach($categoryDiseases as $diseaseId)
                {
                    $maleAgeBelow5 = 0;
                    $maleAgeAbove5 = 0;
                    $femaleAgeBelow5 = 0;
                    $femaleAgeAbove5 = 0;
                    $disease = \App\Models\DiseaseBank::find($diseaseId);
                    $userInstances = \App\Models\SurveillanceFormInstance::where("project_id",$projectId)->where("user_id", $userId)->where("disease_id", $diseaseId)->where("instance_type","collection")->whereBetween('created_at', [$dateStart, $dateEnd])->orderBy("project_id")->get();

                    foreach($userInstances as $instance){
                        $form = \App\Models\Form::where("project_id",$instance->project_id)->first();
                        $formQuestion1 = \App\Models\Question::with("options")->where("form_id",$form->id)->where("name_en","LIKE","Gender?")->where("response_type_id",1)->first();
                        $formQuestion2 = \App\Models\Question::where("form_id",$form->id)->where("name_en","LIKE","Age?")->where("response_type_id",4)->first();

                        $maleOptionId = 0;
                        $femaleOptionId = 0;
                        if(isset($formQuestion1->options)){
                            foreach($formQuestion1->options as $option){
                                if($option->name_en == 'Female')
                                    $femaleOptionId = $option->id;
                                else
                                    $maleOptionId = $option->id;
                            }
                        }

                        $malCount = isset($formQuestion1->id)?\App\Models\SurveillanceQuestionAnswer::where("surveillance_form_instance_id",$instance->id)->where("question_id",$formQuestion1->id)->where("value",$maleOptionId)->count():0;
                        $femaleCount = isset($formQuestion1->id)?\App\Models\SurveillanceQuestionAnswer::where("surveillance_form_instance_id",$instance->id)->where("question_id",$formQuestion1->id)->where("value",$femaleOptionId)->count():0;
                        $age = (int)@\App\Models\SurveillanceQuestionAnswer::where("surveillance_form_instance_id",$instance->id)->where("question_id",$formQuestion2->id)->pluck("value")->first();

                        if($femaleCount > 0 && $age <= 5)
                            $femaleAgeBelow5 ++;
                        else if($femaleCount > 0 && $age > 5)
                            $femaleAgeAbove5 ++;
                        else if($malCount > 0 && $age <= 5)
                            $maleAgeBelow5 ++;
                        else
                            $maleAgeAbove5 ++;
                    }

                    $maleAgeBelow5 += (int)@SurveillanceReportCounter::where("project_id",$projectId)->where("user_id",$userId)->where("disease_id",$diseaseId)->whereBetween('created_at', [$dateStart, $dateEnd])->where("gender","Male")->where("age",4)->sum("number");
                    $maleAgeAbove5 += (int)@SurveillanceReportCounter::where("project_id",$projectId)->where("user_id",$userId)->where("disease_id",$diseaseId)->whereBetween('created_at', [$dateStart, $dateEnd])->where("gender","Male")->where("age",6)->sum("number");
                    $femaleAgeBelow5 += (int)@SurveillanceReportCounter::where("project_id",$projectId)->where("user_id",$userId)->where("disease_id",$diseaseId)->whereBetween('created_at', [$dateStart, $dateEnd])->where("gender","Female")->where("age",4)->sum("number");
                    $femaleAgeAbove5 += (int)@SurveillanceReportCounter::where("project_id",$projectId)->where("user_id",$userId)->where("disease_id",$diseaseId)->whereBetween('created_at', [$dateStart, $dateEnd])->where("gender","Female")->where("age",6)->sum("number");

                    $totalMaleBelow5 += $maleAgeBelow5;
                    $totalMaleAbove5 += $maleAgeAbove5;
                    $totalFemaleBelow5 += $femaleAgeBelow5;
                    $totalFemaleAbove5 += $femaleAgeAbove5;

                    $diseaseCountList[] = ['disease_id'=>$diseaseId,'disease_en'=>$disease->appearance_name_en,'disease_ar'=>$disease->appearance_name_ar,'disease_color'=>$disease->disease_color,'female_below5'=>$femaleAgeBelow5,'female_above5'=>$femaleAgeAbove5,'male_below5'=>$maleAgeBelow5,'male_above5'=>$maleAgeAbove5];
                }
                $itemList[] = ['category_en'=>$category->category_en,'category_ar'=>$category->category_ar,'items'=>$diseaseCountList];
            }

            $totalItems = ['total_female_below5'=>$totalFemaleBelow5,'total_male_below5'=>$totalMaleBelow5,'total_female_above5'=>$totalFemaleAbove5,'total_male_above5'=>$totalMaleAbove5,'additional'=>(int)@$reportAdditional,'records'=>$itemList];

            return $totalItems;
        }

        /**
        * export user monthly Collections
        ***/
        public function exportMonthlyCollections($projectId,$userId)
        {
            $dataItems = $this->monthlyCollectionList($userId,$projectId);
            $additionalValue = (int)@$dataItems["additional"];
            $western_arabic = array('0','1','2','3','4','5','6','7','8','9');
            $eastern_arabic = array('٠','١','٢','٣','٤','٥','٦','٧','٨','٩');

            $diseaseColors = [];
            $excelData[] = [0=>'',1=>'',2=>'',3=>'',4=>'',5=>'',6=>'',7=>''];
            $excelData[] = [0=>'',1=>'',2=>'',3=>'',4=>'',5=>'',6=>'',7=>''];
            $excelData[] = [0=>'',1=>'',2=>'',3=>'',4=>'',5=>'',6=>'',7=>''];
            $excelData[] = [0=>'',1=>'',2=>'',3=>'',4=>'',5=>'',6=>'',7=>''];
            foreach($dataItems['records'] as $data){
               $excelData[] = [0=>'',1=>'',2=>'',3=>($data['category_en']."/ ".$data['category_ar']),4=>'',5=>'',6=>'',7=>''];
               $counter = 1;
               foreach($data['items'] as $item){
                   $diseaseColors["{$item['disease_en']}"] = $item['disease_color'];
                   $excelData[] = [0=>'',1=>($counter),2=>$item['disease_en'],3=>$item['male_below5'],4=>$item['female_below5'],5=>$item['male_above5'],6=>$item['female_above5'],7=>$item['disease_ar'],8=>str_replace($western_arabic, $eastern_arabic, $counter++)];
               }
            }
            $sumValues = $dataItems['total_male_below5'] + $dataItems['total_female_below5'] + $dataItems['total_male_above5'] + $dataItems['total_female_above5'] + $additionalValue;
            $excelData[] = [0=>'',1=>'',2=>'',3=>'',4=>'',5=>'',6=>'',7=>''];
            $excelData[] = [0=>'',1=>'',2=>'Total Number of All Consultation',3=>$dataItems['total_male_below5'],4=>$dataItems['total_female_below5'],5=>$dataItems['total_male_above5'],6=>$dataItems['total_female_above5'],7=>'عدد المراجعين الكلى'];
            $excelData[] = [0=>'',1=>'',2=>'Additional Consultation',3=>'',4=>$additionalValue,5=>'',6=>'',7=>'عدد المراجعات الاخرى'];
            $excelData[] = [0=>'',1=>'',2=>'Total',3=>'',4=>$sumValues,5=>'',6=>'',7=>'مجموع الكلي'];

            \Excel::load(public_path()."/template/collector-report.xls", function ($excel) use ($excelData,$diseaseColors)
            {
                // creating the sheet and filling it with questions data
                    $excel->sheet(//black, red, orange , green , blue
                        'Collections', function ($sheet) use ($excelData,$diseaseColors) {
                            //$sheet->rows($excelData);
                            $colorList = ['red'=>'FF0000', 'blue'=>'0000FF', 'orange'=>'FFA500', 'green'=>'008000', 'black'=>'000000'];
                            for($i = 1 ; $i <= count($excelData); $i++)
                            {
                                if($i < 4)
                                    continue;

                                $data = @$excelData[$i];
                                $color = @$diseaseColors[@$data[2]];
                                $color = ($color != ""?$colorList[$color]:"000000");

                                $sheet->cells('A'.$i, function($cells) use ($data) {
                                    $cells->setValue(@$data[0]);
                                });
                                $sheet->cells('B'.$i, function($cells) use ($data) {
                                    $cells->setValue(@$data[1]);
                                });
                                $sheet->cells('C'.$i, function($cells) use ($data, $color) {
                                    $cells->setValue(@$data[2]);
                                    $cells->setFontColor("{$color}");
                                });
                                $sheet->cells('D'.$i, function($cells) use ($data) {
                                    $cells->setValue(@$data[3]);
                                });
                                $sheet->cells('E'.$i, function($cells) use ($data) {
                                    $cells->setValue(@$data[4]);
                                });
                                $sheet->cells('F'.$i, function($cells) use ($data) {
                                    $cells->setValue(@$data[5]);
                                });
                                $sheet->cells('G'.$i, function($cells) use ($data) {
                                    $cells->setValue(@$data[6]);
                                });
                                $sheet->cells('H'.$i, function($cells) use ($data, $color) {
                                    $cells->setValue(@$data[7]);
                                    $cells->setFontColor("{$color}");
                                });
                                $sheet->cells('I'.$i, function($cells) use ($data) {
                                    $cells->setValue(@$data[8]);
                                });
                            }
                        }
                    );
            })->store('xls', storage_path('tmp'));
        }

        /*
        * user monthly Collection listing
        * */
        public function monthlyCollectionList($userId=0,$projectId=0)
        {
            $dateStart = date("Y-m-01 00:00:00");
            $dateEnd = date("Y-m-d H:i:s");

            $reportAdditional = \App\Models\SurveillanceReportAdditional::where("project_id",$projectId)
                                                            ->where("user_id",$userId)
                                                            ->whereBetween('created_at', [$dateStart, $dateEnd])
                                                            ->sum("additional");

            $form = \App\Models\Form::where("project_id", $projectId)->first();
            $diseaseAll = \App\Models\DiseaseDetail::where("project_id", $projectId)->pluck("disease_id","disease_id")->toArray();
            $collectedDiseases = \App\Models\SurveillanceFormInstance::where("project_id",$projectId)->where("user_id", $userId)->where("instance_type","collection")->whereBetween('created_at', [$dateStart, $dateEnd])->pluck("disease_id", "disease_id")->toArray();
            $categoryIds = \App\Models\DiseaseBank::whereIn("id",$diseaseAll)->pluck("disease_category_id","disease_category_id")->toArray();
            $categories = \App\Models\DiseaseCategory::whereIn("id", $categoryIds)->get();

            $itemList = [];
            $totalMaleBelow5 = 0;
            $totalMaleAbove5 = 0;
            $totalFemaleBelow5 = 0;
            $totalFemaleAbove5 = 0;
            foreach($categories as $category)
            {
                $categoryDiseases = \App\Models\DiseaseBank::where("disease_category_id","!=",$category->id)->pluck("id","id")->toArray();
                $categoryDiseases = array_diff($diseaseAll, $categoryDiseases);
                $diseaseCountList = [];
                foreach($categoryDiseases as $diseaseId)
                {
                    $maleAgeBelow5 = 0;
                    $maleAgeAbove5 = 0;
                    $femaleAgeBelow5 = 0;
                    $femaleAgeAbove5 = 0;
                    $disease = \App\Models\DiseaseBank::find($diseaseId);
                    $userInstances = \App\Models\SurveillanceFormInstance::where("project_id",$projectId)->where("user_id", $userId)->where("disease_id", $diseaseId)->where("instance_type","collection")->whereBetween('created_at', [$dateStart, $dateEnd])->orderBy("project_id")->get();

                    foreach($userInstances as $instance){
                        $form = \App\Models\Form::where("project_id",$instance->project_id)->first();
                        $formQuestion1 = \App\Models\Question::with("options")->where("form_id",$form->id)->where("name_en","LIKE","Gender?")->where("response_type_id",1)->first();
                        $formQuestion2 = \App\Models\Question::where("form_id",$form->id)->where("name_en","LIKE","Age?")->where("response_type_id",4)->first();

                        $maleOptionId = 0;
                        $femaleOptionId = 0;
                        if(isset($formQuestion1->options)){
                            foreach($formQuestion1->options as $option){
                                if($option->name_en == 'Female')
                                    $femaleOptionId = $option->id;
                                else
                                    $maleOptionId = $option->id;
                            }
                        }

                        $malCount = isset($formQuestion1->id)?\App\Models\SurveillanceQuestionAnswer::where("surveillance_form_instance_id",$instance->id)->where("question_id",$formQuestion1->id)->where("value",$maleOptionId)->count():0;
                        $femaleCount = isset($formQuestion1->id)?\App\Models\SurveillanceQuestionAnswer::where("surveillance_form_instance_id",$instance->id)->where("question_id",$formQuestion1->id)->where("value",$femaleOptionId)->count():0;
                        $age = (int)@\App\Models\SurveillanceQuestionAnswer::where("surveillance_form_instance_id",$instance->id)->where("question_id",$formQuestion2->id)->pluck("value")->first();

                        if($femaleCount > 0 && $age <= 5)
                            $femaleAgeBelow5 ++;
                        else if($femaleCount > 0 && $age > 5)
                            $femaleAgeAbove5 ++;
                        else if($malCount > 0 && $age <= 5)
                            $maleAgeBelow5 ++;
                        else
                            $maleAgeAbove5 ++;
                    }

                    $maleAgeBelow5 += (int)@SurveillanceReportCounter::where("project_id",$projectId)->where("user_id",$userId)->where("disease_id",$diseaseId)->whereBetween('created_at', [$dateStart, $dateEnd])->where("gender","Male")->where("age",4)->sum("number");
                    $maleAgeAbove5 += (int)@SurveillanceReportCounter::where("project_id",$projectId)->where("user_id",$userId)->where("disease_id",$diseaseId)->whereBetween('created_at', [$dateStart, $dateEnd])->where("gender","Male")->where("age",6)->sum("number");
                    $femaleAgeBelow5 += (int)@SurveillanceReportCounter::where("project_id",$projectId)->where("user_id",$userId)->where("disease_id",$diseaseId)->whereBetween('created_at', [$dateStart, $dateEnd])->where("gender","Female")->where("age",4)->sum("number");
                    $femaleAgeAbove5 += (int)@SurveillanceReportCounter::where("project_id",$projectId)->where("user_id",$userId)->where("disease_id",$diseaseId)->whereBetween('created_at', [$dateStart, $dateEnd])->where("gender","Female")->where("age",6)->sum("number");

                    $totalMaleBelow5 += $maleAgeBelow5;
                    $totalMaleAbove5 += $maleAgeAbove5;
                    $totalFemaleBelow5 += $femaleAgeBelow5;
                    $totalFemaleAbove5 += $femaleAgeAbove5;

                    $diseaseCountList[] = ['disease_id'=>$diseaseId,'disease_en'=>$disease->appearance_name_en,'disease_ar'=>$disease->appearance_name_ar,'disease_color'=>$disease->disease_color,'female_below5'=>$femaleAgeBelow5,'female_above5'=>$femaleAgeAbove5,'male_below5'=>$maleAgeBelow5,'male_above5'=>$maleAgeAbove5];
                }
                $itemList[] = ['category_en'=>$category->category_en,'category_ar'=>$category->category_ar,'items'=>$diseaseCountList];
            }

            $totalItems = ['total_female_below5'=>$totalFemaleBelow5,'total_male_below5'=>$totalMaleBelow5,'total_female_above5'=>$totalFemaleAbove5,'total_male_above5'=>$totalMaleAbove5,'additional'=>(int)@$reportAdditional,'records'=>$itemList];

            return $totalItems;
        }
}
