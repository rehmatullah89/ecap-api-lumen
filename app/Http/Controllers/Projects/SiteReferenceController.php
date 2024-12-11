<?php

/*
 * This file is part of the IdeaToLife package.
 *
 * (c) Youssef Jradeh <youssef.jradeh@ideatolife.me>
 *
 */

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\WhoController;
use App\Models\SiteReference;
use App\Models\ProjectDetail;
use App\Models\ProjectLocationDetail;
use App\Models\ClusterReference;
use App\Models\IndicatorResults;
use App\Models\FormInstance;
use Idea\Helpers\Paging;

class SiteReferenceController extends WhoController
{
    
    public $filePath = "sites/";
    
    protected $permissions = [
    "index"   => ["code" => "site_configuration", "action" => "read"],
    "one"     => ["code" => "site_configuration", "action" => "read"],
    "store"   => ["code" => "site_configuration", "action" => "write"],
    "update"  => ["code" => "site_configuration", "action" => "write"],
    "destroy" => ["code" => "site_configuration", "action" => "write"],
    ];
    
    /**
     *
     * @return array
     */
    protected static function validationRules() 
    {
        return [
        'store'  => [
        "name"           => "required|unique:site_references,name",
        'governorate_id' => 'required|exists:governorates,id',    
        'district_id' => 'required|exists:districts,id',    
        ],
        'update' => [
        "name"           => "required",
        'governorate_id' => 'required|exists:governorates,id',        
        'district_id' => 'required|exists:districts,id',    
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
        $searchQuery        = !empty(@request('query')) ? request('query') : "";
        $districtId         = (@request("district_id")>0)?@request("district_id"):0;
        $governorateId      = (@request("governorate_id")>0)?@request("governorate_id"):0;
        $governorateFilter  = (@request("governorate_id")>0)?'=':'!=';
        
        // if user is guest or super_guest then only show the sites to which he/she belongs
        if($this->user && ($this->user->hasRole('guest') || $this->user->hasRole('super_guest'))) {
            $userId = $this->user->id;
            $query = SiteReference::where('governorate_id', $governorateFilter, $governorateId)
            ->where(function ($q) use ($searchQuery) {
                $q->where('name', 'LIKE', "%" . $searchQuery . "%");                
            })        
            ->whereHas(
                'guestSites', function ($query) use ($userId) {
                    $query->where('user_id', $userId);
                }
            )            
            ->with(['clusters' => function ($searchQuery) {
                $query->where('name', 'like', "%" . $searchQuery . "%");
            }, "governorate", "district"]);
        }else{
            $query = SiteReference::where('governorate_id', $governorateFilter, $governorateId)
            ->where(function ($q) use ($searchQuery) {
                $q->where('name', 'LIKE', "%" . $searchQuery . "%");
                $q->orWhereHas(
                        'clusters', function ($q2) use ($searchQuery) {
                        $q2->where('name', 'LIKE', "%" . $searchQuery . "%");
                    }
                );
            })        
            ->with(['clusters' => function ($q) use ($searchQuery) {
                $q->where('name', 'like', "%" . $searchQuery . "%");
            }, "governorate", "district"]);
        }

        if($districtId > 0)
            $query->where("district_id", $districtId);
        
        request("per_page", 10);
        
        return $this->successData(new Paging($query));
    }
    
    /**
    * Display a listing of the resource.
    *
    * @return \Illuminate\Http\Response
    */
    public function multipleSites() 
    {
         // checking for the search query
        $districts = request("districts");
        $governorates = request("governorates");
        $projectId = (int)request("project_id");
        $searchQuery = !empty(request('query')) ? request('query') : "";
        $data = SiteReference::where(function ($q) use ($searchQuery, $districts, $projectId, $governorates) {
            $q->where('name', 'LIKE', "%" . $searchQuery . "%");

            if(!empty($districts))
                $q->whereIn('district_id', $districts);
            
            if(empty($districts) && !empty($governorates))
                $q->whereIn('governorate_id', $governorates);

            if($projectId>0)
            {
                $sites = ProjectDetail::where("project_id", $projectId)->pluck("site_id","site_id")->toArray();

                if(!empty($sites) && (@$sites[0] != 0))
                    $q->whereIn("id", $sites);
            }
            
        })->take(10)->get();
        

        return $this->successData($data);
    }
   
    /**
     * Display the specified resource.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function one($id) 
    {
        $site = SiteReference::with(["governorate", "district"])->find($id);
        if (!$site) {
            return $this->failed("Invalid site Id");
        }
        return $this->successData($site);
    }
    
    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function store() 
    {
        $site                 = new SiteReference();
        $site->governorate_id = request("governorate_id");
        $site->district_id    = request("district_id");
        $site->name           = request("name");
        $site->description    = request("description");
        $site->lat            = request("lat");
        $site->lng            = request("lng");
        $site->save();
        
        return $this->successData($site);
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
        
        $site = SiteReference::find($id);
        if (!$site) {
            return $this->failed("Invalid Site");
        }
        if(SiteReference::where("name", "LIKE", request("name"))->where("id", "!=", $id)->count() > 0){
            return $this->failed("Site Name already exists!");
        }
        
        $site->governorate_id = request("governorate_id");
        $site->district_id    = request("district_id");
        $site->name           = request("name");
        $site->description    = request("description");
        $site->lat            = request("lat");
        $site->lng            = request("lng");
        $site->save();
        
        return $this->successData($site);
    }
    
    /**
     * Migrate specified sites data in storage.
     *
     * @return void
     */
    public function migrateOldSitesAndClusters() 
    {
        //remove foreign keys of teams & team_clusters before executing this script
        $allSites = \App\Models\Site::with(["clusters","teams"])->orderBy("id")->get();
        
        foreach($allSites as $key => $siteObj)
        {
            //duplicate sites
            $site                 =  new SiteReference();
            $site->governorate_id = ($siteObj->governorate_id>0)?$siteObj->governorate_id:10;
            $site->name           = $siteObj->name;
            $site->description    = $siteObj->description;
            $site->lat            = $siteObj->lat;
            $site->lng            = $siteObj->lng;
            $site->save();
            
            if(isset($siteObj->teams) && count($siteObj->teams)>0)
            {
                foreach ($siteObj->teams as $key1 => $teamObj){
                    $teamObj->site_id    = $site->id;
                    $teamObj->save();
                }
            }

            if(isset($siteObj->clusters) && count($siteObj->clusters)>0)
            {
                //duplicate clusters
                foreach ($siteObj->clusters as $key2 => $clusterObj){
                    $cluster                 = new ClusterReference();
                    $cluster->site_id        = $site->id;
                    $cluster->name           = $clusterObj->name;
                    $cluster->lat            = $clusterObj->lat;
                    $cluster->lng            = $clusterObj->lng;
                    $cluster->coordinates    = $clusterObj->coordinates;
                    $cluster->save();
                    
                    //add site & cluster relation
                    $projectDetail = new ProjectDetail();
                    $projectDetail->project_id = $clusterObj->project_id;
                    $projectDetail->site_id = $site->id;
                    $projectDetail->cluster_id = $cluster->id;
                    $projectDetail->save();
                    
                    // add team clusters
                    $teamClusters = \App\Models\TeamCluster::where("cluster_id",$clusterObj->id)->get();
                    if(isset($teamClusters) && count(@$teamClusters)>0)
                    {
                        foreach ($teamClusters as $key3 => $teamCluster){
                            $teamCluster->cluster_id = $cluster->id;
                            $teamCluster->save();
                        }
                    }
                    
                    //add form instance
                    $oldFormInstances  = FormInstance::where("site_id", $siteObj->id)->where("cluster_id", $clusterObj->id)->get();

                    if(isset($oldFormInstances) && count(@$oldFormInstances)>0)
                    {
                        foreach ($oldFormInstances as $key4 => $oldFormInstance){
                            $formInstance             = new \App\Models\TempFormInstance();                    
                            $formInstance->id         = $oldFormInstance->id;
                            $formInstance->project_id = $oldFormInstance->project_id;
                            $formInstance->user_id    = $oldFormInstance->user_id;
                            $formInstance->lat        = $oldFormInstance->lat;//latitude of the current submission
                            $formInstance->lng        = $oldFormInstance->lng;//longitude of the current submission
                            $formInstance->site_id    = $site->id;//current site
                            $formInstance->cluster_id = $cluster->id;//and current cluster
                            $formInstance->date_start = $oldFormInstance->date_start;
                            $formInstance->date_end   = $oldFormInstance->date_end;
                            $formInstance->save();
                        }
                    }
                }
            }
            
            //add guest sites
            $guestSite = \App\Models\GuestSite::where("site_id", $siteObj->id);            
            if(isset($guestSite))
            {
                foreach($guestSite as $key5 => $guest){
                    $guestSite->site_id    = $site->id;
                    $guestSite->save();
                }
            }
        
        }
        
        $count = \App\Models\TempFormInstance::count();
        if($count > 0){
            \DB::select("TRUNCATE form_instances");
            \DB::select("INSERT INTO form_instances (project_id, user_id, lat, lng, governorate_id, district_id, site_id, cluster_id, date_start, date_end, old_lat_lng, individual_count, stopped, created_at, updated_at)
                SELECT project_id, user_id, lat, lng, governorate_id, district_id, site_id, cluster_id, date_start, date_end, old_lat_lng, individual_count, stopped, created_at, updated_at
                FROM temp_form_instances where deleted_at IS NULL");
            \DB::select("TRUNCATE temp_form_instances");
        }
        
        return $this->successData($site);
    }
    
    /**
     * Migrate specified collector to locations table
     *
     * @return void
     */
    public function migrateCollectorsData()
    {
        $projects = \App\Models\Project::pluck("id")->toArray();
        
        foreach($projects as $index => $project){
            $members = \App\Models\ProjectMember::where("project_id", $project)->whereNotNull("team_id")->pluck("team_id", "user_id")->toArray();
            
            foreach($members as $memberId => $teamId)
            {
                $team = \App\Models\Team::with("site.clusters")->where("id", $teamId)->get()->toArray();
                
                if(isset($team[0]['site']['clusters'])){                    
                    foreach($team[0]['site']['clusters'] as $key => $data){
                            $location                 =  new ProjectLocationDetail();
                            $location->project_id     = $project;
                            $location->user_id        = $memberId;
                            $location->site_id        = $team[0]['site']['id'];
                            $location->cluster_id     = $data['id'];
                            $location->save();                        
                    }
                }
            }
        }
        
        return $this->successData();
    }
    
    public function migrateFormTypeParameter()
    {
        $FormTypes = \App\Models\FormType::get();
        
        foreach($FormTypes as $FormType)
        {
            $form = \App\Models\Form::find($FormType->form_id);
            $parameter = new \App\Models\Parameter();
            $parameter->project_id =  $form->project_id;
            $parameter->name_en =  $FormType->name_en;
            $parameter->name_ar =  $FormType->name_ar;
            $parameter->name_ku =  $FormType->name_ku;
            $parameter->allow_edit = in_array($FormType->name_en, array('Site/ Sub District', 'Cluster/ Camp Name/ PHC Name'))?0:1;
            $parameter->loop = 0;
            $parameter->order = 0;
            $parameter->save();
            
            $FormType->parameter_id = $parameter->id;
            $FormType->save();
        }
        return $this->successData();
    }
    
    public function migrateQuestionsFromParent()
    {
        $projects = \App\Models\Project::whereIn("id", [1,76,100,108])->get();
        
        foreach($projects as $project){
            $form = \App\Models\Form::with("types")->where("project_id", $project->id)->first();
            foreach($form->types as $type){
                if(!in_array($type->name_en, ['site/ sub district', 'cluster/ camp name/ phc name', 'governorate', 'district'])){
                    $questionNullGroups = \App\Models\QuestionGroup::where("form_type_id", $type->id)->whereNull("parent_group")->pluck("id","id")->toArray();
                    foreach($questionNullGroups as $nullGroupId)
                    {
                        $questionGroups = \App\Models\QuestionGroup::where("form_type_id", $type->id)->where("parent_group", $nullGroupId)->pluck("id","id")->toArray();
                        foreach($questionGroups as $groupId)
                        {
                            $questions = \App\Models\Question::where("question_group_id", $groupId)->get();
                            $this->updateQuestion($questions, $nullGroupId);
                            
                            $questionGroups2 = \App\Models\QuestionGroup::where("form_type_id", $type->id)->where("parent_group", $groupId)->pluck("id","id")->toArray();
                            foreach($questionGroups2 as $groupId2)
                            {
                                $questions2 = \App\Models\Question::where("question_group_id", $groupId2)->get();
                                $this->updateQuestion($questions2, $nullGroupId);
                                
                                $questionGroups3 = \App\Models\QuestionGroup::where("form_type_id", $type->id)->where("parent_group", $groupId2)->pluck("id","id")->toArray();
                                foreach($questionGroups3 as $groupId3)
                                {
                                    $questions3 = \App\Models\Question::where("question_group_id", $groupId3)->get();
                                    $this->updateQuestion($questions3, $nullGroupId);
                                    
                                    $questionGroups4 = \App\Models\QuestionGroup::where("form_type_id", $type->id)->where("parent_group", $groupId3)->pluck("id","id")->toArray();
                                    foreach($questionGroups4 as $groupId4)
                                    {
                                        $questions4 = \App\Models\Question::where("question_group_id", $groupId4)->get();
                                        $this->updateQuestion($questions4, $nullGroupId);
                                        
                                        $questionGroups5 = \App\Models\QuestionGroup::where("form_type_id", $type->id)->where("parent_group", $groupId4)->pluck("id","id")->toArray();
                                        foreach($questionGroups5 as $groupId5)
                                        {
                                            $questions5 = \App\Models\Question::where("question_group_id", $groupId5)->get();
                                            $this->updateQuestion($questions5, $nullGroupId);
                                            
                                            $questionGroups6 = \App\Models\QuestionGroup::where("form_type_id", $type->id)->where("parent_group", $groupId5)->pluck("id","id")->toArray();
                                            foreach($questionGroups6 as $groupId6)
                                            {
                                                $questions6 = \App\Models\Question::where("question_group_id", $groupId6)->get();
                                                $this->updateQuestion($questions6, $nullGroupId);
                                                
                                                $questionGroups7 = \App\Models\QuestionGroup::where("form_type_id", $type->id)->where("parent_group", $groupId6)->pluck("id","id")->toArray();
                                                foreach($questionGroups7 as $groupId7)
                                                {
                                                    $questions7 = \App\Models\Question::where("question_group_id", $groupId7)->get();
                                                    $this->updateQuestion($questions7, $nullGroupId);
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }                        
            }
        }
        return $this->successData();
    }
    
    private function updateQuestion($questions, $parentGroupId){
        foreach($questions as $question){
            $question->question_group_id = $parentGroupId;
            $question->save();
        }
    }
    
    /*
    * Remove duplicate governorates locations data 
    * */
    private function removeDuplicateGovernorates()
    {
        $governorates = \App\Models\Governorate::get();
        foreach($governorates as $governorate){
            $governorate->name = trim($governorate->name);
            $governorate->save();
        }
        
        $governorates = \DB::select("SELECT count(1) as count, name, GROUP_CONCAT(id SEPARATOR ',') as governorateIds FROM governorates where deleted_at IS NULL group by name having count>1 Order By id");
        foreach($governorates as $obj)
        {
            $governorateIds = explode(",", $obj->governorateIds);
            $firstGovernorateId = $governorateIds[0];
            
            $projectDetail = ProjectDetail::whereIn("governorate_id", $governorateIds)->first();
            $projectLocationDetail = ProjectLocationDetail::whereIn("governorate_id", $governorateIds)->first();
            $formInstance = FormInstance::whereIn("governorate_id", $governorateIds)->first();
            $indicatorResults = IndicatorResults::whereIn("governorate_id", $governorateIds)->first();
            
            if(empty($projectDetail) && empty($projectLocationDetail) && empty($formInstance) && empty($indicatorResults)){
                \App\Models\Governorate::where("id", "!=", $firstGovernorateId)->whereIn("id", $governorateIds)->delete();
            }
            else{                
                    \App\Models\Governorate::where("id", "!=", $firstGovernorateId)->whereIn("id", $governorateIds)->delete();
                    ProjectDetail::where("governorate_id", "!=", $firstGovernorateId)->whereIn("governorate_id", $governorateIds)->delete();
                    ProjectLocationDetail::where("governorate_id", "!=", $firstGovernorateId)->whereIn("governorate_id", $governorateIds)->delete();

                    \DB::table('form_instances')->whereIn("governorate_id", $governorateIds)->update(['governorate_id' => $firstGovernorateId]);
                    \DB::table('indicators_results')->whereIn("governorate_id", $governorateIds)->update(['governorate_id' => $firstGovernorateId]);                
            }            
        }        
    }
    
    
    /*
    * Remove duplicate districts data and clean data 
    * */
    public function removeDuplicateDistricts()
    {
        $districts = \App\Models\District::get();
        foreach($districts as $district){
            $district->name = trim($district->name);
            $district->save();
        }
        
        $districts = \DB::select("SELECT count(1) as count, name, GROUP_CONCAT(id SEPARATOR ',') as districtIds FROM districts where deleted_at IS NULL group by name having count>1 Order By id");
        foreach($districts as $obj)
        {
            $districtIds = explode(",", $obj->districtIds);
            $firstDistrictId = $districtIds[0];
            
            $sitesData = SiteReference::whereIn("district_id", $districtIds)->first();
            $projectDetail = ProjectDetail::whereIn("district_id", $districtIds)->first();
            $projectLocationDetail = ProjectLocationDetail::whereIn("district_id", $districtIds)->first();
            $formInstance = FormInstance::whereIn("district_id", $districtIds)->first();
            $indicatorResults = IndicatorResults::whereIn("district_id", $districtIds)->first();
            
            if(empty($sitesData) && empty($projectDetail) && empty($projectLocationDetail) && empty($formInstance) && empty($indicatorResults)){
                \App\Models\District::where("id", "!=", $firstDistrictId)->whereIn("id", $districtIds)->delete();
            }
            else{                
                    \App\Models\District::where("id", "!=", $firstDistrictId)->whereIn("id", $districtIds)->delete();
                    ProjectDetail::where("district_id", "!=", $firstDistrictId)->whereIn("district_id", $districtIds)->delete();
                    ProjectLocationDetail::where("district_id", "!=", $firstDistrictId)->whereIn("district_id", $districtIds)->delete();

                    \DB::table('site_references')->whereIn("district_id", $districtIds)->update(['district_id' => $firstDistrictId]);
                    \DB::table('form_instances')->whereIn("district_id", $districtIds)->update(['district_id' => $firstDistrictId]);
                    \DB::table('indicators_results')->whereIn("district_id", $districtIds)->update(['district_id' => $firstDistrictId]);                
            }
                        
        }
    }
    
    /*
    * Remove duplicate clusters data and clean data 
    * */
    public function removeDuplicateClusters()
    {
        $clusters = \App\Models\ClusterReference::get();
        foreach($clusters as $cluster){
            $cluster->name = trim($cluster->name);
            $cluster->save();
        }
        
        $clusters = \DB::select("SELECT count(1) as count, name, GROUP_CONCAT(id SEPARATOR ',') as clusterIds FROM cluster_references where deleted_at IS NULL group by name having count>1 Order By id");
        foreach($clusters as $obj)
        {
            $clustersIds = explode(",", $obj->clusterIds);
            $firstClusterId = $clustersIds[0];
            
            $projectDetail = ProjectDetail::whereIn("cluster_id", $clustersIds)->first();
            $projectLocationDetail = ProjectLocationDetail::whereIn("cluster_id", $clustersIds)->first();
            $formInstance = FormInstance::whereIn("cluster_id", $clustersIds)->first();
            $indicatorResults = IndicatorResults::whereIn("cluster_id", $clustersIds)->first();
            
            if(empty($projectDetail) && empty($projectLocationDetail) && empty($formInstance) && empty($indicatorResults)){
                \App\Models\ClusterReference::where("id", "!=", $firstClusterId)->whereIn("id", $clustersIds)->delete();
            }
            else{                
                    \App\Models\ClusterReference::where("id", "!=", $firstClusterId)->whereIn("id", $clustersIds)->delete();
                    ProjectDetail::where("cluster_id", "!=", $firstClusterId)->whereIn("cluster_id", $clustersIds)->delete();
                    ProjectLocationDetail::where("cluster_id", "!=", $firstClusterId)->whereIn("cluster_id", $clustersIds)->delete();

                    \DB::table('form_instances')->whereIn("cluster_id", $clustersIds)->update(['cluster_id' => $firstClusterId]);
                    \DB::table('indicators_results')->whereIn("cluster_id", $clustersIds)->update(['cluster_id' => $firstClusterId]);                
            }
                        
        }
    }
    
    /*
    * Remove duplicate sites from old data and clean data 
    * */
    public function removeDuplicateSites()
    {
        ini_set('memory_limit','-1');
        set_time_limit(6000);
        
        try{
            \DB::statement("ALTER TABLE `form_instances` DROP FOREIGN KEY `form_instances_site_id_foreign`");
        }catch (\Exception $e) {
            
        }
        $this->removeDuplicateGovernorates();
        $this->removeDuplicateDistricts();
        $this->removeDuplicateClusters();
        
        $sites = \DB::select("SELECT count(1) as count, name, GROUP_CONCAT(id SEPARATOR ',') as siteIds FROM site_references where deleted_at IS NULL group by name having count(1)>1");
        foreach($sites as $obj)
        {
            $siteIds = explode(",", $obj->siteIds);
            $firstSiteId = $siteIds[0];
            
            $clusters = ClusterReference::whereIn("site_id", $siteIds)->first();
            $projectDetail = ProjectDetail::whereIn("site_id", $siteIds)->first();
            $projectLocationDetail = ProjectLocationDetail::whereIn("site_id", $siteIds)->first();
            $formInstance = FormInstance::whereIn("site_id", $siteIds)->first();
            $indicatorResults = IndicatorResults::whereIn("site_id", $siteIds)->first();
            
            if(empty($clusters) && empty($projectDetail) && empty($projectLocationDetail) && empty($formInstance) && empty($indicatorResults)){
                SiteReference::where("id", "!=", $firstSiteId)->whereIn("id", $siteIds)->delete();
            }
            else if(empty($clusters) && !empty($siteIds))
            {
                SiteReference::where("id", "!=", $firstSiteId)->whereIn("id", $siteIds)->delete();
            }
            else{
                if($clusters){
                    SiteReference::where("id", "!=", $clusters->site_id)->whereIn("id", $siteIds)->delete();
                    ProjectDetail::where("site_id", "!=", $clusters->site_id)->whereIn("site_id", $siteIds)->delete();
                    ProjectLocationDetail::where("site_id", "!=", $clusters->site_id)->whereIn("site_id", $siteIds)->delete();

                    \DB::table('cluster_references')->whereIn("site_id", $siteIds)->update(['site_id' => $clusters->site_id]);
                    \DB::table('form_instances')->whereIn("site_id", $siteIds)->update(['site_id' => $clusters->site_id]);
                    \DB::table('indicators_results')->whereIn("site_id", $siteIds)->update(['site_id' => $clusters->site_id]);
                }
            }
            
        }
        
        //Strict Items to Remove
        $removeSites = [16,18,40,41,151,153];
        $removeClusters = [57,58,59,60,130,131,132,477,478,479,480];
        ProjectDetail::whereIn("site_id",$removeSites)->orWhereIn("cluster_id",$removeClusters)->delete();
        ProjectLocationDetail::whereIn("site_id",$removeSites)->orWhereIn("cluster_id",$removeClusters)->delete();
        IndicatorResults::whereIn("site_id",$removeSites)->orWhereIn("cluster_id",$removeClusters)->delete();
        FormInstance::whereIn("site_id",$removeSites)->orWhereIn("cluster_id",$removeClusters)->delete();
        ClusterReference::whereIn("site_id",$removeSites)->orWhereIn("id",$removeClusters)->delete();
        SiteReference::whereIn("id", $removeSites)->delete();
        \App\Models\Governorate::whereIn("id",[256,512,629,768,1024,1131,1280,1536])->delete();
        ProjectDetail::whereIn("governorate_id",[256,512,629,768,1024,1131,1280,1536])->delete();
        ProjectLocationDetail::whereIn("governorate_id",[256,512,629,768,1024,1131,1280,1536])->orWhereIn("cluster_id",$removeClusters)->delete();
        $sitesToRemove = SiteReference::whereIn("governorate_id",[256,512,629,768,1024,1131,1280,1536])->pluck("id","id")->toArray();
        $sites = ClusterReference::pluck("site_id","site_id")->toArray();
        SiteReference::whereNotIn("id",$sites)->orWhereIn("id",$sitesToRemove)->delete();
        ClusterReference::whereIn("site_id",$sitesToRemove)->delete();
        $this->addNewDistrictsToSites();
        
        return $this->successData();
    }
    
    /*
    * Remove un-matched data
    * */
    public function removeExtraLocationData()
    {
        ini_set('memory_limit','-1');
        set_time_limit(6000);
        
        try{
            \DB::statement("ALTER TABLE `form_instances` DROP FOREIGN KEY `form_instances_site_id_foreign`");
        }catch (\Exception $e) {
            
        }
        
        $sites = ClusterReference::pluck("site_id","site_id")->toArray();
        $districts = SiteReference::whereIn("id",$sites)->pluck("district_id","district_id")->toArray();
        $governorates = SiteReference::whereIn("id",$sites)->pluck("governorate_id","governorate_id")->toArray();
        
        SiteReference::whereNotIn("id",$sites)->delete();
        \App\Models\District::whereNotIn("id",$districts)->delete();
        \App\Models\Governorate::whereNotIn("id",$governorates)->delete();
        
        return $this->successData();
    }

    /*
    * Add new districts for site data
    * */
    private function addNewDistrictsToSites()
    {
        $sites = \App\Models\SiteReference::whereNull("district_id")->orWhere("district_id", "")->get();
        
        foreach($sites as $site)
        {
            $district = \App\Models\District::where("name", "LIKE", $site->name)->where("governorate_id", $site->governorate_id)->first();
            if(!$district){
                $district = new \App\Models\District();
                $district->name = $site->name;
                $district->governorate_id = $site->governorate_id;
                $district->save();
            }
            $site->district_id = $district->id;
            $site->save();
        }
    }
    
    /*
    * Fixed questions name languages
    * */
    public function updateBaseQuestions()
    {
        \DB::table('questions')->where("name_en","LIKE",'Name of Governorate?')->update(['name_ar' => 'اسم المحافظة؟', 'name_ku'=>'ناوی پارێزگا؟']);
        \DB::table('questions')->where("name_en","LIKE",'Name of District?')->update(['name_ar' => 'اسم المحافظة؟', 'name_ku'=>'ناوى کەرت؟']);
        \DB::table('questions')->where("name_en","LIKE",'Name of the Site?')->update(['name_ar' => 'اسم الموقع؟', 'name_ku'=>'ناوی شوێن؟']);
        \DB::table('questions')->where("name_en","LIKE",'Name of Cluster?')->update(['name_ar' => 'اسم التجمع؟', 'name_ku'=>'ناوی کۆمەڵ؟']);
        
        $forms = \App\Models\Form::get();
        foreach($forms as $form){
            /*$type = \App\Models\FormType::where("form_id", $form->id)->where("name_en","Individual Information")->first();
            if($type){
                $formQuestion1 = \App\Models\Question::with("options")->where("form_id",$form->id)->where("name_en","LIKE","Gender?")->where("response_type_id",1)->first();
                $formQuestion2 = \App\Models\Question::where("form_id",$form->id)->where("name_en","LIKE","Age?")->where("response_type_id",4)->first();
                if(isset($formQuestion1->options) && count($formQuestion1->options) == 0)
                {
                    \DB::table('question_options')->where("question_id", $formQuestion1->id)->update(['deleted_at' => NULL]);
                }
            }*/
            
            $formQuestion = \App\Models\Question::with("options")->where("form_id",$form->id)->whereIn("response_type_id",[1,2,3,4,5,6,9,10,14,15,16,18,19])->first();
            if(isset($formQuestion->options) && count($formQuestion->options) == 0)
            {
                \DB::table('question_options')->where("question_id", $formQuestion->id)->update(['deleted_at' => NULL]);
            }
        }
        
        \DB::table('question_options')->update(['deleted_at' => NULL]);
        
        return $this->successData();
    }
    
    /*
    * Fixed questions response type to change
    * */
    public function updateResponseType()
    {
        ini_set('memory_limit','-1');
        set_time_limit(6000);
        
        $questionList = [];
        $questions = \App\Models\Question::whereIn("name_en", ['Name of Governorate?','Name of District?','Name of the Site?','Name of Cluster?'])->get();
        
        foreach($questions as $question)
        {
            $question->response_type_id = 1;
            $question->save();
            
            \DB::table('question_answers')->where("question_id", $question->id)->update(['response_type_id' => 1]);
            \DB::table('indicators_results')->where("question_id", $question->id)->update(['response_type_id' => 1]);
        }
        
        $projects = \App\Models\Project::get();
        foreach($projects as $project){
            if($project->project_type == 'survey'){
                $governorates = ProjectDetail::where("project_id", $project->id)->pluck("governorate_id", "governorate_id")->toArray();
                $districts = ProjectDetail::where("project_id", $project->id)->pluck("district_id", "district_id")->toArray();
                $sites = ProjectDetail::where("project_id", $project->id)->pluck("site_id", "site_id")->toArray();
                $clusters = ProjectDetail::where("project_id", $project->id)->pluck("cluster_id", "cluster_id")->toArray();
                $this->addLocationQuestionOptions($project, $governorates, $districts, $sites, $clusters);
            }else{
                $governorates = \App\Models\Governorate::pluck("id", "id")->toArray();
                $districts = \App\Models\District::pluck("id", "id")->toArray();
                $sites = SiteReference::pluck("id", "id")->toArray();
                $clusters = ClusterReference::pluck("id", "id")->toArray();
                $this->addLocationQuestionOptions($project, $governorates, $districts, $sites, $clusters);
            }
        }
        
        \DB::update('UPDATE `question_options` SET config_id=id', []);
        
        return $this->successData();
    }
    
    /*
    * Fix missin governorate_id and district_id in old data
    * */
    public function addOldDataLocations()
    {
        ini_set('memory_limit','-1');
        ini_set('max_execution_time', '0');
        
        $governorates = \App\Models\SiteReference::pluck("governorate_id","id");
        $districts = \App\Models\SiteReference::pluck("district_id","id");
        

        $projectDetails = ProjectDetail::where("site_id", ">", 0)
                                                    ->orWhere("district_id", 0)
                                                    ->orWhere("governorate_id", 0)
                                                    ->orWhere("district_id", "")
                                                    ->orWhere("governorate_id", "")
                                                    ->orWhereNull("district_id")
                                                    ->orWhereNull("governorate_id")
                                                    ->get();
        
        foreach($projectDetails as $detail)
        {
            if(!($detail->district_id > 0 && $detail->governorate_id > 0)){
                ProjectDetail::where('site_id', $detail->site_id)
                    ->update(['district_id' => @$districts[$detail->site_id], 'governorate_id'=>@$governorates[$detail->site_id]]);
            }
        }
        
        $projectLocationDetails = ProjectLocationDetail::where("site_id", ">", 0)
                                                    ->orWhere("district_id", 0)
                                                    ->orWhere("governorate_id", 0)
                                                    ->orWhere("district_id", "")
                                                    ->orWhere("governorate_id", "")
                                                    ->orWhereNull("district_id")
                                                    ->orWhereNull("governorate_id")
                                                    ->get();
        
        foreach($projectLocationDetails as $location)
        {
           if(!($location->district_id > 0 && $location->governorate_id > 0)){
                ProjectLocationDetail::where('site_id', $location->site_id)
                    ->update(['district_id' => @$districts[$location->site_id], 'governorate_id'=>@$governorates[$location->site_id]]);
            }
        }
        
        $formInstances = FormInstance::where("site_id", ">", 0)
                                                    ->orWhere("district_id", 0)
                                                    ->orWhere("governorate_id", 0)
                                                    ->orWhere("district_id", "")
                                                    ->orWhere("governorate_id", "")
                                                    ->orWhereNull("district_id")
                                                    ->orWhereNull("governorate_id")
                                                    ->get();
        foreach($formInstances as $formInstance)
        {
            $formInstance->district_id = @$districts[$formInstance->site_id];
            $formInstance->governorate_id = @$governorates[$formInstance->site_id];
            $formInstance->save();
        }
        
        /*$indicatorResults = IndicatorResults::where("site_id", ">", 0)
                                                    ->orWhere("district_id", 0)
                                                    ->orWhere("governorate_id", 0)
                                                    ->orWhere("district_id", "")
                                                    ->orWhere("governorate_id", "")
                                                    ->orWhereNull("district_id")
                                                    ->orWhereNull("governorate_id")
                                                    ->get();
        
        foreach($indicatorResults as $indicator)
        {
            IndicatorResults::where('site_id', $indicator->site_id)
                ->update(['district_id' => @$districts[$indicator->site_id], 'governorate_id'=>@$governorates[$indicator->site_id]]);
        }*/
        
        
        return $this->successData();
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
            if (!$site = SiteReference::find($id)) {
                return $this->failed("Invalid Site");
            }
            
            if(\App\Models\ClusterReference::where('site_id', $id)->count() == 0 && FormInstance::where('site_id', $id)->count() == 0){
                $site->delete();
            }else
                return $this->failed("Please remove first related data to remove this resourse.");
            
            return $this->success('Site deleted');
        } catch (\Exception $e) {
            return $this->failed('destroy error');
        }
    }
    
    /**
     * add locations as options
     * @return Void
     */
    public function addLocationQuestionOptions($project, $savedGovernorates, $savedDistricts, $savedSites, $savedClusters)
    {
        $form = \App\Models\Form::where("project_id", $project->id)->first();

        $governorates = \App\Models\Governorate::whereIn("id", $savedGovernorates)->get();
        $districts = \App\Models\District::whereIn("id", $savedDistricts)->get();
        $sites = \App\Models\SiteReference::whereIn("id", $savedSites)->get();
        $clusters = \App\Models\ClusterReference::whereIn("id", $savedClusters)->get();
        
        $locationList = ['Name of Governorate?'=>$governorates, 'Name of District?'=>$districts, 'Name of the Site?'=>$sites, 'Name of Cluster?'=>$clusters];
        $questions = \App\Models\Question::where("form_id", $form->id)->whereIn("name_en", ['Name of Governorate?','Name of District?','Name of the Site?','Name of Cluster?'])->get();

        $questionIds = [];
        $notToDeleteOptions = [];
        foreach($questions as $question)
        {
            $locations = $locationList[$question->name_en];
            foreach($locations as $key => $locationObj)
            {
                $option = \App\Models\QuestionOption::where("question_id",$question->id)->where("name_en", "LIKE", $locationObj->name)->first();
                $option = ($option)?$option: new \App\Models\QuestionOption();  
                $option->config_id = $locationObj->id;
                $option->name_en = $locationObj->name;
                $option->name_ar = $locationObj->name;
                $option->name_ku = $locationObj->name;
                $option->question_id = $question->id;
                $option->order_value = $key+1;
                $option->stop_collect = 0;
                $option->save();
                
                $notToDeleteOptions[$option->id] = $option->id;
            }
            
            $questionIds[$question->id] = $question->id;
        }
        
        \App\Models\QuestionOption::whereIn("question_id",$questionIds)->whereNotIn("id",$notToDeleteOptions)->delete();
    }
}
