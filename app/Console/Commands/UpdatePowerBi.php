<?php
/**
 * Update results crone
 *
 * (c) RehmatUllah <rehmatullahbhatti@gmail.com>
 *
 */

namespace App\Console\Commands;

use App\Models\FormCategory;
use App\Models\Project;
use App\Models\Question;
use App\Models\PowerBiConsultation;
use App\Models\Indicator;
use App\Models\PowerBiData;
use App\Models\PowerBiAlert;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\SurveillanceFormInstance;


class UpdatePowerBi extends Command
{
        /**
         * The console command name.
         *
         * @var string
         */
        protected $name = 'update:pbi';

        /**
         * The console command description.
         *
         * @var string
         */
        protected $description = 'Update Power Bi Tables!';

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
                Log::info('------Starting Updating Power Bi Tables!------'); // for lumen.log

                PowerBiData::truncate();
                PowerBiAlert::truncate();
                PowerBiConsultation::truncate();

                $minDate = SurveillanceFormInstance::min("created_at");
                $maxDate = SurveillanceFormInstance::max("created_at");
                //$weekStart = (int)date('W', strtotime($minDate));
                //$weekEnd = (int)date('W', strtotime($maxDate));
                $yearStart = (int)date('Y', strtotime($minDate));
                $yearEnd = (int)date('Y', strtotime($maxDate));
                
                $governorates = \App\Models\Governorate::pluck("name","id")->toArray();
                $districts = \App\Models\District::pluck("name","id")->toArray();
                $sites = \App\Models\SiteReference::pluck("name","id")->toArray();
                $clusters = \App\Models\ClusterReference::pluck("name","id")->toArray();
                $userAgencyList = \App\Models\User::pluck("reporting_agency","id")->toArray();
                $confirmationList = ['DL'=>'District Level Confirmed','LL'=>'Laboratory Confirmed','CL'=>'Clinically confirmed','HL'=>'Higher Verifier Confirmed','D'=>'Discarded'];
                $diseases = \App\Models\DiseaseBank::get();
                
                for($weekNo=1;$weekNo<=52;$weekNo++)
                {
                    $dates = $this->startEndDateOfWeek($weekNo, $yearStart);
                    $startDate = @$dates[0]." 00:00:00";
                    $endDate = @$dates[1]." 23:59:59";
                                        
                    //get data and alers pbi
                    foreach($diseases as $disease)
                    {
                        $data = DB::select(" Select project_id, governorate_id, district_id, site_id, cluster_id,  GROUP_CONCAT(id SEPARATOR ',') as instanceIds, lat, lng, count(1) as value, user_id, "
                                ." (SELECT name from projects where id = surveillance_form_instances.project_id Limit 1) as project_name "
                                ." From surveillance_form_instances "
                                ." Where disease_id = {$disease->id} AND deleted_at IS NULL AND created_at Between '$startDate' AND '$endDate' " 
                                ." Group by project_id, cluster_id, user_id");
                                
                        $project = (@$data[0]->project_name == "")?"Dummy Project":@$data[0]->project_name;    
                        $projectId = @$data[0]->project_id;
                        $governorateId = @$data[0]->governorate_id;
                        $districtId = @$data[0]->district_id;
                        $siteId = @$data[0]->site_id;
                        $clusterId = @$data[0]->cluster_id;
                        $instanceIds = @$data[0]->instanceIds;
                        $instanceIds = ($instanceIds != "")?explode(",",$instanceIds):[];
                        $lat = (@$data[0]->lat == "")?"":@$data[0]->lat;
                        $lng = (@$data[0]->lng == "")?"":@$data[0]->lng;
                        $value = (int)@$data[0]->value;
                        $userId = @$data[0]->user_id;
                        $governorate = ((@$governorates[$governorateId] == "")?"Al Anbar":@$governorates[$governorateId]);
                        $district = ((@$districts[$districtId] == "")?"Ana":@$districts[$districtId]);
                        $site = ((@$sites[$siteId] == "")?"Ana":@$sites[$siteId]);
                        $cluster = ((@$clusters[$clusterId] == "")?"First Market":@$clusters[$clusterId]);
                        $agency = ((@$userAgencyList[$userId] == "")?"none":@$userAgencyList[$userId]);
                        $month = date('n', strtotime("{$yearStart}-W{$weekNo}"));
                        $form = \App\Models\Form::where("project_id", $projectId)->first();
                        
                        if($form)
                        {
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
                        }
                        
                        $age = 0;
                        $ageBelow5 = 0;
                        $ageAbove5 = 0;
                        $malCount = 0;
                        $femaleCount = 0;
                        
                        foreach($instanceIds as $instanceId)
                        {
                            $males = isset($formQuestion1->id)?\App\Models\SurveillanceQuestionAnswer::where("surveillance_form_instance_id",$instanceId)->where("question_id",$formQuestion1->id)->where("value",$maleOptionId)->count():0;
                            $females = isset($formQuestion1->id)?\App\Models\SurveillanceQuestionAnswer::where("surveillance_form_instance_id",$instanceId)->where("question_id",$formQuestion1->id)->where("value",$femaleOptionId)->count():0;
                            $age = (int)@\App\Models\SurveillanceQuestionAnswer::where("surveillance_form_instance_id",$instanceId)->where("question_id",$formQuestion2->id)->pluck("value")->first();

                            if($females>$males)                                
                                $femaleCount ++;
                            else
                                $malCount ++;
                            
                            if($age <= 5)
                                $ageBelow5 ++;
                            else
                                $ageAbove5 ++;
                            
                            //save all the instances
                            if(!empty($governorate) && !empty($district) && !empty($site) && !empty($cluster))
                            {
                                $verification = \App\Models\DiseaseVerification::where("surveillance_form_instance_id", $instanceId)->whereNotNull("confirmation_level")->orderBy("updated_at", "DESC")->first();
                                
                                if(isset($verification->confirmation_status) && $verification->confirmation_status == 'discard')
                                    $confirmationLevel = 'D';
                                else
                                   $confirmationLevel = isset($verification->confirmation_level)?$verification->confirmation_level:"DL"; 
                                
                                $pbiAlert = new PowerBiAlert();
                                $pbiAlert->project = $project;
                                $pbiAlert->governorate = $governorate;
                                $pbiAlert->district = $district;
                                $pbiAlert->site = $site;
                                $pbiAlert->cluster = $cluster;
                                $pbiAlert->unique_district = $district.$districtId;
                                $pbiAlert->unique_site = $site.$siteId;
                                $pbiAlert->unique_cluster = $cluster.$clusterId;
                                $pbiAlert->gender = ($femaleCount>$malCount?"Female":"Male");
                                $pbiAlert->lat = $lat;
                                $pbiAlert->long = $lng;
                                $pbiAlert->week = "W".str_pad($weekNo, 2, '0', STR_PAD_LEFT);
                                $pbiAlert->week_no = $weekNo;
                                $pbiAlert->month = $month;
                                $pbiAlert->year = $yearStart;
                                $pbiAlert->disease = $disease->appearance_name_en;
                                $pbiAlert->disease_type = $disease->disease_type;
                                $pbiAlert->user_agency = $agency;                                
                                $pbiAlert->inv_result = (@$confirmationList[$confirmationLevel] == ""?'District Level Confirmed':@$confirmationList[$confirmationLevel]);
                                $pbiAlert->date_of_collection = isset($verification)?$verification->date_start:date("Y-m-d H:i:s");
                                $pbiAlert->date_of_result = isset($verification)?$verification->date_end:date("Y-m-d H:i:s");
                                $pbiAlert->save();
                            }
                            else
                            {
                                $pbiAlert = new PowerBiAlert();
                                $pbiAlert->governorate = "Al Anbar";
                                $pbiAlert->district = "Ana";
                                $pbiAlert->site = "Ana";
                                $pbiAlert->cluster = "First Market";
                                $pbiAlert->unique_district = "Ana";
                                $pbiAlert->unique_site = "Ana";
                                $pbiAlert->unique_cluster = "First Market";
                                $pbiAlert->gender = ($femaleCount>$malCount?"Female":"Male");
                                $pbiAlert->lat = "33.2232";
                                $pbiAlert->long = "43.6793";
                                $pbiAlert->week = "W".str_pad($weekNo, 2, '0', STR_PAD_LEFT);
                                $pbiAlert->week_no = $weekNo;
                                $pbiAlert->month = $month;
                                $pbiAlert->year = $yearStart;
                                $pbiAlert->disease = "Non Sepecified";
                                $pbiAlert->disease_type = "Non Sepecified";
                                $pbiAlert->user_agency = "none";     
                                $pbiAlert->inv_result = 'District Level Confirmed';
                                $pbiAlert->date_of_collection = date("Y-m-d H:i:s");
                                $pbiAlert->date_of_result = date("Y-m-d H:i:s");
                                $pbiAlert->save();
                            }
                        }
                        
                        if(!empty($governorate) && !empty($district) && !empty($site) && !empty($cluster))
                        {
                            $pbiData = new PowerBiData();
                            $pbiData->project = $project;
                            $pbiData->governorate = $governorate;
                            $pbiData->district = $district;
                            $pbiData->site = $site;
                            $pbiData->cluster = $cluster;
                            $pbiData->unique_district = $district.$districtId;
                            $pbiData->unique_site = $site.$siteId;
                            $pbiData->unique_cluster = $cluster.$clusterId;
                            $pbiData->age = ($age>5)?"Age>5":"Age<5";
                            $pbiData->age_less_than_5 = $ageBelow5;
                            $pbiData->age_greater_than_5 = $ageAbove5;
                            $pbiData->gender = ($femaleCount>$malCount?"Female":"Male");
                            $pbiData->male = $malCount;
                            $pbiData->female = $femaleCount;
                            $pbiData->lat = $lat;
                            $pbiData->long = $lng;
                            $pbiData->week = "W".str_pad($weekNo, 2, '0', STR_PAD_LEFT);
                            $pbiData->week_no = $weekNo;
                            $pbiData->month = $month;
                            $pbiData->year = $yearStart;
                            $pbiData->disease = $disease->appearance_name_en;
                            $pbiData->disease_type = $disease->disease_type;
                            $pbiData->value = $value;
                            $pbiData->user_agency = $agency;                        
                            $pbiData->save();
                        }
                    }
                    
                    //Get all Consultations pbi
                    $data = DB::select(" Select (SELECT name from users where id = surveillance_form_instances.user_id Limit 1) as collector, "
                            . " (SELECT name from projects where id = surveillance_form_instances.project_id Limit 1) as project_name, "
                            . " (SELECT appearance_name_en from disease_bank where id = surveillance_form_instances.disease_id Limit 1) as disease, "
                            . " project_id, user_id, governorate_id, district_id, site_id, cluster_id,  GROUP_CONCAT(id SEPARATOR ',') as instanceIds, count(1) as value "
                        ." from surveillance_form_instances ".
                        " where deleted_at IS NULL AND instance_type='collection' AND created_at Between '$startDate' AND '$endDate' ".
                        " group by project_id, disease_id, user_id, cluster_id");

                    $project = (@$data[0]->project_name == "")?"Dummy Project":@$data[0]->project_name;
                    $projectId = @$data[0]->project_id;
                    $governorateId = @$data[0]->governorate_id;
                    $districtId = @$data[0]->district_id;
                    $siteId = @$data[0]->site_id;
                    $clusterId = @$data[0]->cluster_id;
                    $user = @$data[0]->collector;
                    $value = (int)@$data[0]->value;
                    $instanceIds = @$data[0]->instanceIds;
                    $disease = @$data[0]->disease;
                    $instanceIds = ($instanceIds != "")?explode(",",$instanceIds):[];
                    $governorate = ((@$governorates[$governorateId] == "")?"Al Anbar":@$governorates[$governorateId]);
                    $district = ((@$districts[$districtId] == "")?"Ana":@$districts[$districtId]);
                    $site = ((@$sites[$siteId] == "")?"Ana":@$sites[$siteId]);
                    $cluster = ((@$clusters[$clusterId] == "")?"First Market":@$clusters[$clusterId]);
                    $totalValue = (int)@\App\Models\SurveillanceReportCounter::whereBetween('created_at', [$startDate, $endDate])->sum("number") + $value;
                    $month = date('n', strtotime("{$yearStart}-W{$weekNo}"));

                    $pbiConsultation = new PowerBiConsultation();
                    $pbiConsultation->project = $project;
                    $pbiConsultation->governorate = $governorate;
                    $pbiConsultation->district = $district;
                    $pbiConsultation->site = $site;
                    $pbiConsultation->cluster = $cluster;
                    $pbiConsultation->disease = ($disease == "")?'Non-specified-disease':$disease;
                    $pbiConsultation->collector = ($user == "")?"Charly Chakhtoura":$user;
                    $pbiConsultation->unique_district = $district.$districtId;
                    $pbiConsultation->unique_site = $site.$siteId;
                    $pbiConsultation->unique_cluster = $cluster.$clusterId;
                    $pbiConsultation->week = "W".str_pad($weekNo, 2, '0', STR_PAD_LEFT);
                    $pbiConsultation->week_no = $weekNo;
                    $pbiConsultation->month = $month;
                    $pbiConsultation->year = $yearStart;
                    $pbiConsultation->total_consultations = $totalValue;
                    $pbiConsultation->save();                        
                }
                
        }//function ends
        
        /*
        * get end and start date of week
        **/
        function startEndDateOfWeek($week, $year)
        {
            $time = strtotime("1 January $year", time());
            $day = date('w', $time);
            $time += ((7*$week)+1-$day)*24*3600;
            $dates[0] = date('Y-m-d', $time);
            $time += 6*24*3600;
            $dates[1] = date('Y-m-d', $time);

            return $dates;
        }
}
