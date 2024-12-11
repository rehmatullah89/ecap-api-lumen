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
use App\Models\FormCategory;
use App\Models\FormType;
use App\Models\Parameter;
use App\Models\Project;
use App\Models\ProjectDetail;
use App\Models\ProjectMember;
use App\Models\ProjectPermission;
use App\Models\Question;
use App\Models\QuestionGroup;
use App\Models\ProjectLocationDetail;
use App\Models\QuestionSettingAppearance;
use App\Models\QuestionSettingOptions;
use App\Models\SkipLogicQuestion;
use App\Models\SkipLogicQuestionDetail;
use App\Models\ClusterReference;
use App\Models\SiteReference;
use App\Models\User;
use App\Models\DiseaseDetail;
use Idea\Helpers\Paging;
use Illuminate\Support\Facades\DB;
use Intervention\Image\Facades\Image;
use App\Models\ProjectUserTitles;

class ProjectController extends WhoController
{

    public $filePath = "projects/";

    public $questionMap = [];

    /**
     * the following variable is used to keep reference of options while duplicating project
     *
     * @var array
     */
    public $optionMap = [];

    protected $permissions = [
        "index" => ["code" => "projects", "action" => "read"],
        "one" => ["code" => "projects", "action" => "read"],
        "store" => ["code" => "projects", "action" => "write"],
        "update" => ["code" => "projects", "action" => "write"],
        "updateStatus" => ["code" => "projects", "action" => "write"],
        "duration" => ["code" => "projects", "action" => "write"],
        "goal" => ["code" => "projects", "action" => "write"],
        "description" => ["code" => "projects", "action" => "write"],
        "destroy" => ["code" => "projects", "action" => "write"],
        "listProjects" => ["code" => "projects", "action" => "read"],
    ];

    /**
     *
     * @return array
     */
    protected static function validationRules()
    {
        return [
            'store' => [
                "name" => "required",
            ],
            'update' => [
                "name" => "required",
                /*//|unique:projects,name,:id
                 * 'icd_code' => 'required_if:project_type,surveillance',
                'disease_group' => 'required_if:project_type,surveillance',
                'date_start' => 'date_format:d/m/Y',
                'date_end' => 'date_format:d/m/Y',*/
            ],
            'duration' => [
                'date_start' => 'required|date_format:d/m/Y',
                'date_end' => 'required|date_format:d/m/Y',
            ],
            'goal' => [
                'goal' => 'required|integer',
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
        $userId = $this->user->id;
        $isGuest = $this->user->hasRole('guest');
        $search = request("query");
        $projectType = request("project_type");

        $query = Project::with(["sites.clusters","form.questions", "projectDetails"])->orderBy("id");

        if (!empty($search))
            $query->where("name", "like", "%" . $search . "%");

        if(!empty($projectType))
            $query->where('project_type', $projectType);

        $user = User::whereId($this->user->id)->first();
        if (request("search")) {
            $query->where("name", "like", "%" . request("search") . "%");
        }

        if (!$user->hasAnyOfRoles(["admin", "owner", "project_administrator"]) && empty($projectType)) {
            $query->whereHas(
                'members',
                function ($q) {
                    $q->where('user_id', $this->user->id);
                }
            );
        }

        return $this->successData(new Paging($query));
    }

    /**
     * Display a listing of the projects with required relations.
     *
     * @return \Illuminate\Http\Response
     */
    public function listProjects()
    {
        $searchQuery = !empty(@request('query')) ? request('query') : "";
        $searchOpt = !empty(@request('option')) ? request('option') : "";
        $searchStatus = request('status') == "" ? [0, 1] : [request('status')];
        $projectType = !empty(@request('project_type')) ? request('project_type') : "survey";

        $query = Project::where(function ($q) use ($searchQuery) {
            if (!empty($searchQuery)) {
                $q->where('name', 'LIKE', "%" . $searchQuery . "%");
            } else {
                $q->where('id', '!=', 0);
            }

        })
        ->where('project_type', $projectType)
        ->whereIn('status', $searchStatus)
        ->with(["createdBy", "form.groups"])
        ->orderBy('id', 'Desc');

        $user = User::whereId($this->user->id)->first();
        if (request("search")) {
            $query->where("name", "like", "%" . request("search") . "%");
        }

        if ($user->role_id != 9) {
            $query->whereHas(
                'members',
                function ($q) {
                    $q->where('user_id', $this->user->id);
                }
            );
        }

        return $this->successData(new Paging($query));
    }

    /**
     * Get Project location
     *
     * @param $id
     *
     * @return \Illuminate\Http\Response
     */
    public function projectLocation($id)
    {
        $query = DB::select(DB::raw("SELECT COUNT(DISTINCT pd.site_id) as countSites, "
                . "COUNT(DISTINCT pd.cluster_id) as countClusters, "
                . "(SELECT COUNT(DISTINCT district_id) from site_references where id IN (SELECT site_id from project_details Where project_id = {$id})) as countDistricts, "
                . "(SELECT COUNT(DISTINCT governorate_id) from site_references where id IN (SELECT site_id from project_details Where project_id = {$id})) as countGovernorates, "
                . "GROUP_CONCAT(DISTINCT site_id SEPARATOR ',') as sites "
            . "FROM project_details pd WHERE pd.project_id = {$id} GROUP By pd.project_id"));

        $query = $this->projectData($query);

        if (isset($query['sites']) && !empty($query['sites'])) {
            $query['sites'] = \App\Models\SiteReference::with("clusters")->whereIn("id", explode(",", $query['sites']))->get()->toArray();
        }

        return $this->successData($query);
    }

    /**
     * Get Project location
     *
     * @param $id
     *
     * @return \Illuminate\Http\Response
     */
    public function exportProjectLocations()
    {
        $id = request("project_id");
        // exporting the excel sheet with the resepctive data
        \Excel::create(
           "ExportProjectLocations{$id}", function ($excel) use ($id) {

               $governorateList = ProjectDetail::where("project_id",$id)->pluck("governorate_id","governorate_id")->toArray();
               $governorates = \App\Models\Governorate::whereIn("id",$governorateList)->get();
               $districtList = ProjectDetail::where("project_id",$id)->pluck("district_id","district_id")->toArray();
               $districts = \App\Models\District::whereIn("id",$districtList)->get();
               $siteList = ProjectDetail::where("project_id",$id)->pluck("site_id","site_id")->toArray();
               $sites = \App\Models\SiteReference::whereIn("id",$siteList)->get();
               $clusterList = ProjectDetail::where("project_id",$id)->pluck("cluster_id","cluster_id")->toArray();
               $clusters = \App\Models\ClusterReference::whereIn("id",$clusterList)->get();

               $governorateData[] = [0=>"id",1=>"name"];
               foreach($governorates as $governorate)
                   $governorateData[] = [0=>$governorate->id,1=>$governorate->name];

               $districtData[] = [0=>"id",1=>"name"];
               foreach($districts as $district)
                   $districtData[] = [0=>$district->id,1=>$district->name];

               $siteData[] = [0=>"id",1=>"name"];
               foreach($sites as $site)
                   $siteData[] = [0=>$site->id,1=>$site->name];

               $clusterData[] = [0=>"id",1=>"name"];
               foreach($clusters as $cluster)
                   $clusterData[] = [0=>$cluster->id,1=>$cluster->name];

                // creating the sheet and filling it with questions data
                $excel->sheet(
                    'Governorates', function ($sheet) use ($governorateData) {
                        $sheet->rows($governorateData);
                    }
                );

                $excel->sheet(
                    'Districts', function ($sheet) use ($districtData) {
                        $sheet->rows($districtData);
                    }
                );

                $excel->sheet(
                    'Sites', function ($sheet) use ($siteData) {
                        $sheet->rows($siteData);
                    }
                );

                $excel->sheet(
                    'Clusters', function ($sheet) use ($clusterData) {
                        $sheet->rows($clusterData);
                    }
                );
            }
        )->store('xls', "/tmp");

        //download excel file
        $file = "/tmp/ExportProjectLocations{$id}.xls";
        return response()->download($file, "ExportProjectLocations{$id}.xls", ['Content-Type: application/vnd.ms-excel']);
    }

    /**
     * Get Project summary
     *
     * @param $id
     *
     * @return \Illuminate\Http\Response
     */
    public function projectSummary($id)
    {
        $query = DB::select(DB::raw("SELECT (SELECT COUNT(1) from form_instances where project_id = p.id) as countForms, "
            . " (SELECT COUNT(1) from form_categories where form_id = f.id) as countCategories, "
            . " (SELECT COUNT(1) from form_instances where project_id = p.id) as countRecords, "
            . " (SELECT SUM(IF(individual_count>0,individual_count,1)) from form_instances where project_id = p.id) as countParticipants, "
            . " (SELECT SUM(1) from questions where form_id = f.id) as countQuestions "
            . "FROM projects p, forms f WHERE p.id=f.project_id AND p.id = {$id}"));

        return $this->successData($this->projectData($query));
    }

    /**
    * Export Project Users
    *
    * @param $id
    *
    * @return \Illuminate\Http\Response
    */
    public function exportProjectUsers()
    {
        $id = request("project_id");

            $queryData = DB::select(DB::raw("SELECT GROUP_CONCAT(IF(ur.role_id=4,ur.role_id,0) SEPARATOR ',') as projectManager, "
                . " GROUP_CONCAT(IF(ur.role_id=5,ur.user_id,0) SEPARATOR ',') as supervisor , "
                . " GROUP_CONCAT(IF(ur.role_id=6,ur.user_id,0) SEPARATOR ',') as guest , "
                . " GROUP_CONCAT(IF(ur.role_id=4 OR ur.role_id=5,ur.user_id,0) SEPARATOR ',') as collaborator, "
                . " GROUP_CONCAT(IF(ur.role_id=1 OR ur.role_id=10,ur.user_id,0) SEPARATOR ',') as projectAdmin, "
                ." GROUP_CONCAT(IF(ur.role_id=12,ur.user_id,0) SEPARATOR ',') as collectors "
            . "FROM project_members pm, user_roles ur WHERE pm.user_id=ur.user_id AND pm.project_id = {$id}"));

        // exporting the excel sheet with the resepctive data
        \Excel::create(
           "ExportProjectUsers{$id}", function ($excel) use ($queryData) {

                $projectMangers = $this->getUsers(explode(",",@$queryData[0]->projectManager));
                $supervisors = $this->getUsers(explode(",",@$queryData[0]->supervisor));
                $guests = $this->getUsers(explode(",",@$queryData[0]->guest));
                $collaborators = $this->getUsers(explode(",",@$queryData[0]->collaborator));
                $projectAdmins = $this->getUsers(explode(",",@$queryData[0]->projectAdmin));
                $collectors = $this->getUsers(explode(",",@$queryData[0]->collectors));

                $projectMangerData[] = [0=>'Id',1=>'Name'];
                foreach($projectMangers as $data)
                    $projectMangerData[] = [0=>$data->id,1=>$data->name];

                $supervisorData[] = [0=>'Id',1=>'Name'];
                foreach($supervisors as $data)
                    $supervisorData[] = [0=>$data->id,1=>$data->name];

                $guestData[] = [0=>'Id',1=>'Name'];
                foreach($guests as $data)
                    $guestData[] = [0=>$data->id,1=>$data->name];

                $collaboratorData[] = [0=>'Id',1=>'Name'];
                foreach($collaborators as $data)
                    $collaboratorData[] = [0=>$data->id,1=>$data->name];

                $projectAdminData[] = [0=>'Id',1=>'Name'];
                foreach($projectAdmins as $data)
                    $projectAdminData[] = [0=>$data->id,1=>$data->name];

                $collectorData[] = [0=>'Id',1=>'Name'];
                foreach($collectors as $data)
                    $collectorData[] = [0=>$data->id,1=>$data->name];

                // creating the sheet and filling it with questions data
                $excel->sheet(
                    'ProjectManagers', function ($sheet) use ($projectMangerData) {
                        $sheet->rows($projectMangerData);
                    }
                );

                $excel->sheet(
                    'Supervisors', function ($sheet) use ($supervisorData) {
                        $sheet->rows($supervisorData);
                    }
                );

                $excel->sheet(
                    'Guests', function ($sheet) use ($guestData) {
                        $sheet->rows($guestData);
                    }
                );

                $excel->sheet(
                    'Collaborators', function ($sheet) use ($collaboratorData) {
                        $sheet->rows($collaboratorData);
                    }
                );

                $excel->sheet(
                    'ProjectAdmins', function ($sheet) use ($projectAdminData) {
                        $sheet->rows($projectAdminData);
                    }
                );

                $excel->sheet(
                    'Collectors', function ($sheet) use ($collectorData) {
                        $sheet->rows($collectorData);
                    }
                );

            }
        )->store('xls', "/tmp");

        //download excel file
        $file = "/tmp/ExportProjectUsers{$id}.xls";
        return response()->download($file, "ExportProjectUsers{$id}.xls", ['Content-Type: application/vnd.ms-excel']);
    }

    /**
    * Export Project summary
    *
    * @param $id
    *
    * @return \Illuminate\Http\Response
    */
    public function exportProjectSummary()
    {
        $id = request("project_id");
        $project = Project::find($id);
        $query = DB::select(DB::raw("SELECT (SELECT COUNT(1) from form_instances where project_id = p.id) as countForms, "
            . " (SELECT COUNT(1) from form_categories where form_id = f.id) as countCategories, "
            . " (SELECT COUNT(1) from form_instances where project_id = p.id) as countRecords, "
            . " (SELECT SUM(IF(individual_count>0,individual_count,1)) from form_instances where project_id = p.id) as countParticipants, "
            . " (SELECT SUM(1) from questions where form_id = f.id) as countQuestions "
            . "FROM projects p, forms f WHERE p.id=f.project_id AND p.id = {$id}"));

        $summaryData[] = [0=>"Project Name:",1=>$project->name,2=>"",3=>"",4=>""];
        $summaryData[] = [0=>"Start Date:",1=>$project->date_start,2=>"",3=>"",4=>""];
        $summaryData[] = [0=>"End Date:",1=>$project->date_end,2=>"",3=>"",4=>""];
        $summaryData[] = [0=>"Created By:",1=>User::where("id",$project->created_by)->pluck("name")->first(),2=>"",3=>"",4=>""];
        $summaryData[] = [0=>"",1=>"",2=>"",3=>"",4=>""];
        $summaryData[] = [0=>"Records",1=>"Households",2=>"Individuals",3=>"Categories",4=>"Questions"];
        $summaryData[] = [0=>@$query[0]->countRecords,1=>@$query[0]->countForms,2=>@$query[0]->countParticipants,3=>@$query[0]->countCategories,4=>@$query[0]->countQuestions];
        // exporting the excel sheet with the resepctive data
        \Excel::create(
           "ExportProjectSummary{$id}", function ($excel) use ($summaryData) {
                // creating the sheet and filling it with questions data
                $excel->sheet(
                    'ProjectSummary', function ($sheet) use ($summaryData) {
                        $sheet->rows($summaryData);
                    }
                );
            }
        )->store('xls', "/tmp");

        //download excel file
        $file = "/tmp/ExportProjectSummary{$id}.xls";
        return response()->download($file, "ExportProjectSummary{$id}.xls", ['Content-Type: application/vnd.ms-excel']);
    }

    /**
     * Get Project users stats
     *
     * @param $id
     *
     * @return \Illuminate\Http\Response
     */
    public function projectUserStats($id)
    {
        $project = Project::find($id);
        $query = DB::select(DB::raw("SELECT COUNT(DISTINCT IF(ur.role_id=4,ur.user_id,NULL)) as projectManager, "
                . "COUNT(DISTINCT IF(ur.role_id=5,ur.user_id,NULL)) as supervisor , "
                . "COUNT(DISTINCT IF(ur.role_id=6,ur.user_id,NULL)) as guest , "
                . "COUNT(DISTINCT IF(ur.role_id=4 OR ur.role_id=5,ur.user_id,NULL)) as collaborator, "
                . "COUNT(DISTINCT IF(ur.role_id=1 OR ur.role_id=10,ur.user_id,NULL)) as projectAdmin, "
                ."COUNT(DISTINCT IF(ur.role_id=12,ur.user_id,NULL)) as collectors "
            . "FROM project_members pm, user_roles ur WHERE pm.user_id=ur.user_id AND pm.project_id = {$id}"));
        return $this->successData($this->projectData($query));
    }

    /**
     * Set Project users.
     *
     * @param $id
     *
     * @return \Illuminate\Http\Response
     */
    public function projectMembers()
    {
        $projectId = request("project_id");
        $members = request("members"); //json_decode(request("members"));

        // detaching all previously added members
        $project = Project::find($projectId);
        ProjectMember::where("project_id", $projectId)->delete();
        ProjectUserTitles::where("project_id", $projectId)->whereNotIn("user_id", $members)->delete();
        ProjectPermission::where("project_id", $projectId)->whereNotIn("user_id", $members)->delete();
        ProjectLocationDetail::where("project_id", $projectId)->whereNotIn("user_id", $members)->delete();

        if (count($members) > 0 && !empty($members)) {
            foreach ($members as $memberId) {
                $member = new ProjectMember();
                $member->user_id = $memberId;
                $member->project_id = $projectId;
                $member->save();

                if(ProjectPermission::where("user_id", $memberId)->where("project_id", $projectId)->count() == 0)
                    $this->saveDefaultProjectPermissions($projectId, $memberId);
            }
        }

        $existinMembers = ProjectLocationDetail::where("project_id", $projectId)->pluck("user_id","user_id")->toArray();
        $users = array_diff($members, $existinMembers);

        if($project->project_type == 'survey'){
            $locations = ProjectDetail::where("project_id", $projectId)->get();

            foreach ($locations as $location) {
                foreach ($users as $userId) {
                    if ($location) {
                        $projectDetail = new ProjectLocationDetail();
                        $projectDetail->user_id = $userId;
                        $projectDetail->project_id = $projectId;
                        $projectDetail->site_id = $location->site_id;
                        $projectDetail->cluster_id = $location->cluster_id;
                        $projectDetail->district_id = $location->district_id;
                        $projectDetail->governorate_id = $location->governorate_id;
                        $projectDetail->save();
                    }
                }
            }
        }else{
            foreach ($users as $userId){
                if(\App\Models\SurveillanceLocation::where("user_id",$userId)->count() == 0)
                    $this->saveDefaultProjectLocations($projectId, $userId);
            }
        }

        $project = Project::with(["form", "members", "icdCode"])->find($projectId);
        if($project->project_type == 'surveillance'){
            $project['push_to_mobile'] = $this->pushToMobileStatus($projectId);
            $project['disease_categories'] = \App\Models\DiseaseCategory::with("diseases.icdCode")->has("diseases")->orderBy("id")->get();
            $project['disease_detail'] = $this->getProjectDiseaseData($projectId);
        }
        else
            $project['push_to_mobile'] = true;

        $project['members_list'] = $members;
        $project['members_detail'] = $this->getProjectMembers($projectId, $members);
        $project["permission_details"] = (count($this->getProjectPermissions($projectId, \Auth::user()->id))>0)?$this->getProjectPermissions($projectId, \Auth::user()->id):$this->getProjectDefaultPermissions(\Auth::user()->id);

        return $this->successData($project);
    }

    /**
     * @param $id
     * @return user permissions applied
     */
    public function getPermissionDetails($id, $projectId)
    {
        $id = (int) $id;
        $count = 1;
        $oldPermission = 0;
        $compelteList = [];
        $userPermissionsList = [];

        $userPermissions = ProjectPermission::where("user_id", $id)->where("project_id", $projectId)->orderBy("permission_id", "action_id")->get()->toArray();

        foreach ($userPermissions as $permission) {
            if ($oldPermission != $permission['permission_id'] && $oldPermission != 0) {
                $compelteList[] = $userPermissionsList;
                $userPermissionsList = [];
            }

            $userPermissionsList['id'] = $permission['permission_id'];
            $userPermissionsList['actions'][] = $permission['action_id'];

            if ($count == (count($userPermissions))) {
                $compelteList[] = $userPermissionsList;
            }

            $count++;
            $oldPermission = $permission['permission_id'];
        }

        return $compelteList;
    }

    /**
     * @param $id
     * @return project permissions applied
     */
    private function getProjectPermissions($projectId, $userId)
    {
        $completeList = [];
        $permissionsList = [];

        $permissions = \App\Models\Permission::get();
        $actions = \App\Models\Action::pluck('id', 'name')->toArray();
        $userRole = User::find($userId)->role_id;
        $userPermissions = ProjectPermission::where("user_id", $userId)->where("project_id", $projectId)->get()->toArray();

        $projectPermissionList = [];
        foreach ($userPermissions as $id => $value) {
            $projectPermissionList[$value['permission_id']][$value['action_id']] = 1;
        }

        foreach ($permissions as $permission) {
            $permissionsList['permission_id'] = $permission['id'];
            $permissionsList['name'] = $permission['name'];
            $permissionsList['code'] = $permission['code'];

            foreach ($actions as $action => $id) {
                $tempList['action_id'] = $id;
                $tempList['name'] = $action;

                if($userRole == 9)
                    $tempList['allowed'] = 1;
                else
                    $tempList['allowed'] = (int) @$projectPermissionList[$permission['id']][$id];

                $permissionsList['actions'][] = $tempList;
            }

            $completeList[] = $permissionsList;
            $permissionsList = [];
        }

        return $completeList;
    }

    /**
     * @param $id
     * @return project permissions applied
     */
    public function projectPermissions()
    {
        $userId = request("user_id");
        $projectId = request("project_id");

        $user = User::find($userId);

        $permissionList["titles"] = \Idea\Models\Role::whereIn("type", ["project","verifier"])->pluck('slug', 'id')->toArray();
        $permissionList["title_id"] = ($user->role_id == 12)?12:ProjectUserTitles::where("user_id", $userId)->where("project_id", $projectId)->pluck("title_id")->first();
        $permissionList["permissions"] = $this->getAllPermissions($projectId,$userId);
        $permissionList["permission_details"] = $this->getPermissionDetails($userId, $projectId);

        return $this->success('success', $permissionList);
    }

    /**
     * @param $id
     * @return get all project permissions
     */
    private function getAllPermissions($projectId,$userId)
    {
        $completeList = [];
        $permissionsList = [];

        $actions = \App\Models\Action::pluck('id', 'name')->toArray();
        $userPermissions = ProjectPermission::where("user_id", $userId)->where("project_id", $projectId)->get()->toArray();
        $userPermissionList = [];
        foreach ($userPermissions as $id => $value) {
            $userPermissionList[$value['permission_id']][$value['action_id']] = 1;
        }

        $permissions = \App\Models\Permission::where("id", "!=", 14)->get();
        foreach ($permissions as $permission) {
            $permissionsList['permission_id'] = $permission['id'];
            $permissionsList['name'] = $permission['name'];
            $permissionsList['code'] = $permission['code'];

            foreach ($actions as $action => $id) {
                $tempList['action_id'] = $id;
                $tempList['name'] = $action;
                $tempList['allowed'] = (int) @$userPermissionList[$permission['id']][$id];

                $permissionsList['actions'][] = $tempList;
            }

            $completeList[] = $permissionsList;
            $permissionsList = [];
        }

        return $completeList;
    }

    /**
     * @param $id
     * @return project default permissions applied
     */
    public function getProjectDefaultPermissions($userId)
    {
        $completeList = [];
        $permissionsList = [];

        $permissions = \App\Models\Permission::get();
        $actions = \App\Models\Action::pluck('id', 'name')->toArray();
        $userPermissions = DB::select(DB::raw("SELECT permission_id, action_id FROM default_project_permissions WHERE user_id='$userId'"));

        $titleId = 0;
        $userPermissionList = [];
        foreach ($userPermissions as $id => $value){
            $userPermissionList[$value->permission_id][$value->action_id] = 1;
        }
        foreach ($permissions as $permission) {
            $permissionsList['permission_id'] = $permission['id'];
            $permissionsList['name'] = $permission['name'];
            $permissionsList['code'] = $permission['code'];

            foreach ($actions as $action => $id) {
                $tempList['action_id'] = $id;
                $tempList['name'] = $action;
                $tempList['allowed'] = (int) @$userPermissionList[$permission['id']][$id];

                $permissionsList['actions'][] = $tempList;
            }

            $completeList[] = $permissionsList;
            $permissionsList = [];
        }

        return $completeList;
    }

    /**
     * @action save users permissions
     * @return user permissions
     */
    public function saveProjectPermissions()
    {
        $userId = request("user_id");
        $titleId = request("title_id");
        $projectId = request("project_id");
        $permissions = request("permissions");

        $user = User::find($userId);

        // A verifier cant be collector and vice versa
        if(in_array($titleId, array(12,13,14,15,16))){

            if($titleId == 12 && $user->role_id != 12)
                return $this->failed("This user cannot be a collector.");

//            $userVerifierCount = ProjectPermission::where("project_id", "!=", $projectId)->where("user_id", $userId)->where("title_id", 12)->count();

            if($titleId != 12 && $user->role_id == 12)
                return $this->failed("This user cannot be a verifier.");
        }

        $project = Project::find($projectId);
        ProjectPermission::where("user_id", $userId)->where("project_id", $projectId)->delete();
        ProjectUserTitles::where("user_id", $userId)->where("project_id", $projectId)->delete();
        $titles = \Idea\Models\Role::whereIn("type", ["project","verifier"])->pluck('slug', 'id')->toArray();

        $projectUserTitle = new ProjectUserTitles();
        $projectUserTitle->user_id = $userId;
        $projectUserTitle->title_id = $titleId;
        $projectUserTitle->project_id = $projectId;
        $projectUserTitle->save();

        foreach ($permissions as $id => $value) {
            $permissionId = $value['id'];
            $actions = $value['actions'];

            foreach ($actions as $id => $actionId) {
                $userPermission = new ProjectPermission();
                $userPermission->user_id = $userId;
                $userPermission->title_id = $titleId;
                $userPermission->action_id = $actionId;
                $userPermission->project_id = $projectId;
                $userPermission->permission_id = $permissionId;
                $userPermission->save();
            }
        }

        if($titleId == 12){ //save all the sites&clusters if user title is collector for this project
            ProjectLocationDetail::where("project_id", $projectId)->where("user_id", $userId)->delete();

            $locations = ProjectDetail::where("project_id", $projectId)->pluck("site_id", "cluster_id")->toArray();

            foreach ($locations as $cluster => $site) {

                if (is_numeric($cluster) && $cluster > 0) {
                    $projectDetail = new ProjectLocationDetail();
                    $projectDetail->user_id = $userId;
                    $projectDetail->project_id = $projectId;
                    $projectDetail->site_id = $site;
                    $projectDetail->cluster_id = $cluster;
                    $projectDetail->save();
                }
            }
        }

        $permissionList["titles"] = $titles;
        $permissionList["title_id"] = $titleId;
        $permissionList["permissions"] = $this->getAllPermissions($projectId,$userId);
        $permissionList["permission_details"] = $permissions;

        return $this->success('success', $permissionList);
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
        $project = Project::with("form") //, "members.user"
            ->withCount(
                [
                    "activeSites",
                    "inactiveSites",
                    "members" => function ($q) use ($id) {
                        $q->where('project_id', $id);
                    },
                ]
            )
            ->find($id);
        if (!$project) {
            return $this->failed("Invalid project Id");
        }

        if($project->project_type == 'surveillance'){
            $project['push_to_mobile'] = $this->pushToMobileStatus($id);
            $project['disease_categories'] = \App\Models\DiseaseCategory::with("diseases.icdCode")->has("diseases")->orderBy("id")->get();
            $project['disease_detail'] = $this->getProjectDiseaseData($id);
        }
        else
            $project['push_to_mobile'] = true;
        /*else{
            $project['sites'] = \App\Models\SiteReference::with("clusters")->has("clusters")->get();
            $project['project_detail'] = $this->getProjectSiteClusterData($id);
        }*/

        $project['sites'] = \App\Models\SiteReference::with("clusters")->has("clusters")->get();
        $project['members_list'] = ProjectMember::where("project_id", $id)->pluck("user_id")->toArray();
        $project['members_detail'] = $this->getProjectMembers($id, $project['members_list']);
        $project["permission_details"] = (count($this->getProjectPermissions($id, \Auth::user()->id))>0)?$this->getProjectPermissions($id, \Auth::user()->id):$this->getProjectDefaultPermissions(\Auth::user()->id);

        return $this->successData($project);
    }

    /**
     * @param int $projectId
     *
     * @return \Illuminate\Http\Response
     */
    private function pushToMobileStatus($projectId)
    {
        $form = Form::where("project_id", $projectId)->first();

        if($form)
            $formType = FormType::where("form_id", $form->id)->where("name_en", "Like", "Parameter Disease")->first();

        if($formType){
            $formCategories = FormCategory::where("form_id", $form->id)->where("form_type_id", $formType->id)->pluck("id", "id")->toArray();

            if($formCategories){
                $categoryGroups = \App\Models\QuestionGroup::whereIn("form_category_id", $formCategories)->pluck("id","id")->toArray();

                if($categoryGroups)
                    $groupQuestions = \App\Models\Question::whereIn("question_group_id", $categoryGroups)->get();
            }
        }

        if(count(@$groupQuestions) > 0)
            return true;
        else
            return false;
    }

    /**
     * Private method to get project members
     **/
    private function getProjectMembers($id, $members)
    {
        //$users = User::whereIn("id", $members)->get();
        if(!empty($members)){
            $users = User::whereIn('id', $members)
                ->orderBy(DB::raw('FIELD(`id`, '.implode(',', $members).')'))
                ->get();

            $userTitles = ProjectUserTitles::whereIn("user_id", $members)->where("project_id", $id)->pluck("title_id", "user_id")->toArray();

        }else
            $users = [];

        foreach($users as &$user){
            if($user->role_id == 12)
                $user['title'] = 'collector';
            else
                $user['title'] = \Idea\Models\Role::where("id", (int)@$userTitles[$user->id])->pluck("slug")->first();
        }
        return $users;
    }

     /**
     * Private method to project disease detail
     * Return data array
     **/
    private function getProjectDiseaseData($id)
    {
        $cnt = 1;
        $oldCat = 0;
        $dataList = [];
        $allData = [];

        $projectDisease = DiseaseDetail::where("project_id", $id)->orderBy("disease_category_id")->get();
        foreach ($projectDisease as $data) {
            if ($oldCat != $data['disease_category_id'] && $oldCat != 0) {
                $allData[] = $dataList;
                $dataList = [];
            }

            $dataList['id'] = $data['disease_category_id'];
            $dataList['diseases'][]['id'] = $data['disease_id'];

            if ($cnt == (count($projectDisease))) {
                $allData[] = $dataList;
            }

            $cnt++;
            $oldCat = $data['disease_category_id'];
        }

        $cData['categories'] = $allData;

        return $cData;
    }

     /**
     * Private method to project sites and clusters
      * Return data array
     **/
    private function getProjectSiteClusterData($id)
    {
        $cnt = 1;
        $oldSite = 0;
        $dataList = [];
        $allData = [];

        $projectDetails = ProjectDetail::where("project_id", $id)->orderBy("site_id")->get();
        foreach ($projectDetails as $data) {
            if ($oldSite != $data['site_id'] && $oldSite != 0) {
                $allData[] = $dataList;
                $dataList = [];
            }

            $dataList['id'] = $data['site_id'];
            $dataList['clusters'][]['id'] = $data['cluster_id'];

            if ($cnt == (count($projectDetails))) {
                $allData[] = $dataList;
            }

            $cnt++;
            $oldSite = $data['site_id'];
        }

        $cData['sites'] = $allData;

        return $cData;
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function store()
    {
        $project = new Project();
        $project->name = str_replace('/', '_',str_replace('\\', '_', request("name")));
        $project->project_type = request("project_type", 'survey');
        $project->description = request("description");
        $project->created_by = \Auth::user()->id;

        $project->date_start = \Carbon\Carbon::createFromFormat(
            'd/m/Y',
            request("date_start", date("d/m/Y"))
        )->toDateTimeString();

        $project->date_end = \Carbon\Carbon::createFromFormat(
            'd/m/Y',
            request("date_end", date("d/m/Y", strtotime("+30 days")))
        )->toDateTimeString();

        $project->status = request("status", 0);
        $project->save();

        if($project->project_type == 'survey'){
            $filters = new \App\Models\ResultFilter();
            $filters->title = "Date Range";
            $filters->date_from = $project->date_start;
            $filters->date_to = $project->date_end;
            $filters->save();
        }

        if (!empty($project->id)) {
            $this->attachImage($project, "logo");
        }

        //create new
        $form = new Form();
        $form->project_id = $project->id;
        $form->save();

        if(\Auth::user()->role_id != 9)
        {
            //create project member
            $projectMember = new ProjectMember();
            $projectMember->project_id = $project->id;
            $projectMember->user_id = \Auth::user()->id;
            $projectMember->save();

            //save default project permissions
            $this->saveDefaultProjectPermissions($project->id, \Auth::user()->id);
        }

        // Create Default Parameters
        $houseHoldParameter = new Parameter();
        $houseHoldParameter->name_en = 'Governorate';
        $houseHoldParameter->name_ar = 'Governorate';
        $houseHoldParameter->name_ku = 'Governorate';
        $houseHoldParameter->project_id = $project->id;
        $houseHoldParameter->allow_edit = 0;
        $houseHoldParameter->loop = 0;
        $houseHoldParameter->order = 1;
        $houseHoldParameter->save();

        $districtParameter = new Parameter();
        $districtParameter->name_en = 'District';
        $districtParameter->name_ar = 'District';
        $districtParameter->name_ku = 'District';
        $districtParameter->project_id = $project->id;
        $districtParameter->allow_edit = 0;
        $districtParameter->loop = 0;
        $districtParameter->order = 2;
        $districtParameter->save();


        $individualParameter = new Parameter();
        $individualParameter->name_en = 'Site/ Sub District';
        $individualParameter->name_ar = 'Site/ Sub District';
        $individualParameter->name_ku = 'Site/ Sub District';
        $individualParameter->project_id = $project->id;
        $individualParameter->allow_edit = 0;
        $individualParameter->loop = 0;
        $individualParameter->order = 3;
        $individualParameter->save();

        $individualParameter = new Parameter();
        $individualParameter->name_en = 'Cluster/ Camp Name/ PHC Name';
        $individualParameter->name_ar = 'Cluster/ Camp Name/ PHC Name';
        $individualParameter->name_ku = 'Cluster/ Camp Name/ PHC Name';
        $individualParameter->project_id = $project->id;
        $individualParameter->allow_edit = 0;
        $individualParameter->loop = 0;
        $individualParameter->order = 4;
        $individualParameter->save();

        if($project->project_type == 'surveillance'){
            $individualParameter = new Parameter();
            $individualParameter->name_en = 'Parameter Disease';
            $individualParameter->name_ar = 'Parameter Disease';
            $individualParameter->name_ku = 'Parameter Disease';
            $individualParameter->project_id = $project->id;
            $individualParameter->allow_edit = 1;
            $individualParameter->loop = 0;
            $individualParameter->order = 5;
            $individualParameter->save();

            $individualParameter = new Parameter();
            $individualParameter->name_en = 'Individual Information';
            $individualParameter->name_ar = 'Individual Information';
            $individualParameter->name_ku = 'Individual Information';
            $individualParameter->project_id = $project->id;
            $individualParameter->allow_edit = 0;
            $individualParameter->loop = 0;
            $individualParameter->order = 6;
            $individualParameter->save();
        }

        //Create Form Types
        $projectParameters = Parameter::where('project_id', $project->id)->get();
        foreach ($projectParameters as $projectParameter) {
            $formType = new FormType();
            $formType->parameter_id = $projectParameter->id;
            $formType->name_en = $projectParameter->name_en;
            $formType->name_ar = $projectParameter->name_ar;
            $formType->name_ku = $projectParameter->name_ku;
            $formType->form_id = $form->id;
            $formType->allow_edit = $projectParameter->allow_edit;
            $formType->loop = $projectParameter->loop;
            $formType->order = $projectParameter->order;
            $formType->save();

            if ($projectParameter->name_en == 'Site/ Sub District') {
                $this->addSiteData($form, $formType->id);
            } else if ($projectParameter->name_en == 'Cluster/ Camp Name/ PHC Name') {
                $this->addClusterData($form, $formType->id);
            }else if($projectParameter->name_en == 'Governorate'){
                $this->addGovernorateData($form, $formType->id);
            }
            else if($projectParameter->name_en == 'District'){
                $this->addDistrictData($form, $formType->id);
            }
            else if($projectParameter->name_en == 'Individual Information'){
                $this->addIndividualData($form, $formType->id);
            }
        }

        if($project->project_type == 'surveillance'){
                //$governorates = \App\Models\Governorate::pluck("id", "id")->toArray();
                //$districts = \App\Models\District::pluck("id", "id")->toArray();
                //$sites = \App\Models\SiteReference::pluck("id", "id")->toArray();
                $clusters = \App\Models\ClusterReference::pluck("id", "id")->toArray();
                $sites = ClusterReference::pluck("site_id","site_id")->toArray();
                $districts = SiteReference::whereIn("id",$sites)->pluck("district_id","district_id")->toArray();
                $governorates = SiteReference::whereIn("id",$sites)->pluck("governorate_id","governorate_id")->toArray();
                $this->addLocationQuestionOptions($project, $governorates, $districts, $sites, $clusters);
        }

        return $this->successData(Project::with("sites", "form")->find($project->id));
    }

    /**
     * @action save default project permissions
     * @return project permissions
     */
    private function saveDefaultProjectPermissions($projectId, $userId)
    {
        $defaultPermissions = DB::select(DB::raw("SELECT permission_id, action_id FROM default_project_permissions where user_id = '$userId'"));

        foreach ($defaultPermissions as $key => $object) {
            $userPermission = new ProjectPermission();
            $userPermission->user_id = $userId;
            $userPermission->project_id = $projectId;
            $userPermission->action_id = $object->action_id;
            $userPermission->permission_id = $object->permission_id;
            $userPermission->save();
        }
    }

    /**
     * @action save all project locations
     * @return project permissions
     */
    private function saveDefaultProjectLocations($projectId, $userId)
    {
            $clusters = \App\Models\ClusterReference::pluck("id", "id")->toArray();
            $sites = \App\Models\ClusterReference::pluck("site_id", "site_id")->toArray();
            $districts = \App\Models\SiteReference::whereIn("id", $sites)->pluck("district_id", "district_id")->toArray();
            $governorates = \App\Models\District::whereIn("id", $districts)->pluck("governorate_id", "governorate_id")->toArray();

            foreach($governorates as $governorateId)
            {
                $governorate = \App\Models\Governorate::find($governorateId);

                if(!$governorate)
                    continue;

                $governorateDistricts = \App\Models\District::where("governorate_id", $governorate->id)->pluck("id","id")->toArray();

                foreach($districts as $districtId)
                {
                    if(in_array($districtId, $governorateDistricts))
                    {
                        $district = \App\Models\District::find($districtId);
                        $dNewDataList = array('id'=>$district->id, 'name'=>$district->name, 'governorate_id'=>$district->governorate_id);
                        $districtSites = \App\Models\SiteReference::where("district_id", $districtId)->pluck("id","id")->toArray();

                        foreach($sites as $siteId)
                        {
                            if(in_array($siteId, $districtSites))
                            {
                                $site = \App\Models\SiteReference::find($siteId);
                                $sNewDataList = array('id'=>$site->id, 'name'=>$site->name, 'governorate_id'=>$site->governorate_id, 'district_id'=>$site->district_id);
                                $siteClusters = \App\Models\ClusterReference::where("site_id", $siteId)->pluck("id","id")->toArray();

                                foreach($clusters as $clusterId)
                                {
                                    if(in_array($clusterId, $siteClusters))
                                    {
                                        //save all the locations
                                        $projectLocationDetail = new ProjectLocationDetail();
                                        $projectLocationDetail->project_id = $projectId;
                                        $projectLocationDetail->user_id = $userId;
                                        $projectLocationDetail->governorate_id = $governorateId;
                                        $projectLocationDetail->district_id = $districtId;
                                        $projectLocationDetail->site_id = $siteId;
                                        $projectLocationDetail->cluster_id = $clusterId;
                                        $projectLocationDetail->save();
                                    }
                                }
                            }
                        }
                    }
                }
            }

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
        $project = Project::with("form", "icdCode")->find($id);
        if (!$project) {
            return $this->failed("Invalid Project");
        }

        $status = request("status");
        $imageDelete = request("image_deleted");

        $project->name = request("name");
        $project->project_type = request("project_type", 'survey');
        $project->description = request("description");
        $project->frontend_color = request("frontend_color");
        $project->frontend_icon = request("frontend_icon");
        $project->lat = request("lat");
        $project->lng = request("lng");

        $project->date_start = \Carbon\Carbon::createFromFormat(
            'd/m/Y',
            request("date_start", date("d/m/Y"))
        )->toDateTimeString();

        $project->date_end = \Carbon\Carbon::createFromFormat(
            'd/m/Y',
            request("date_end", date("d/m/Y", strtotime("+30 days")))
        )->toDateTimeString();

        if(isset($status))
            $project->status = request("status", 0);

        $project->save();

        if ($project->id > 0 && request("logo") != "" && isset($imageDelete) && $imageDelete == 0) {
            $this->attachImage($project, "logo");
        }

        if ($project->id > 0 && $imageDelete == 1) {
            $this->deleteImage($project, "logo");
        }

        if($project->project_type == 'surveillance'){
            $project['push_to_mobile'] = $this->pushToMobileStatus($id);
            $project['disease_categories'] = \App\Models\DiseaseCategory::with("diseases.icdCode")->has("diseases")->orderBy("id")->get();
            $project['disease_detail'] = $this->getProjectDiseaseData($id);
        }
        else
            $project['push_to_mobile'] = true;
        /*else{
            $project['sites'] = \App\Models\SiteReference::with("clusters")->has("clusters")->get();
            $project['project_detail'] = $this->getProjectSiteClusterData($id);
        }*/

        $project['members_list'] = ProjectMember::where("project_id", $id)->pluck("user_id")->toArray();
        $project['members_detail'] = $this->getProjectMembers($id, $project['members_list']);
        $project["permission_details"] = (count($this->getProjectPermissions($id, \Auth::user()->id))>0)?$this->getProjectPermissions($id, \Auth::user()->id):$this->getProjectDefaultPermissions(\Auth::user()->id);

        return $this->successData($project);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function updateStatus($id)
    {
        $project = Project::find($id);
        if (!$project) {
            return $this->failed("Invalid Project");
        }
        $status = request("status", -1);
        if ($status == -1 or $status > 2) {
            return $this->failed("Invalid Status");
        }

        $project->status = $status;
        $project->save();

        return $this->successData($project);
    }

    public function duration($id)
    {
        $project = Project::find($id);
        if (!$project) {
            return $this->failed("Invalid Project");
        }
        $project->date_start = \Carbon\Carbon::createFromFormat(
            'd/m/Y',
            request("date_start")
        )
            ->toDateTimeString();
        $project->date_end = \Carbon\Carbon::createFromFormat(
            'd/m/Y',
            request("date_end")
        )
            ->toDateTimeString();
        $project->save();

        return $this->successData($project);
    }

    public function goal($id)
    {

        $project = Project::find($id);
        if (!$project) {
            return $this->failed("Invalid Project");
        }
        $project->goal = request("goal");
        $project->save();

        return $this->successData($project);
    }

    public function description($id)
    {

        $project = Project::find($id);
        if (!$project) {
            return $this->failed("Invalid Project");
        }
        $project->description = request("description");
        $project->save();

        return $this->successData($project);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function destroyProjectMember()
    {

        $userId = request("user_id");
        $projectId = request("project_id");

        try {
            $projectMember = ProjectMember::where("user_id", $userId)->where("project_id", $projectId);
            if (!$projectMember) {
                return $this->failed("Invalid Project Member");
            }

            $projectMember->delete();

            return $this->success('Project Member deleted');
        } catch (\Exception $e) {
            return $this->failed($e->getMessage());
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
            if (!$project = Project::find($id)) {
                return $this->failed("Invalid Project");
            }

            //delete image if exist
            $this->deleteImage($project, "logo");

            //then delete the row from the database
            $project->delete();

            //delete all other related table entries for this project

            return $this->success('Project deleted');
        } catch (\Exception $e) {
            return $this->failed($e->getMessage());
        }
    }

    /**
     * @param $form
     *
     * @return \App\Http\Controllers\Projects\FormCategory
     */
    public function addSiteData($form, $typeId)
    {
        //create form category for sites
        $category = new FormCategory();
        $category->name_en = 'Site Information';
        $category->name_ar = 'Site Information';
        $category->name_ku = 'Site Information';
        $category->form_type_id = $typeId;
        $category->form_id = $form->id;
        $category->order = 1;
        $category->save();
        //site group
        $group = new QuestionGroup();
        $group->name = 'Site Group';
        $group->form_id = $form->id;
        $group->form_type_id = $typeId;
        $group->form_category_id = $category->id;
        $group->order_value = 1;
        $group->root_group = 1;
        $group->save();
        //name of the site
        $question1 = new Question();
        $question1->name_en = 'Name of the Site?';
        $question1->name_ar = 'اسم الموقع؟';
        $question1->name_ku = 'ناوی شوێن؟';
        $question1->question_code = 'Name of the Site?';
        $question1->required = 1;
        $question1->order = 1;
        $question1->response_type_id = 1;
        $question1->form_id = $form->id;
        $question1->question_group_id = $group->id;
        $question1->save();
    }

    /**
     * save project details
     * @return \App\Http\Controllers\Projects\projectDetils
     */
    public function projectDetails()
    {

        $projectId = request("project_id");
        $projectDetailsData = request("project_detail");

        ProjectDetail::where("project_id", $projectId)->delete();

        if (isset($projectDetailsData['sites']) && !empty(@$projectDetailsData['sites'])) {
            foreach ($projectDetailsData['sites'] as $data) {
                //save project details
                foreach (@$data['clusters'] as $clusterData) {
                    $clusterId = $clusterData['id'];
                    if (is_numeric($clusterId) && $clusterId > 0) {
                        $projectDetail = new ProjectDetail();
                        $projectDetail->project_id = $projectId;
                        $projectDetail->site_id = $data['id'];
                        $projectDetail->cluster_id = $clusterId;
                        $projectDetail->save();
                    }
                }
            }
        }

        $project = Project::with("referenceSites")->find($projectId);
        $project['project_detail'] = $projectDetailsData;

        return $this->successData($project);
    }

    /**
     * save project disease details
     * @return \App\Http\Controllers\Projects\projectDetils
     */
    public function projectDiseaseDetails()
    {
        $projectId = request("project_id");
        $projectDiseaseData = request("disease_detail");

        $allDiseases = DiseaseDetail::where("project_id", $projectId)->pluck("disease_id","disease_id")->toArray();
        DiseaseDetail::where("project_id", $projectId)->delete();

        $notToDeleteDiseases = [];
        if (isset($projectDiseaseData['categories']) && !empty(@$projectDiseaseData['categories'])) {
            foreach ($projectDiseaseData['categories'] as $data) {
                //save project details
                foreach (@$data['diseases'] as $diseaseData) {
                    $diseaseId = $diseaseData['id'];
                    if (is_numeric($diseaseId) && $diseaseId > 0) {
                        $diseaseDetail = new DiseaseDetail();
                        $diseaseDetail->project_id = $projectId;
                        $diseaseDetail->disease_category_id = $data['id'];
                        $diseaseDetail->disease_id = $diseaseId;
                        $diseaseDetail->save();

                        $notToDeleteDiseases[] = $diseaseId;
                        $this->addDiseaseFormQuestions($projectId, $diseaseId);
                    }
                }
            }
        }

        $deletedDiseases = array_diff($allDiseases,$notToDeleteDiseases);
        $this->deleteFormCategories($projectId, $deletedDiseases);
        $project = Project::with(["projectDisease.diseaseCategory", "projectDisease.icdCode"])->find($projectId);
        $project['disease_detail'] = $projectDiseaseData;

        if(count($notToDeleteDiseases) > 0)
            $project['push_to_mobile'] = true;
        else
            $project['push_to_mobile'] = false;

        return $this->successData($project);
    }

    /**
     * save project add disease details
     * @return void
     */
    private function addDiseaseFormQuestions($projectId, $diseaseId)
    {
        $form = Form::where("project_id", $projectId)->first();
        $disease = \App\Models\DiseaseBank::with("questions.questionDetails")->find($diseaseId);
        $formType = FormType::where("form_id", $form->id)->where("name_en", "Like", "Parameter Disease")->first();

        $notToDeleteQuestions = [];

        if($formType){
            //create form category for sites
            $category = FormCategory::firstOrCreate(['name_en' => $disease->appearance_name_en, 'form_type_id'=>$formType->id, 'form_id'=>$form->id]);
            $category->name_en = $disease->appearance_name_en;
            $category->name_ar = $disease->appearance_name_en;
            $category->name_ku = $disease->appearance_name_en;
            $category->form_type_id = $formType->id;
            $category->form_id = $form->id;
            $category->order = 1;
            $category->save();

            //site group
            $questionGroup = QuestionGroup::where('form_category_id', $category->id)->first();
            $group = ($questionGroup)?$questionGroup: new QuestionGroup();
            $group->name = $disease->appearance_name_en.' Group';
            $group->form_id = $form->id;
            $group->form_type_id = $formType->id;
            $group->form_category_id = $category->id;
            $group->order_value = 1;
            $group->root_group = 1;
            $group->save();

            foreach($disease->questions as $questions)
            {
                $qustionObj = Question::where("name_en", "LIKE", $questions->questionDetails->name_en)->where('question_group_id',$group->id)->first();
                $question = ($qustionObj)?$qustionObj: new Question();
                $question->name_en = @$questions->questionDetails->name_en;
                $question->name_ar = @$questions->questionDetails->name_ar;
                $question->name_ku = @$questions->questionDetails->name_ku;
                $question->question_code = @$questions->questionDetails->question_code;
                $question->required = @$questions->questionDetails->required;
                $question->order = @$questions->questionDetails->multiple;
                $question->response_type_id = @$questions->questionDetails->response_type_id;
                $question->form_id = @$form->id;
                $question->question_group_id = @$group->id;

                if(isset($questions->questionDetails->setting) && $questions->questionDetails->setting != "" && $questions->questionDetails->setting != null)
                    $question->setting = json_encode(@$questions->questionDetails->setting);

                $question->save();

                if(isset($questions->questionDetails->id))
                    $questionBankOptions = \App\Models\QuestionBankOption::where("question_id", $questions->questionDetails->id)->get();

                if(@$questionBankOptions)
                {
                    foreach($questionBankOptions as $id => $optionData)
                    {
                        $questionOptions = \App\Models\QuestionOption::where("name_en", "LIKE", $optionData['name_en'])->where('question_id',$question->id)->first();
                        $questionOptions                 = ($questionOptions)?$questionOptions: new \App\Models\QuestionOption();
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
                    $notToDeleteQuestions[] = $question->id;
            }
                Question::where("question_group_id", $group->id)->whereNotIn("id", $notToDeleteQuestions)->delete();
        }
    }

    /*
     * Delete form categories
     * return void
     * */
    private function deleteFormCategories($projectId, $deletedDiseases)
    {
        $form = Form::where("project_id", $projectId)->first();
        $formType = FormType::where("form_id", $form->id)->where("name_en", "Like", "Parameter Disease")->first();
        $diseases = \App\Models\DiseaseBank::whereIn("id", $deletedDiseases)->pluck("appearance_name_en", "appearance_name_en")->toArray();
        $categories = FormCategory::whereIn("name_en", $diseases)->where("form_type_id", $formType->id)->pluck("id", "id")->toArray();

        foreach($categories as $categoryId)
        {
            $group = QuestionGroup::where('form_category_id',$categoryId)->first();
            $questions = Question::where('question_group_id',$group->id)->pluck("id", "id")->toArray();

            \App\Models\QuestionOption::whereIn("question_id", $questions)->delete();
            Question::whereIn('id',$questions)->delete();
            QuestionGroup::where("id", $group->id)->delete();
            FormCategory::where("id", $categoryId)->delete();
        }
    }

    /**
     * get project details
     * @return \App\Http\Controllers\Projects\projectDetils
     */
    public function getProjectLocationDetails()
    {
        $user = request("user_id");
        $projectId = request("project_id");
        $project = Project::find($projectId);

        $governorates = ProjectLocationDetail::where("project_id", $projectId)->where("user_id", $user)->pluck("governorate_id", "governorate_id")->toArray();
        $districts = ProjectLocationDetail::where("project_id", $projectId)->where("user_id", $user)->pluck("district_id", "district_id")->toArray();
        $sites = ProjectLocationDetail::where("project_id", $projectId)->where("user_id", $user)->pluck("site_id", "site_id")->toArray();
        $clusters = ProjectLocationDetail::where("project_id", $projectId)->where("user_id", $user)->pluck("cluster_id", "cluster_id")->toArray();

        if($project->project_type == 'surveillance'){
            $selectedClusters = \App\Models\ClusterReference::pluck("id", "id")->toArray();
            $selectedSites = \App\Models\ClusterReference::pluck("site_id", "site_id")->toArray();
            $selectedDistricts = \App\Models\SiteReference::whereIn("id", $selectedSites)->pluck("district_id", "district_id")->toArray();
            $selectedGovernorates = \App\Models\District::whereIn("id", $selectedDistricts)->pluck("governorate_id", "governorate_id")->toArray();
        }else{
            $selectedGovernorates = ProjectDetail::where("project_id", $projectId)->orderBy("governorate_id")->pluck("governorate_id", "governorate_id")->toArray();
            $selectedDistricts = ProjectDetail::where("project_id", $projectId)->pluck("district_id", "district_id")->toArray();
            $selectedSites = ProjectDetail::where("project_id", $projectId)->pluck("site_id", "site_id")->toArray();
            $selectedClusters = ProjectDetail::where("project_id", $projectId)->pluck("cluster_id", "cluster_id")->toArray();
        }

        $dataList = [];
        foreach($selectedGovernorates as $governorateId)
        {
            $dDataList = [];
            $governorate = \App\Models\Governorate::find($governorateId);

            if($governorate)
            {
                $gDataList = array('id'=>$governorate->id, 'name'=>$governorate->name." ("."Governorate)", 'checked'=>(in_array($governorate->id, $governorates)?true:false));

                $governorateDistricts = \App\Models\District::where("governorate_id", $governorate->id)->pluck("id","id")->toArray();
                foreach($selectedDistricts as $districtId)
                {
                    if(in_array($districtId, $governorateDistricts))
                    {
                        $sDataList = [];
                        $district = \App\Models\District::find($districtId);
                        $dNewDataList = array('id'=>$district->id, 'name'=>$district->name." ("."District)", 'governorate_id'=>$district->governorate_id, 'checked'=>(in_array($district->id, $districts)?true:false));

                        $districtSites = \App\Models\SiteReference::where("district_id", $districtId)->pluck("id","id")->toArray();
                        foreach($selectedSites as $siteId)
                        {
                            if(in_array($siteId, $districtSites))
                            {
                                $cDataList = [];
                                $site = \App\Models\SiteReference::find($siteId);
                                $sNewDataList = array('id'=>$site->id, 'name'=>$site->name." ("."Site)", 'governorate_id'=>$site->governorate_id, 'district_id'=>$site->district_id, 'checked'=>(in_array($site->id, $sites)?true:false));

                                $siteClusters = \App\Models\ClusterReference::where("site_id", $siteId)->pluck("id","id")->toArray();
                                foreach($selectedClusters as $clusterId)
                                {
                                    if(in_array($clusterId, $siteClusters))
                                    {
                                        $cluster = \App\Models\ClusterReference::find($clusterId);
                                        $cDataList[] = array('id'=>$cluster->id, 'name'=>$cluster->name." ("."Cluster)", 'site_id'=>$cluster->site_id, 'checked'=>(in_array($cluster->id, $clusters)?true:false));
                                    }
                                }
                                $sDataList[] = $sNewDataList + array('children'=>$cDataList);
                            }
                        }
                        $dDataList[] = $dNewDataList + array('children'=>$sDataList);
                    }
                }
                $dataList[] = $gDataList + array('children'=>$dDataList);
            }
        }

        if(empty($dataList))
        {
            $clusters = \App\Models\ClusterReference::pluck("id", "id")->toArray();
            $sites = \App\Models\ClusterReference::pluck("site_id", "site_id")->toArray();
            $districts = \App\Models\SiteReference::whereIn("id", $sites)->pluck("district_id", "district_id")->toArray();
            $governorates = \App\Models\District::whereIn("id", $districts)->pluck("governorate_id", "governorate_id")->toArray();

            foreach($governorates as $governorateId)
            {
                $dDataList = [];
                $governorate = \App\Models\Governorate::find($governorateId);

                if($governorate)
                {
                    $gDataList = array('id'=>$governorate->id, 'name'=>$governorate->name, 'checked'=>false);

                    $governorateDistricts = \App\Models\District::where("governorate_id", $governorate->id)->pluck("id","id")->toArray();
                    foreach($districts as $districtId)
                    {
                        if(in_array($districtId, $governorateDistricts))
                        {
                            $sDataList = [];
                            $district = \App\Models\District::find($districtId);
                            $dNewDataList = array('id'=>$district->id, 'name'=>$district->name, 'governorate_id'=>$district->governorate_id, 'checked'=>false);

                            $districtSites = \App\Models\SiteReference::where("district_id", $districtId)->pluck("id","id")->toArray();
                            foreach($sites as $siteId)
                            {
                                if(in_array($siteId, $districtSites))
                                {
                                    $cDataList = [];
                                    $site = \App\Models\SiteReference::find($siteId);
                                    $sNewDataList = array('id'=>$site->id, 'name'=>$site->name, 'governorate_id'=>$site->governorate_id, 'district_id'=>$site->district_id, 'checked'=>false);

                                    $siteClusters = \App\Models\ClusterReference::where("site_id", $siteId)->pluck("id","id")->toArray();
                                    foreach($clusters as $clusterId)
                                    {
                                        if(in_array($clusterId, $siteClusters))
                                        {
                                            $cluster = \App\Models\ClusterReference::find($clusterId);
                                            $cDataList[] = array('id'=>$cluster->id, 'name'=>$cluster->name, 'site_id'=>$cluster->site_id, 'checked'=>false);
                                        }
                                    }
                                    $sDataList[] = $sNewDataList + array('children'=>$cDataList);
                                }
                            }
                            $dDataList[] = $dNewDataList + array('children'=>$sDataList);
                        }
                    }
                    $dataList[] = $gDataList + array('children'=>$dDataList);
                }
            }
        }

        return $this->successData($dataList);
    }

     /**
     * save project details
     * @return \App\Http\Controllers\Projects\projectDetils
     */
    public function saveProjectLocationDetails()
    {
        $userId = request("user_id");
        $projectId = request("project_id");
        $dataList = request("data");

        ProjectLocationDetail::where("project_id", $projectId)->where("user_id", $userId)->delete();

         foreach($dataList as $data){
                $governorateId = $data['id'];
            if(isset($data['children'])){
                foreach($data['children'] as $district)
                {
                    $districtId = $district['id'];
                    if(isset($district['children'])){
                        foreach($district['children'] as $site)
                        {
                            $siteId = $site['id'];
                            if(isset($site['children'])){
                                foreach($site['children'] as $cluster)
                                {
                                    $clusterId = $cluster['id'];

                                    if($cluster['checked'] == true)
                                    {
                                        $clusterObj = \App\Models\ClusterReference::find($clusterId);
                                        $siteObj = \App\Models\SiteReference::find($clusterObj->site_id);

                                        $projectLocationDetail = new ProjectLocationDetail();
                                        $projectLocationDetail->project_id = $projectId;
                                        $projectLocationDetail->user_id = $userId;
                                        $projectLocationDetail->governorate_id = $siteObj->governorate_id;
                                        $projectLocationDetail->district_id = $siteObj->district_id;
                                        $projectLocationDetail->site_id = $clusterObj->site_id;
                                        $projectLocationDetail->cluster_id = $clusterId;
                                        $projectLocationDetail->save();
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        $governorates = ProjectLocationDetail::where("project_id", $projectId)->where("user_id", $userId)->pluck("governorate_id", "governorate_id")->toArray();
        $districts = ProjectLocationDetail::where("project_id", $projectId)->where("user_id", $userId)->pluck("district_id", "district_id")->toArray();
        $sites = ProjectLocationDetail::where("project_id", $projectId)->where("user_id", $userId)->pluck("site_id", "site_id")->toArray();
        $clusters = ProjectLocationDetail::where("project_id", $projectId)->where("user_id", $userId)->pluck("cluster_id", "cluster_id")->toArray();
        $NewDataList = \App\Models\Governorate::with("children.children.children")->orderBy("name")->get();

        foreach($NewDataList as $data){
                $data->name = $data->name." ("."Governorate)";
            if(in_array($data->id, $governorates))
                $data->checked = true;
            else
                $data->checked = false;

            if(isset($data->children)){
                foreach($data->children as $district)
                {
                    $district->name = $district->name." ("."District)";

                    if(in_array($district->id, $districts))
                        $district->checked = true;
                    else
                        $district->checked = false;

                    if(isset($district->children)){
                        foreach($district->children as $site)
                        {
                            $site->name = $site->name." ("."Site)";

                            if(in_array($site->id, $sites))
                                $site->checked = true;
                            else
                                $site->checked = false;

                            if(isset($site->children)){
                                foreach($site->children as $cluster)
                                {
                                    $cluster->name = $cluster->name." ("."Cluster)";

                                    if(in_array($cluster->id, $clusters))
                                        $cluster->checked = true;
                                    else
                                        $cluster->checked = false;
                                }
                            }
                        }
                    }
                }
            }
        }

        return $this->successData($NewDataList);
    }

    /**
     * @param $form
     *
     * @return \App\Http\Controllers\Projects\FormCategory
     */
    public function addClusterData($form, $typeId)
    {
        //create form category for sites
        $category = new FormCategory();
        $category->name_en = 'Cluster Information';
        $category->name_ar = 'Cluster Information';
        $category->name_ku = 'Cluster Information';
        $category->form_type_id = $typeId;
        $category->form_id = $form->id;
        $category->order = 1;
        $category->save();

        //site group
        $group = new QuestionGroup();
        $group->name = 'Cluster Group';
        $group->form_id = $form->id;
        $group->form_type_id = $typeId;
        $group->form_category_id = $category->id;
        $group->order_value = 1;
        $group->root_group = 1;
        $group->save();

        //name of the Cluster
        $question1 = new Question();
        $question1->name_en = 'Name of Cluster?';
        $question1->name_ar = 'اسم التجمع؟';
        $question1->name_ku = 'ناوی کۆمەڵ؟';
        $question1->question_code = 'Name of Cluster?';
        $question1->required = 1;
        $question1->order = 1;
        $question1->response_type_id = 1;
        $question1->form_id = $form->id;
        $question1->question_group_id = $group->id;
        $question1->save();

        //name of the site
        $question1 = new Question();
        $question1->name_en = 'GPS of Cluster?';
        $question1->name_ar = 'GPS of Cluster?';
        $question1->name_ku = 'GPS of Cluster?';
        $question1->question_code = 'GPS of Cluster?';
        $question1->required = 1;
        $question1->order = 1;
        $question1->response_type_id = 7;
        $question1->form_id = $form->id;
        $question1->question_group_id = $group->id;
        $question1->save();
    }

    /**
    * @param $form, $typeId
    *
    * @return \App\Http\Controllers\Projects\FormCategory
    */
    public function addIndividualData($form, $typeId)
    {
        //create form category for sites
        $category = new FormCategory();
        $category->name_en = 'Individual Category';
        $category->name_ar = 'Individual Category';
        $category->name_ku = 'Individual Category';
        $category->form_type_id = $typeId;
        $category->form_id = $form->id;
        $category->order = 1;
        $category->save();

        //site group
        $group = new QuestionGroup();
        $group->name = 'Individual Group';
        $group->form_id = $form->id;
        $group->form_type_id = $typeId;
        $group->form_category_id = $category->id;
        $group->order_value = 1;
        $group->root_group = 1;
        $group->save();

        //What is your gender
        $question1 = new Question();
        $question1->name_en = 'Gender?';
        $question1->name_ar = 'الجنس؟';
        $question1->name_ku = 'ڕەگەز؟';
        $question1->question_code = 'Gender?';
        $question1->required = 1;
        $question1->order = 1;
        $question1->response_type_id = 1;
        $question1->form_id = $form->id;
        $question1->question_group_id = $group->id;
        $question1->save();

        $genders = [0=>"Male",1=>"Female"];
        $gendersArabic = [0=>"ذكر",1=>"انثى"];
        $gendersKurdish = [0=>"نێر",1=>"مێ"];

        foreach($genders as $key => $gender){
            $questionOptions                 = new \App\Models\QuestionOption();
            $questionOptions->name_en        = $gender;
            $questionOptions->name_ar        = $gendersArabic[$key];
            $questionOptions->name_ku        = $gendersKurdish[$key];
            $questionOptions->question_id    = $question1->id;
            $questionOptions->stop_collect   = 0;
            $questionOptions->save();

            if($questionOptions->config_id == 0 || $questionOptions->config_id == null){
                $questionOptions->config_id = $questionOptions->id;
                $questionOptions->save();
            }
        }
        //What is your age
        $question2 = new Question();
        $question2->name_en = 'Age?';
        $question2->name_ar = 'العمر؟';
        $question2->name_ku = 'تەمەن؟';
        $question2->question_code = 'Age?';
        $question2->required = 1;
        $question2->order = 2;
        $question2->response_type_id = 4;
        $question2->form_id = $form->id;
        $question2->question_group_id = $group->id;
        $question2->setting = json_encode('{format_value: "", prefixes_value: "", suffixes_value: "years", minimum_value: "", maximum_value: ""}');
        $question2->save();

        $questionOption = new QuestionSettingOptions();
        $questionOption->guide_en       = "For Age in Months, use the following: 0.083 (1M), 0.167 (2M), 0.25 (3M) 0.33 (4M), 0.417 (5M), 0.5 (6M), 0.583 (7M), 0.667 (8M) 0.75 (9M), 0.83 (10M), 0.917 (11M)!";
        $questionOption->guide_ar       = "For Age in Months, use the following: 0.083 (1M), 0.167 (2M), 0.25 (3M) 0.33 (4M), 0.417 (5M), 0.5 (6M), 0.583 (7M), 0.667 (8M) 0.75 (9M), 0.83 (10M), 0.917 (11M)!";
        $questionOption->guide_ku       = "For Age in Months, use the following: 0.083 (1M), 0.167 (2M), 0.25 (3M) 0.33 (4M), 0.417 (5M), 0.5 (6M), 0.583 (7M), 0.667 (8M) 0.75 (9M), 0.83 (10M), 0.917 (11M)!";
        $questionOption->project_id     = $form->project_id;
        $questionOption->form_id        = $form->id;
        $questionOption->question_id    = $question2->id;
        $questionOption->save( );
    }

    /**
    * @param $form
    *
    * @return \App\Http\Controllers\Projects\FormCategory
    */
    public function addDistrictData($form, $typeId)
    {
        //create form category for sites
        $category = new FormCategory();
        $category->name_en = 'District Information';
        $category->name_ar = 'District Information';
        $category->name_ku = 'District Information';
        $category->form_type_id = $typeId;
        $category->form_id = $form->id;
        $category->order = 1;
        $category->save();

        //site group
        $group = new QuestionGroup();
        $group->name = 'District Group';
        $group->form_id = $form->id;
        $group->form_type_id = $typeId;
        $group->form_category_id = $category->id;
        $group->order_value = 1;
        $group->root_group = 1;
        $group->save();

        //name of the Governorate
        $question1 = new Question();
        $question1->name_en = 'Name of District?';
        $question1->name_ar = 'اسم القطاع؟';
        $question1->name_ku = 'ناوى کەرت؟';
        $question1->question_code = 'Name of District?';
        $question1->required = 1;
        $question1->order = 1;
        $question1->response_type_id = 1;
        $question1->form_id = $form->id;
        $question1->question_group_id = $group->id;
        $question1->save();
    }

    /**
     * @param $form
     *
     * @return \App\Http\Controllers\Projects\FormCategory
     */
    public function addGovernorateData($form, $typeId)
    {
        //create form category for sites
        $category = new FormCategory();
        $category->name_en = 'Governorate Information';
        $category->name_ar = 'Governorate Information';
        $category->name_ku = 'Governorate Information';
        $category->form_type_id = $typeId;
        $category->form_id = $form->id;
        $category->order = 1;
        $category->save();

        //site group
        $group = new QuestionGroup();
        $group->name = 'Governorate Group';
        $group->form_id = $form->id;
        $group->form_type_id = $typeId;
        $group->form_category_id = $category->id;
        $group->order_value = 1;
        $group->root_group = 1;
        $group->save();

        //name of the Governorate
        $question1 = new Question();
        $question1->name_en = 'Name of Governorate?';
        $question1->name_ar = 'اسم المحافظة؟';
        $question1->name_ku = 'ناوی پارێزگا؟';
        $question1->question_code = 'Name of Governorate?';
        $question1->required = 1;
        $question1->order = 1;
        $question1->response_type_id = 1;
        $question1->form_id = $form->id;
        $question1->question_group_id = $group->id;
        $question1->save();
    }

    /**
     * @param $projectId
     *
     * @return new project
     */
    public function duplicate($id)
    {
        DB::beginTransaction();

        $sitesMap = [];
        $teamMap = [];
        $clusterMap = [];

        if (!$project = Project::with(
            "form",
            "sites",
            "teams",
            "sites.clusters",
            "members"
        )->find($id)
        ) {
            return $this->failed("Invalid Project");
        }

        $newProject = $project->replicate();
        $newProject->save();
        $newProject->name = $project->name . " Duplicated";
        $newProject->description = $project->description . " Duplicated";
        $newProject->save();

        //save diseases specified for surveillance
        if($project->project_type == 'surveillance'){
            $dieaseDetail = DiseaseDetail::where("project_id", $project->id)->get();
            foreach ($dieaseDetail as $detail) {
                $newDetail = new DiseaseDetail();
                $newDetail->project_id = $newProject->id;
                $newDetail->disease_category_id = $detail->disease_category_id;
                $newDetail->disease_id = $detail->disease_id;
                $newDetail->save();
            }
        }

        //save project filters
        $filters = new \App\Models\ResultFilter();
        $filters->title = "Date Range";
        $filters->date_from = $newProject->date_start;
        $filters->date_to = $newProject->date_end;
        $filters->save();

        //copy project members
        foreach ($project->members as $member) {
            $newMember = $member->replicate();
            $newMember->project_id = $newProject->id;
            $newMember->team_id = empty($member->team_id) || !isset($teamMap[$member->team_id]) ? null :
            $teamMap[$member->team_id];
            $newMember->save();
        }

        //duplicate locations
        $projectDetails = ProjectDetail::where("project_id", $id)->get();
        foreach($projectDetails as $detail){
            $newDetail = new ProjectDetail();
            $newDetail->project_id = $newProject->id;
            $newDetail->governorate_id = $detail->governorate_id;
            $newDetail->district_id = $detail->district_id;
            $newDetail->site_id = $detail->site_id;
            $newDetail->cluster_id = $detail->cluster_id;
            $newDetail->save();
        }

        //duplicate user locations
        $projectLocationDetails = ProjectLocationDetail::where("project_id", $id)->get();
        foreach($projectLocationDetails as $detail){
            $newDetail = new ProjectLocationDetail();
            $newDetail->project_id = $newProject->id;
            $newDetail->governorate_id = $detail->governorate_id;
            $newDetail->district_id = $detail->district_id;
            $newDetail->site_id = $detail->site_id;
            $newDetail->cluster_id = $detail->cluster_id;
            $newDetail->user_id = $detail->user_id;
            $newDetail->save();
        }

        //copy form
        $form = new Form();
        $form->project_id = $newProject->id;
        $form->save();

        //form_types
        if (isset($project->form->id)) {
            $formData = \App\Types\FormType::getForm($project->form->id);

            foreach ($formData->types as $type) {

                //$paramId = FormType::where("id", $type->id)->pluck("parameter_id")->first();
                $parameter = Parameter::find($type->parameter_id);
                $newParam = $parameter->replicate();
                $newParam->project_id = $newProject->id;
                $newParam->save();

                $newType = $type->replicate();
                $newType->form_id = $form->id;
                $newType->parameter_id = $newParam->id;
                $newType->save();
                foreach ($type->categories as $category) {
                    $newCategory = $category->replicate();
                    unset($newCategory->groups);
                    $newCategory->form_id = $form->id;
                    $newCategory->form_type_id = $newType->id;
                    $newCategory->save();

                    //save group and question
                    $this->cloneGroupQuestion(
                        $newProject->id,
                        $id,
                        $category->groups,
                        $form->id,
                        $newType->id,
                        $newCategory->id,
                        null
                    );
                }
            }
        }

        //indicators
        foreach ($project->indicators as $indicator) {
            $newIndicator = $indicator->replicate();
            $newIndicator->project_id = $newProject->id;
            $this->updateArithmetic($newIndicator, $indicator->arithmetic);
            $newIndicator->save();
        }
        DB::commit();
        return $this->successData($newProject);
    }

    /**
     * the following method is used to update the arithmetic
     *
     * @param  [type] $newIndicator
     * @param  [type] $oldArithmetic
     * @return void
     */
    private function updateArithmetic($newIndicator, $oldArithmetic)
    {
        // the following logic is done to update the arthematic of the new duplicated project
        $arithmetic = str_replace(" ", "", strtolower($oldArithmetic));
        $arithmetic = str_replace("x", "*", $arithmetic);
        //find all question id inside [] like [id_12312] where 12312 is the question id
        preg_match_all('#\[(.*?)\]#', $arithmetic, $match);
        if (empty($match[1])) {
            return;
        }
        foreach ($match[1] as $question) {
            $options = [];
            //if greater then 1 , mean it's a question id plus option id for this question
            $questionId = str_replace("id_", "", $question);
            if (substr_count($question, '_') > 1) {
                $options = explode("_", str_replace("id_", "", $question));
                $questionId = $options[0];
                unset($options[0]);
            }

            // replacing question with new only in the first part i.e. id_4363_14535 in string only, not in the complete section
            // we check for isset to make sure deleted Questions( thats still exists in the arithmetic) done create issue
            if (isset($this->questionMap[$questionId])) {
                $newArithmetic = str_replace_first($questionId, $this->questionMap[$questionId], $question);
            } else {
                continue; // incase its not set the arithmetic will be duplicated as it is so just pass the current loop
            }
            // now going through all the options of the respective question
            foreach ($options as $optionId) {
                // replacing option with new only in the first part i.e. id_4363_14535 in string only, not in the complete section
                // we check for isset to make sure deleted Options( thats still exists in the arithmetic) done create issue
                if (isset($this->optionMap[$optionId])) {
                    $newArithmetic = str_replace($optionId, $this->optionMap[$optionId], $newArithmetic);
                } else {
                    continue;
                }
            }
            $newIndicator->arithmetic = str_replace_first($question, $newArithmetic, $newIndicator->arithmetic);
        }
        $newIndicator->save();
    }

    private function cloneGroupQuestion(
        $projectId,
        $oldProjectId,
        $groups,
        $formId,
        $typeId,
        $categoryId,
        $parent = null
    ) {

        foreach ($groups as $group) {
            $newGroup = $group->replicate();
            $newGroup->form_id = $formId;
            $newGroup->form_type_id = $typeId;
            $newGroup->form_category_id = $categoryId;
            $newGroup->parent_group = $parent;
            $newGroup->save();

            //Adding questions
            if (!empty($group->questions)) {
                foreach ($group->questions as $question) {

                    $newQuestion = $question->replicate();
                    $newQuestion->form_id = $formId;
                    $newQuestion->question_group_id = $newGroup->id;
                    $newQuestion->save();

                    //copy skip logic
                    $skipLogic = SkipLogicQuestion::where("parent_id", $question->id)->get();

                    if (count($skipLogic) > 0) {
                        foreach ($skipLogic as $id => $data) {
                            $skipLogicQuestion = new SkipLogicQuestion();
                            $skipLogicQuestion->question_id = $data['question_id'];
                            $skipLogicQuestion->operator_id = $data['operator_id'];
                            $skipLogicQuestion->condition_id = $data['condition_id'];
                            $skipLogicQuestion->project_id = $projectId;
                            $skipLogicQuestion->form_id = $formId;
                            $skipLogicQuestion->parent_id = $newQuestion->id;
                            $skipLogicQuestion->save();

                            $skipLogicDetail = SkipLogicQuestionDetail::where("skip_logic_id", $data['id'])->get();

                            foreach ($skipLogicDetail as $detailData) {
                                $skipLogicQuestionDetail = new SkipLogicQuestionDetail();
                                $skipLogicQuestionDetail->skip_logic_id = $skipLogicQuestion->id;
                                $skipLogicQuestionDetail->question_id = $data['question_id'];
                                $skipLogicQuestionDetail->parent_id = $newQuestion->id;
                                $skipLogicQuestionDetail->operator_id = $data['operator_id'];
                                $skipLogicQuestionDetail->option_value_id = $detailData['option_value_id'];
                                $skipLogicQuestionDetail->option_value = $detailData['option_value'];
                                $skipLogicQuestionDetail->save();
                            }
                        }
                    }

                    //update setting options
                    $questionOptions = QuestionSettingOptions::where("question_id", $question->id)->where("project_id", $oldProjectId)->get();

                    if (count($questionOptions) > 0) {
                        $questionOption = new QuestionSettingOptions();
                        $questionOption->guide_en = $questionOptions[0]->guide_en;
                        $questionOption->guide_ar = $questionOptions[0]->guide_ar;
                        $questionOption->guide_ku = $questionOptions[0]->guide_ku;
                        $questionOption->note_en = $questionOptions[0]->note_en;
                        $questionOption->note_ar = $questionOptions[0]->note_ar;
                        $questionOption->note_ku = $questionOptions[0]->note_ku;
                        $questionOption->project_id = $projectId;
                        $questionOption->form_id = $formId;
                        $questionOption->question_id = $newQuestion->id;
                        $questionOption->save();
                    }

                    //update setting appearance
                    $questionAppearanceData = QuestionSettingAppearance::where("question_id", $question->id)->where("project_id", $oldProjectId)->get();

                    if (count($questionAppearanceData) > 0) {
                        $questionAppearance = new QuestionSettingAppearance();
                        $questionAppearance->font = $questionAppearanceData[0]->font;
                        $questionAppearance->color = $questionAppearanceData[0]->color;
                        $questionAppearance->highlight = $questionAppearanceData[0]->highlight;
                        $questionAppearance->positioning = $questionAppearanceData[0]->positioning;
                        $questionAppearance->capitalization = $questionAppearanceData[0]->capitalization;
                        $questionAppearance->font_style = $questionAppearanceData[0]->font_style;
                        $questionAppearance->project_id = $projectId;
                        $questionAppearance->form_id = $formId;
                        $questionAppearance->question_id = $newQuestion->id;
                        $questionAppearance->save();
                    }

                    $this->questionMap[$question->id] = $newQuestion->id;

                    //adding options
                    if (!empty($question->options)) {
                        foreach ($question->options as $option) {

                            /*$newOption = $option->replicate();
                            $newOption->question_id = $newQuestion->id;
                            $newOption->save();*/
                            $newOption = new \App\Models\QuestionOption();
                            $newOption->name_en = $this->checkValue($option, 'name_en');
                            $newOption->name_ar = $this->checkValue($option, 'name_ar');
                            $newOption->name_ku = $this->checkValue($option, 'name_ku');
                            $newOption->question_id = $newQuestion->id;
                            $newOption->order_value = @$option['order_value'];
                            $stopCollect = $this->checkValue($option, 'stop_collect', 0);
                            $newOption->stop_collect = $stopCollect ? 1 : 0;
                            $newOption->save();

                            if($newOption->config_id == 0 || $newOption->config_id == null){
                                $newOption->config_id = $newOption->id;
                                $newOption->save();
                            }
                            // keeping the new option id against the old option id
                            $this->optionMap[$option->id] = $newOption->id;

                        }
                    }
                }
            }
            //adding conditions
            if (!empty($group->conditions)) {
                foreach ($group->conditions as $condition) {
                    // if the question id exists
                    if (isset($this->questionMap[$condition->question_id])) {
                        $newCondition = $condition->replicate();
                        $newCondition->question_group_id = $newGroup->id;
                        $newCondition->question_id = $this->questionMap[$condition->question_id];
                        // incase the type is = then the respective condition value is the option id of the respective question
                        // and we get this from our mapping where we stored the value of the new option id against the index of the old option id
                        // which is $condition->value incase the type is =
                        if ($condition->type === "=" && isset($this->optionMap[$condition->value])) {
                            $newCondition->value = $this->optionMap[$condition->value];
                        };
                        $newCondition->save();
                    }
                }
            }

            if (!empty($group->children)) {
                $this->cloneGroupQuestion(
                    $projectId,
                    $oldProjectId,
                    $group->children,
                    $formId,
                    $typeId,
                    $categoryId,
                    $newGroup->id
                );
            }
        }
    }

    /**
     * @param $user
     * @param $look
     *
     * @return array
     */
    protected function attachImage($model, $imageName, $autoSave = true)
    {
        //remove existing image
        $this->deleteImage($model, $imageName);

        $model->{$imageName} = $this->attachImageBase(
            $model,
            request($imageName)
        );

        if ($autoSave) {
            $model->save();
        }

        return $model;
    }

    /*
     * converting the base64 to an actual image
     */
    public function attachImageBase($model, $base64)
    {

        //if empty return empty, not sure if we may need to return an exception
        if (empty($base64)) {
            return "";
        }

        //generating a random file and specify the right folder
        $fileName = rand() . "-" . time() . ".png";
        $folderPath = public_path('files/projects/' . $model->id);

        //if folder not exist create it
        if (!is_dir($folderPath)) {
            mkdir($folderPath, 0777, true);
        }
        $path = $folderPath . '/' . $fileName;

        //finally save the image
        try {
            Image::make(file_get_contents($base64))->save($path);

            return 'files/projects/' . $model->id . "/" . $fileName;
        } catch (\Exception $e) {
            return "";
        }
    }

    /**
     * get project success message
    **/
    public function projectData($data)
    {
        $data = json_decode(json_encode($data), 1);
        $data = isset($data[0])?$data[0]:[];
        return $data;
    }

    /**
     * get icd-codes
    **/
    public function icdCodes()
    {
        $searchQuery = !empty(request('query')) ? request('query') : "";
        $data = \App\Models\IcdCode::where(function ($q) use ($searchQuery) {
            $q->where('code', 'LIKE', "%" . $searchQuery . "%");
            $q->orWhere('disease_name', 'LIKE', "%" . $searchQuery . "%");
        })->take(10)->get();

        return $this->successData($data);
    }

    /**
     * @return all locations
    **/
    public function getSpecifyLocations()
    {
        $projectId = request("project_id");
        $dataList = \App\Models\Governorate::with("children.children.children")->orderBy("name")->get();
        $governorates = ProjectDetail::where("project_id", $projectId)->pluck("governorate_id", "governorate_id")->toArray();
        $districts = ProjectDetail::where("project_id", $projectId)->pluck("district_id", "district_id")->toArray();
        $sites = ProjectDetail::where("project_id", $projectId)->pluck("site_id", "site_id")->toArray();
        $clusters = ProjectDetail::where("project_id", $projectId)->pluck("cluster_id", "cluster_id")->toArray();

        foreach($dataList as $data){
                $data->name = $data->name." ("."Governorate)";
            if(in_array($data->id, $governorates))
                $data->checked = true;
            else
                $data->checked = false;

            if(isset($data->children)){
                foreach($data->children as $district)
                {
                    $district->name = $district->name." ("."District)";

                    if(in_array($district->id, $districts))
                        $district->checked = true;
                    else
                        $district->checked = false;

                    if(isset($district->children)){
                        foreach($district->children as $site)
                        {
                            $site->name = $site->name." ("."Site)";

                            if(in_array($site->id, $sites))
                                $site->checked = true;
                            else
                                $site->checked = false;

                            if(isset($site->children)){
                                foreach($site->children as $cluster)
                                {
                                    $cluster->name = $cluster->name." ("."Cluster)";

                                    if(in_array($cluster->id, $clusters))
                                        $cluster->checked = true;
                                    else
                                        $cluster->checked = false;
                                }
                            }
                        }
                    }
                }
            }
        }

        return $this->successData($dataList);
    }


    /**
     * save specify locations
     * @return locations
     */
    public function saveSpecifyLocations()
    {
        $projectId = request("project_id");
        $dataList = request("data");

        $project = Project::find($projectId);

        if($this->verifyLocationsForSkipLogic($project,$dataList) && $project->project_type == 'survey')
            return $this->failed("Location specified against skip logic can not be removed!");

        ProjectDetail::where("project_id",$projectId)->delete();

        $savedGovernorates = [];
        $savedDistricts = [];
        $savedSites = [];
        $savedClusters = [];

        foreach($dataList as $data){
                $governorateId = $data['id'];
            if(isset($data['children'])){
                foreach($data['children'] as $district)
                {
                    $districtId = $district['id'];
                    if(isset($district['children'])){
                        foreach($district['children'] as $site)
                        {
                            $siteId = $site['id'];
                            if(isset($site['children'])){
                                foreach($site['children'] as $cluster)
                                {
                                    $clusterId = $cluster['id'];

                                    if($cluster['checked'] == true)
                                    {
                                        $clusterObj = \App\Models\ClusterReference::find($cluster['id']);
                                        $siteObj = \App\Models\SiteReference::find($clusterObj->site_id);

                                        $projectDetail = new ProjectDetail();
                                        $projectDetail->project_id = $projectId;
                                        $projectDetail->governorate_id = $siteObj->governorate_id;
                                        $projectDetail->district_id = $siteObj->district_id;
                                        $projectDetail->site_id = $clusterObj->site_id;
                                        $projectDetail->cluster_id = $cluster['id'];
                                        $projectDetail->save();

                                        $savedGovernorates[$siteObj->governorate_id] = $siteObj->governorate_id;
                                        $savedDistricts[$siteObj->district_id] = $siteObj->district_id;
                                        $savedSites[$clusterObj->site_id] = $clusterObj->site_id;
                                        $savedClusters[$projectDetail->cluster_id] = $projectDetail->cluster_id;
                                    }
                                    unset($cluster);
                                }
                            }
                            unset($site);
                        }
                    }
                    unset($district);
                }
            }
            unset($data);
        }

        if($project->project_type == 'survey')
            $this->addLocationQuestionOptions($project, $savedGovernorates, $savedDistricts, $savedSites, $savedClusters);

        return $this->successData($dataList);
    }

    /**
     * verify locations specified against a skip logic
     * @return true/false
     */
    private function verifyLocationsForSkipLogic($project,$dataList)
    {
        $form = Form::where("project_id", $project->id)->first();
        $governorates = ProjectDetail::where("project_id", $project->id)->pluck("governorate_id", "governorate_id")->toArray();
        $districts = ProjectDetail::where("project_id", $project->id)->pluck("district_id", "district_id")->toArray();
        $sites = ProjectDetail::where("project_id", $project->id)->pluck("site_id", "site_id")->toArray();
        $clusters = ProjectDetail::where("project_id", $project->id)->pluck("cluster_id", "cluster_id")->toArray();

        $newGovernorates = [];
        $newDistricts = [];
        $newSites = [];
        $newClusters = [];

        foreach($dataList as $data){
                $governorateId = $data['id'];
            if(isset($data['children'])){
                foreach($data['children'] as $district)
                {
                    $districtId = $district['id'];
                    if(isset($district['children'])){
                        foreach($district['children'] as $site)
                        {
                            $siteId = $site['id'];
                            if(isset($site['children'])){
                                foreach($site['children'] as $cluster)
                                {
                                    $clusterId = $cluster['id'];

                                    if($cluster['checked'] == true)
                                    {
                                        $clusterObj = \App\Models\ClusterReference::find($cluster['id']);
                                        $siteObj = \App\Models\SiteReference::find($clusterObj->site_id);

                                        $newGovernorates[$siteObj->governorate_id] = $siteObj->governorate_id;
                                        $newDistricts[$siteObj->district_id] = $siteObj->district_id;
                                        $newSites[$clusterObj->site_id] = $clusterObj->site_id;
                                        $newClusters[$clusterObj->id] = $clusterObj->id;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        $governorateResults = array_diff($governorates,$newGovernorates);
        $districtResults = array_diff($districts,$newDistricts);
        $siteResults = array_diff($sites,$newSites);
        $clusterResults = array_diff($clusters,$newClusters);

        $optionNames = [];
        if(!empty($governorateResults)){
            $governorateList = \App\Models\Governorate::whereIn("id", $governorateResults)->pluck("name","name")->toArray();
            $optionNames = array_merge($optionNames,$governorateList);
        }

        if(!empty($districtResults)){
            $districtList = \App\Models\District::whereIn("id", $districtResults)->pluck("name","name")->toArray();
            $optionNames = array_merge($optionNames,$districtList);
        }

        if(!empty($siteResults)){
            $siteList = \App\Models\SiteReference::whereIn("id", $siteResults)->pluck("name","name")->toArray();
            $optionNames = array_merge($optionNames,$siteList);
        }

        if(!empty($clusterResults)){
            $clusterList = \App\Models\ClusterReference::whereIn("id", $clusterResults)->pluck("name","name")->toArray();
            $optionNames = array_merge($optionNames,$clusterList);
        }

        $questionIds = Question::where("form_id", $form->id)->whereIn("name_en", ['Name of Governorate?','Name of District?','Name of the Site?','Name of Cluster?'])->pluck("id","id")->toArray();

        $optionIds = [];
        if(!empty($questionIds)){
            $optionIds = \App\Models\QuestionOption::whereIn("question_id", $questionIds)->whereIn("name_en", $optionNames)->pluck("config_id","config_id")->toArray();
        }

        $existFlag = 0;
        if(!empty($optionIds))
            $existFlag = SkipLogicQuestionDetail::whereIn("question_id", $questionIds)->whereIn("option_value_id", $optionIds)->count();

        return ($existFlag>0?true:false);
    }

    /**
     * add locations as options
     * @return Void
     */
    public function addLocationQuestionOptions($project, $savedGovernorates, $savedDistricts, $savedSites, $savedClusters)
    {
        $form = Form::where("project_id", $project->id)->first();

        $governorates = \App\Models\Governorate::whereIn("id", $savedGovernorates)->get();
        $districts = \App\Models\District::whereIn("id", $savedDistricts)->get();
        $sites = \App\Models\SiteReference::whereIn("id", $savedSites)->get();
        $clusters = \App\Models\ClusterReference::whereIn("id", $savedClusters)->get();

        $locationList = ['Name of Governorate?'=>$governorates, 'Name of District?'=>$districts, 'Name of the Site?'=>$sites, 'Name of Cluster?'=>$clusters];
        $questions = Question::where("form_id", $form->id)->whereIn("name_en", ['Name of Governorate?','Name of District?','Name of the Site?','Name of Cluster?'])->get();

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

    /**
     * list locations
     * @return locations
     */
    public function listSpecifyLocations()
    {
        $projectId = request("project_id");
        $projectDetails = ProjectDetail::with(["governorate", "district", "site", "cluster"])->where("project_id",$projectId);
//        return $this->successData($projectDetails);
        return $this->successData(new Paging($projectDetails));
    }

    /**
     * tacking verification list
     * @return resource list
     */
    public function trackingVerificationList()
    {
        $projectId = request("project_id");
        $reportingType = request("reporting_type");
        $confirmLevel = request("confirmation_level");
        $confirmStatus = request("confirmation_status");
        $dateStart = request("date_start");
        $dateEnd = request("date_end");
        $rangeStart = request("range_start");
        $rangeEnd = request("range_end");
        $reportingDisease = request("reporting_disease");
        $reportingUser = request("reporting_user");

        $joinQuery = "";

        if(!empty($reportingType) && $reportingType != 'null')
            $joinQuery .= " AND disease_id IN (SELECT id from disease_bank where disease_type = '$reportingType') ";

        if(!empty($confirmLevel) && $confirmLevel != 'null')
            $joinQuery .= " AND confirmation_level='$confirmLevel' ";

        if(!empty($confirmStatus) && $confirmStatus != 'null')
            $joinQuery .= " AND confirmation_status='$confirmStatus' ";

        if(!empty($dateStart) && $dateStart != 'null'){
            if(!empty($dateEnd) && $dateEnd != 'null')
                $joinQuery .= " AND date_start >= DATE($dateStart) AND date_start <= DATE($dateEnd) ";
            else
                $joinQuery .= " AND date_start LIKE '$dateStart%' ";
        }

        if(!empty($rangeStart) && $rangeStart != 'null'){
            if(!empty($rangeEnd) && $rangeEnd != 'null')
                $joinQuery .= " AND hours BETWEEN $rangeStart AND $rangeEnd ";
            else
                $joinQuery .= " AND hours LIKE '$rangeStart%' ";
        }

        if(!empty($reportingDisease) && $reportingDisease != 'null')
            $joinQuery .= " AND disease_id='$reportingDisease' ";

        if(!empty($reportingUser) && $reportingUser != 'null')
            $joinQuery .= " AND user_id='$reportingUser' ";

        $query = \DB::select(DB::raw("SELECT surveillance_form_instance_id, disease_id, date_start, date_end, confirmation_level, confirmation_status, created_at, "
                . " (SELECT TIMESTAMPDIFF(HOUR,NOW(),date_end)) as hours, "
                 . " (SELECT name from users where id=user_id) as reporting_user,"
                 . " (SELECT appearance_name_en from disease_bank where id=disease_id) as disease_name, "
                 . " (SELECT disease_type from disease_bank where id=disease_id) as reporting_type "
                 . " From disease_verifications "
                . " Where id IN (SELECT MAX(id) FROM disease_verifications where confirmation_status IS NOT NULL AND project_id = '$projectId' $joinQuery group By surveillance_form_instance_id) "
                . " And confirmation_status IS NOT NULL AND project_id = '$projectId' $joinQuery Order By surveillance_form_instance_id DESC "));

        $listingData = collect($query);
        $collection = $this->paginateCollection($listingData, 10);
        $collection = json_decode(json_encode($collection), true);

        $itemList = array();
        if(isset($collection['data']) && count($collection['data'])>0){
            foreach($collection['data'] as $key => $item){
                array_push($itemList, $item);
            }
        }

        $dataList = array('items'=>$itemList, 'current_page'=>$collection['current_page'], 'from'=>$collection['from'], 'last_page'=>$collection['last_page'], 'next_page_url'=>$collection['next_page_url'], 'path'=>$collection['path'], 'per_page'=>$collection['per_page'], 'prev_page_url'=>$collection['prev_page_url'], 'to'=>$collection['to'], 'total'=>$collection['total']);

        return $this->successData($dataList);

    }

    /*
    * Used generic function for raw query pagination
     * */
    function paginateCollection($collection, $perPage, $pageName = 'page', $fragment = null)
    {
        $currentPage = \Illuminate\Pagination\LengthAwarePaginator::resolveCurrentPage($pageName);
        $currentPageItems = $collection->slice(($currentPage - 1) * $perPage, $perPage);
        parse_str(request()->getQueryString(), $query);
        unset($query[$pageName]);
        $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
            $currentPageItems,
            $collection->count(),
            $perPage,
            $currentPage,
            [
                'pageName' => $pageName,
                'path' => \Illuminate\Pagination\LengthAwarePaginator::resolveCurrentPath(),
                'query' => $query,
                'fragment' => $fragment
            ]
        );

        return $paginator;
    }

    /*
    * get project verifiers list
    * */
    public function projectVerifiers()
    {
        $search = request("query");
        $projectId = request("project_id");

        $verifierUsers = ProjectPermission::where("project_id", $projectId)->whereIn("title_id", [13,14,15,16])->pluck("user_id", "user_id")->toArray();

        $query = User::whereIn("id", $verifierUsers)
                ->where(function ($q) use ($search) {
                        $q->where('name', 'LIKE', "%" . $search . "%");
                })
                ->orderBy("name");

        return $this->successData(new Paging($query));
    }

    /*
    * get project verifiers list
    * */
    public function projectReportedDiseases()
    {
        $search = request("query");
        $projectId = request("project_id");

        $reportedDiseases = \App\Models\DiseaseVerification::where("project_id", $projectId)->pluck("disease_id", "disease_id")->toArray();

        $query = \App\Models\DiseaseBank::whereIn("id", $reportedDiseases)
                ->where(function ($q) use ($search) {
                        $q->where('appearance_name_en', 'LIKE', "%" . $search . "%");
                })
                ->orderBy("appearance_name_en");

        return $this->successData(new Paging($query));
    }

    /*
    * verify if values exists
    * */
    public function checkValue($array, $name, $default = null) {
        if (isset($array[$name])) {
            return $array[$name];
        }
        return $default;
    }

    /**
     * Export the specified resource from storage.
     *
     * @param int $instanceId
     *
     * @return \Illuminate\Http\Response
     */
    public function exportTrackingLog()
    {
        $instanceId = request("instance_id");
        $trackingData[] = [0=>"Form Id", 1=>"Reporting Disease", 2=>"Reporting User", 3=>"Reporting Type", 4=>"Date of Reporting", 5=>"", 6=>"", 7=>""];

        $query = \DB::select(DB::raw("SELECT date_start, date_end, confirmation_level, confirmation_status, updated_at, created_at, "
                . " (SELECT TIMESTAMPDIFF(HOUR,NOW(),date_end)) as hours, "
                 . " (SELECT name from users where id=confirmed_by) as verifier_user,"
                 . " (SELECT name from users where id=user_id) as reporting_user,"
                 . " (SELECT appearance_name_en from disease_bank where id=disease_id) as disease_name, "
                 . " (SELECT disease_type from disease_bank where id=disease_id) as reporting_type "
                 . " From disease_verifications "
                . "  where confirmation_status IS NOT NULL AND surveillance_form_instance_id = '$instanceId' "));

        $i=1;
        $trackingData[] = [0=>$instanceId, 1=>@$query[0]->disease_name, 2=>@$query[0]->reporting_user, 3=>@$query[0]->reporting_type, 4=>@$query[0]->created_at, 5=>"", 6=>"", 7=>""];
        $trackingData[] = [0=>"", 1=>"", 2=>"", 3=>"", 4=>"", 5=>"", 6=>"", 7=>""];

        $trackingData[] = array(0=>"Index",1=>"Confirmation Level",2=>"Confirmation Status",3=>"Date Start",4=>"Date End", 5=>"Remaning Hours",6=>"Verifier",7=>"Verified At");
        $levelsList = ['DL'=>'Verifier/ District','LL'=>'Laboratory Level','CL'=>'Clinic Level','HL'=>'Higher Verifier'];
        foreach($query as $result){
            array_push($trackingData, array(0=>$i++,1=>@$levelsList[$result->confirmation_level],2=>$result->confirmation_status,3=>$result->date_start,4=>$result->date_end,5=>$result->hours,6=>$result->verifier_user,7=>(in_array($result->confirmation_status, ['completed','discard'])?$result->updated_at:"-")));
        }

        // exporting the excel sheet with the resepctive data
        \Excel::create(
           "ExportTrackingForm{$instanceId}", function ($excel) use ($trackingData) {

                // creating the sheet and filling it with questions data
                $excel->sheet(
                    'TrackingLog', function ($sheet) use ($trackingData) {
                        $sheet->rows($trackingData);
                    }
                );

            }
        )->store('xls', "/tmp");

        //download excel file
        $file = "/tmp/ExportTrackingForm{$instanceId}.xls";
        return response()->download($file, "ExportTrackingForm{$instanceId}.xls", ['Content-Type: application/vnd.ms-excel']);

    }

    private function getUsers($list){
        $items = [];
        foreach($list as $item){
            $items[$item] = $item;
        }
        $users = User::whereIn("id",$items)->get();
        return $users;
    }

    /*
    * Get project collectors
    **/
    public function getProjectCollectors($getProjectId=0,$userIdsOnly=0)
    {
        $projectId = ($getProjectId>0)?$getProjectId:request("project_id");
        $search = request("query","");

        $userIds = DB::select(DB::raw("SELECT GROUP_CONCAT(DISTINCT user_id SEPARATOR ',') as collectors "
                . " FROM `user_roles` where user_id IN (SELECT user_id FROM project_members where project_id = {$projectId}) "));
        $userIds = explode(",",@$userIds[0]->collectors);

        if($userIdsOnly == 1)
            return $userIds;

        $query = User::whereIn("id",$userIds);
        if($search != "")
            $query->where("name", 'like', "%" . $search . "%");

        return $this->successData($query->get());
    }

    /*
    * Get surveillance collection week #s
    **/
    public function getSurveillanceCollectionWeeks($getProjectId=0,$getList=0)
    {
        $projectId = ($getProjectId>0)?$getProjectId:request("project_id");
        $searchQuery = request("query","");
        //$instances = \App\Models\SurveillanceFormInstance::where("project_id",$projectId)->get();

        $tempList = [];
        $datesList = [];
        $listItems = [];
        for($i=1;$i<=52; $i++){
            $weekNo = $i;//date("W",  strtotime($instance->created_at));

            if($searchQuery != "" && $searchQuery == $weekNo){
                $tempList[$weekNo] =  ['id'=>$weekNo, 'name'=>"Week# ".$weekNo];
                break;
            }else if($searchQuery == ""){
                $tempList[$weekNo] =  ['id'=>$weekNo, 'name'=>"Week# ".$weekNo];
                $listItems[$weekNo] = $weekNo;
            }
        }

        foreach($tempList as $item => $data)
            array_push($datesList, $data);

        if($getList == 1)
            return $listItems;

        return $this->successData($datesList);
    }

    /**
    * Display a listing of the resource.
    *
    * @return \Illuminate\Http\Response
    */
    public function searchLocations() {
         // checking for the search query
        $projectId = (int)request("project_id");
        $locationType = request('location_type');
        $searchQuery = request('query');

        switch ($locationType) {
            case 'governorate':
                $data = \App\Models\Governorate::where(function ($q) use ($searchQuery, $projectId) {
                    $q->where('name', 'LIKE', "%" . $searchQuery . "%");
                    if($projectId>0){
                        $governorates = \App\Models\SurveillanceFormInstance::where("project_id", $projectId)->pluck("governorate_id","governorate_id")->toArray();
                        $q->whereIn("id", $governorates);
                    }
                })->take(10)->get();
                break;
            case 'district':
                $data = \App\Models\District::where(function ($q) use ($searchQuery, $projectId) {
                    $q->where('name', 'LIKE', "%" . $searchQuery . "%");
                    if($projectId>0){
                        $districts = \App\Models\SurveillanceFormInstance::where("project_id", $projectId)->pluck("district_id","district_id")->toArray();
                        $q->whereIn("id", $districts);
                    }
                })->take(10)->get();
                break;
            case 'site':
                $data = \App\Models\SiteReference::where(function ($q) use ($searchQuery, $projectId) {
                    $q->where('name', 'LIKE', "%" . $searchQuery . "%");
                    if($projectId>0){
                        $sites = \App\Models\SurveillanceFormInstance::where("project_id", $projectId)->pluck("site_id","site_id")->toArray();
                        $q->whereIn("id", $sites);
                    }
                })->take(10)->get();
                break;
            default:
                $data = ClusterReference::where(function ($q) use ($searchQuery, $projectId) {
                    $q->where('name', 'LIKE', "%" . $searchQuery . "%");
                    if($projectId>0){
                        $clusters = \App\Models\SurveillanceFormInstance::where("project_id", $projectId)->pluck("cluster_id","cluster_id")->toArray();
                        $q->whereIn("id", $clusters);
                    }
                })->take(10)->get();
        }

        return $this->successData($data);
    }

    /*
    * Store surveillance graph filters
    **/
    public function getSurveillanceGraphs()
    {
        $projectId = request("project_id",0);
        $diseaseId = request("disease_id",0);
        $weekNumbers = request("weeks");
        $startDate = request("date_from");
        $endDate = request("date_to");
        $age = request("age");
        $gender = request("gender");
        $collectors = request("collectors");
        $locationMetric = request("location_metric");
        $locations = request("locations");
        $allCollectors = request("all_collectors",0);
        $allLocations = request("all_locations",0);
        $allWeeks = request("all_weeks",0);

        if($projectId == 0 || $diseaseId == 0){
            return $this->failed("Invalid disease/project Id.");
        }

        $formInstances = \App\Models\SurveillanceFormInstance::where("project_id",$projectId)
                                                    ->where("disease_id", $diseaseId)
                                                    ->where("instance_type","collection")
                                                    ->where(function ($q) use ($collectors, $allCollectors, $projectId) {
                                                        if(isset($collectors) && !empty($collectors))
                                                            $q->whereIn("user_id", $collectors);
                                                        else if($allCollectors == 1){
                                                            $projectAllCollectors = $this->getProjectCollectors($projectId, 1);
                                                            if(is_array($projectAllCollectors))
                                                                $q->whereIn("user_id", $projectAllCollectors);
                                                            else
                                                                $q->where("user_id", (int)@$projectAllCollectors);
                                                        }
                                                        else
                                                            $q->where("id","!=",0);
                                                    })->where(function ($q) use ($locations, $locationMetric) {
                                                        if(isset($locationMetric) && !empty($locationMetric) && isset($locations) && !empty($locations))
                                                            $q->whereIn("{$locationMetric}_id", $locations);
                                                        else
                                                           $q->where("id","!=",0);
                                                    })->pluck("id","id")->toArray();

        if((isset($age) && $age != "") || (isset($gender) && $gender != ""))
        {
                $selectInstances = [];
                $form = \App\Models\Form::where("project_id",$projectId)->first();
                $formQuestion1 = \App\Models\Question::with("options")->where("form_id",$form->id)->where("name_en","LIKE","Gender?")->where("response_type_id",1)->first();
                $formQuestion2 = \App\Models\Question::where("form_id",$form->id)->where("name_en","LIKE","Age?")->where("response_type_id",4)->first();

                $maleOptionId = 0;
                $femaleOptionId = 0;
                if($formQuestion1){
                    foreach($formQuestion1->options as $option){
                        if($option->name_en == 'Female')
                            $femaleOptionId = $option->id;
                        else
                            $maleOptionId = $option->id;
                    }
                }

                $ageInstances = [];
                $genderInstances = [];
                foreach($formInstances as $instanceId){

                    if(isset($age) && $age != ""){
                        $personAge = (int)@\App\Models\SurveillanceQuestionAnswer::where("surveillance_form_instance_id",$instanceId)->where("question_id",$formQuestion2->id)->pluck("value")->first();

                        if($age == 'all')
                            $ageInstances[$instanceId] = $instanceId;
                        else if($age == '<5' && ($personAge > 0 && $personAge <= 5))
                            $ageInstances[$instanceId] = $instanceId;
                        else if($age == '>5'  && $personAge > 5)
                            $ageInstances[$instanceId] = $instanceId;
                    }

                    if(isset($gender) && $gender != ""){
                        if($gender == 'male' && $formQuestion1){//->where("question_id",$formQuestion1->id)
                            $malCount = (int)@\App\Models\SurveillanceQuestionAnswer::where("surveillance_form_instance_id",$instanceId)->where("value",$maleOptionId)->count();
                            if($malCount>0)
                                $genderInstances[$instanceId] = $instanceId;
                        }else if($gender == 'female' && $formQuestion1){
                            $femaleCount = (int)@\App\Models\SurveillanceQuestionAnswer::where("surveillance_form_instance_id",$instanceId)->where("value",$femaleOptionId)->count();
                            if($femaleCount>0)
                                $genderInstances[$instanceId] = $instanceId;
                        }else
                            $genderInstances[$instanceId] = $instanceId;
                    }
                }

                if(empty($ageInstances))
                    $selectInstances = $genderInstances;
                else if(empty($genderInstances))
                    $selectInstances = $ageInstances;
                else if (!empty($ageInstances) && !empty($genderInstances))
                    $selectInstances = array_intersect($genderInstances, $ageInstances);
        }else
            $selectInstances = $formInstances;

        $dataItems = [];
        if(!empty($selectInstances)){
            if(isset($startDate) && isset($endDate) && !empty($startDate) && !empty($endDate)){
                $startDate = ($startDate." 00:00:00");
                $endDate = ($endDate." 23:59:59");

                $query = DB::select(DB::raw("SELECT COUNT(1) as value, "
                    . " DATE(created_at) as name "
                    . " FROM surveillance_form_instances WHERE id IN (".implode(",", $selectInstances).") AND (created_at BETWEEN '{$startDate}' AND '{$endDate}') GROUP By name"));

                foreach($query as $data)
                    $dataItems[] = ['value'=>$data->value, 'name'=>$data->name];

            }
            else if(isset($weekNumbers) && !empty($weekNumbers) && count($weekNumbers) > 0)
            {
                foreach($weekNumbers as $number){
                        $dates = $this->startEndDateOfWeek((int)@($number-1),date("Y"));
                        $dateStart = @$dates[0]." 00:00:00";
                        $dateEnd = @$dates[1]." 23:59:59";
                        $countValue = (int)@\App\Models\SurveillanceFormInstance::whereIn("id",$selectInstances)->whereBetween('created_at', [$dateStart, $dateEnd])->count();
                        $dataItems[] = ['value'=>$countValue, 'name'=>"Week# ".$number];
                }
            }
            else{
                $weekNumbers = $this->getSurveillanceCollectionWeeks($projectId,1);
                foreach($weekNumbers as $number){
                    $dates = $this->startEndDateOfWeek((int)@($number-1),date("Y"));
                    $dateStart = @$dates[0]." 00:00:00";
                    $dateEnd = @$dates[1]." 23:59:59";
                    $countValue = \App\Models\SurveillanceFormInstance::whereIn("id",$selectInstances)->whereBetween('created_at', [$dateStart, $dateEnd])->count();
                    $dataItems[] = ['value'=>$countValue, 'name'=>"Week# ".$number];
                }
            }
        }

        $disease = \App\Models\DiseaseBank::find($diseaseId);
        $data = ["status"=>"Success", "response"=>1, "message"=>"Success", 'data'=>['name'=>$disease->appearance_name_en,'series'=>$dataItems]];

        return $data;
    }

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
