<?php

/*
 * This file is part of the IdeaToLife package.
 *
 * (c) Youssef Jradeh <youssef.jradeh@ideatolife.me>
 *
 */

namespace App\Http\Controllers\User;

use Excel;
use App\Http\Controllers\WhoController;
use App\Models\GroupMember;
use App\Models\ProjectMember;
use App\Models\User;
use App\Models\UserPermissions;
use App\Models\SurveillanceLocation;
use Idea\Helpers\Paging;
use Idea\Models\Profile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\DefaultProjectPermissions;

class UserController extends WhoController
{

    protected $permissions
    = [
        "one" => [
            "code" => "user_configuration",
            "action" => "read",
        ],
        "index" => [
            "code" => "user_configuration",
            "action" => "read",
        ],
        "destroy" => [
            "code" => "user_configuration",
            "action" => "write",
        ],
        "store" => [
            "code" => "user_configuration",
            "action" => "write",
        ],
        "update" => [
            "code" => "user_configuration",
            "action" => "write",
        ],
    ];

    /**
     * @return array
     */
    protected static function validationRules()
    {
        return [
            'store' => [
                'email' => 'required|email|unique:users,username',
                'password' => 'required',
                'first_name' => 'required',
                /*'last_name' => 'required',*/
                'role_id' => 'required',
            ],
            'update' => [
                'first_name' => 'required',
                /*'last_name' => 'required',*/
                'email' => 'required',
                'role_id' => 'required',
            ],
            'saveUserContactLocation'=>[
              'user_id'=>'required',  
              'governorate_id'=>'required',  
              'district_id'=>'required',  
              'site_id'=>'required',  
              'cluster_id'=>'required',    
              'population'=>'required',  
              'lat'=>'required',    
              'lng'=>'required',                    
            ],            
        ];
    }

    /**
     * @return one user information
     */
    public function one($id)
    {
        $user = User::with(["profile", "location.contacts", "location.governorate", "location.district", "location.site", "location.cluster"])->find($id);
        $user['projects'] = ProjectMember::where("user_id", $id)->pluck("project_id")->toArray();
        $user['groups'] = \App\Models\GroupMember::where("user_id", $id)->pluck("group_id")->toArray();
        $user['groups_detail'] = \App\Models\Group::whereIn("id", $user['groups'])->get()->toArray();
        $user['all_projects'] = \App\Models\Project::get();
        $user['permissions'] = $this->getUserPermissions($id, $user->role_id);
        $user['permission_details'] = $this->getPermissionDetails($id, $user->role_id);
        $user['project_permissions'] = $this->getProjectDefaultPermissions($id);
        $user['project_permissions_detail'] = $this->getProjectPermissionDetails($id);

        return $this->success('idea::general.general_data_fetch_message', $user);
    }

    /**
     * @param $id
     * @return user permissions applied
     */
    private function getPermissionDetails($id, $role)
    {
        $count = 1;
        $oldPermission = 0;
        $compelteList = [];
        $userPermissionsList = [];
        $userPermissions = UserPermissions::where("user_id", $id)->orderBy("permission_id")->get()->toArray();

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
     * @return user permissions applied
     */
    private function getUserPermissions($id, $role)
    {
        $completeList = [];
        $permissionsList = [];

        $systemPermissions = \App\Models\SystemPermission::get();
        $actions = \App\Models\Action::pluck('id', 'name')->toArray();
        $userPermissions = UserPermissions::where("user_id", $id)->get()->toArray();

        $userPermissionList = [];
        foreach ($userPermissions as $id => $value) {
            $userPermissionList[$value['permission_id']][$value['action_id']] = 1;
        }

        foreach ($systemPermissions as $permission) {
            $permissionsList['permission_id'] = $permission['id'];
            $permissionsList['name'] = $permission['name'];
            $permissionsList['code'] = $permission['code'];

            foreach ($actions as $action => $id) {
                $tempList['action_id'] = $id;
                $tempList['name'] = $action;
                $tempList['allowed'] = (int) @$userPermissionList[$permission['id']][$id];

                $permissionsList['actions'][] = $tempList;
                $tempList = [];
            }

            $completeList[] = $permissionsList;
            $permissionsList = [];
        }

        return $completeList;
    }

    public function index()
    {
        
        $searchQuery = !empty(@request('query')) ? request('query') : "";
        $searchStatus = request('status') == "" ? [0, 1] : [request('status')];
        $searchRole = request('role_id');
        $userType = request('user_type');
        $reportingAgency = trim(request('reporting_agency'));
        
        $query = User::with(["profile", "projects", "groups"])
            ->where(function ($q) use ($searchQuery, $searchRole) {
                if (!empty($searchQuery) && !empty($searchRole)) {
                    $q->where("username", "like", "%" . $searchQuery . "%")
                        ->orWhere("name", "like", "%" . $searchQuery . "%")
                        ->Where('role_id', $searchRole);
                } else if (!empty($searchQuery) && empty($searchRole)) {
                    $q->where("username", "like", "%" . $searchQuery . "%")
                        ->orWhere("name", "like", "%" . $searchQuery . "%");
                } else if (empty($searchQuery) && !empty($searchRole)) {
                    $q->where('role_id', $searchRole);
                } else {
                    $q->where('id', '!=', 0);
                }

            })
            ->where(function ($q){
                if(\Auth::user()->role_id == 9)
                    $q->where('role_id', "!=", 0);
                else
                    $q->where('role_id', "!=", 9);
            })
            ->where(function ($q) use ($userType){
                if($userType == 'surveillance')
                    $q->where('user_type', $userType)->orWhere('user_type', 'both');
                else
                    $q->where('user_type', 'survey')->orWhere('user_type', 'both');
            })
            ->whereIn('active', $searchStatus)
            ->orderBy("id", "DESC");
            
        if(!empty($reportingAgency))
            $query->where("reporting_agency", $reportingAgency);

        return $this->successData(new Paging($query));
    }

    public function store()
    {
        $userType = request('user_type');
        
        $user = new User();
        $user->username = request('email');
        $user->email = request('email');
        $user->password = Hash::make(request('password'));
        $user->name = request('first_name') . " " . request('last_name');
        $user->active = request('active', 1);
        $user->role_id = request('role_id');
        $user->user_type = ($userType != ""?$userType:'both');
        $user->reporting_agency = request('reporting_agency');
        $user->getJWTCustomClaims();

        $user->assignRolePermission([request('role_id')]);

        if (!$user->save()) {
            return $this->failed('idea::general.couldnt_created_new_user_please_try_again_later');
        }

        //add projects
        $projects = request("projects");
        //$projects = json_decode(request("projects"));
        if (isset($projects) && !empty(@$projects)) {
            foreach ($projects as $projectId) {
                $porojectMembers = new ProjectMember();
                $porojectMembers->user_id = $user->id;
                $porojectMembers->project_id = $projectId;
                $porojectMembers->save();
            }
        }

        //add users
        $groups = request("projects");
        //$groups = json_decode(request("groups"));
        if (isset($groups) && !empty(@$groups)) {
            foreach ($groups as $groupId) {
                $groupMembers = new GroupMember();
                $groupMembers->user_id = $user->id;
                $groupMembers->group_id = $groupId;
                $groupMembers->save();
            }
        }

        //creating empty profile
        $profile = new Profile();
        $profile->user_id = $user->id;
        $profile->first_name = request('first_name');
        $profile->last_name = request('last_name');
        $profile->middle_name = request('middle_name');
        $profile->title = request('title');
        $profile->position = request('position');
        $profile->phone = request('phone');
        $profile->save();

        /*if($profile->save()){
        // send welcome email
        $user->sendWelcomeEmail(request('password'), request('role_id'));
        }*/

        //$this->saveDefaultUserPermissions($user->id);

        return $this->success('success', User::with(["profile", "projects", "groups"])->find($user->id));
    }

    public function update($id)
    {
        $user = User::with("profile")->find($id);
        if (!$user) {
            return $this->failed();
        }

        //validate the uniqueness of the email
        $emailCount = User::where("id", "!=", $id)->where("email", request('email'))->count();
        if ($emailCount > 0) {
            return $this->failed("This email has already been taken.");
        }

        $user->username = request('email');
        $user->email = request('email');
        $user->name = request('first_name') . " " . request('last_name');
        $user->reporting_agency = request('reporting_agency');
        $user->active = request('active', 1);
        $user->role_id = request('role_id');
        if ($password = request('password')) {
            $user->password = Hash::make($password);
            $user->getJWTCustomClaims();
        }

        $user->assignRolePermission([request('role_id')]);
        if (!$user->save()) {
            return $this->failed('idea::general.couldnt_update_user_please_try_again_later');
        }
        
        if(in_array(request('reporting_agency'), array("mobile_teams","none")))
            SurveillanceLocation::where("user_id", $user->id)->delete();
        
        //save profile
        $user->profile = !isset($user->profile) ? new Profile() : $user->profile;
        $user->profile->user_id = $user->id;
        $user->profile->first_name = request('first_name');
        $user->profile->last_name = request('last_name');
        $user->profile->middle_name = request('middle_name');
        $user->profile->title = request('title');
        $user->profile->position = request('position');
        $user->profile->phone = request('phone');
        $user->profile->save();

        //add projects
        $notTodeleteProjects = [];
        $projects = request("projects");
        //$projects = json_decode(request("projects"));
        if (isset($projects) && !empty(@$projects)) {
            foreach ($projects as $projectId) {
                $projectMem = ProjectMember::where("project_id", $projectId)->where("user_id", $id)->get();
                $porojectMembers = count($projectMem) > 0 ? @($projectMem[0]) : new ProjectMember();
                $porojectMembers->user_id = $id;
                $porojectMembers->project_id = (count($projectMem) > 0) ? $projectMem[0]->project_id : $projectId;
                $porojectMembers->save();

                $notTodeleteProjects[] = $porojectMembers->id;
            }

            ProjectMember::where("user_id", $id)->whereNotIn("id", $notTodeleteProjects)->delete();
        } else {
            ProjectMember::where("user_id", $id)->delete();
        }

        //add groups
        $notTodeleteGroups = [];
        $groups = request("groups");
        //$groups = json_decode(request("groups"));
        if (isset($groups) && !empty(@$groups)) {
            foreach ($groups as $groupId) {
                $GroupMem = GroupMember::where("group_id", $groupId)->where("user_id", $id)->get();
                $groupMembers = count($GroupMem) > 0 ? @($GroupMem[0]) : new GroupMember();
                $groupMembers->user_id = $id;
                $groupMembers->group_id = (count($GroupMem) > 0) ? $GroupMem[0]->group_id : $groupId;
                $groupMembers->save();

                $notTodeleteGroups[] = $groupMembers->id;
            }

            GroupMember::where("user_id", $id)->whereNotIn("id", $notTodeleteGroups)->delete();
        } else {
            GroupMember::where("user_id", $id)->delete();
        }

        return $this->success('success', User::with(["profile", "projects", "groups"])->find($id));
    }

    /**
     * @action save default user permissions
     * @return user permissions
     */
    private function saveDefaultUserPermissions($id)
    {
        $defaultPermissions = DB::select(DB::raw("SELECT permission_id, action_id FROM default_user_permissions"));

        foreach ($defaultPermissions as $key => $object) {
            $userPermission = new UserPermissions();
            $userPermission->user_id = $id;
            $userPermission->action_id = $object->action_id;
            $userPermission->permission_id = $object->permission_id;
            $userPermission->save();
        }
    }

    /**
     * @action save users permissions
     * @return user permissions
     */
    public function savePermissions()
    {
        $userId = request("user_id");
        $systemPermissions = request("system_permissions");
        $projectPermissions = request("project_permissions");

        UserPermissions::where("user_id", $userId)->delete();
        foreach ($systemPermissions as $id => $value) {
            $permissionId = $value['id'];
            $actions = $value['actions'];

            foreach ($actions as $id => $actionId) {
                $userPermission = new UserPermissions();
                $userPermission->user_id = $userId;
                $userPermission->action_id = $actionId;
                $userPermission->permission_id = $permissionId;
                $userPermission->save();
            }
        }
        
        DefaultProjectPermissions::where("user_id", $userId)->delete();
        foreach ($projectPermissions as $id => $value) {
            $permissionId = $value['id'];
            $actions = $value['actions'];

            foreach ($actions as $id => $actionId) {
                $projectPermission = new DefaultProjectPermissions();
                $projectPermission->user_id = $userId;
                $projectPermission->action_id = $actionId;
                $projectPermission->permission_id = $permissionId;
                $projectPermission->save();
            }
        }

        
        $permissionsList['system_permission_details'] = $systemPermissions;
        $permissionsList['project_permission_details'] = $projectPermissions;
        $permissionsList['system_permissions'] = $this->getUserPermissions($userId, 0);
        $permissionsList['project_permissions'] = $this->getProjectDefaultPermissions($userId);
        
        return $this->success('success', $permissionsList);
    }

    /**
     * @param $id
     * @return project permissions applied
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
        
        if(count($userPermissions) > 0)
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
     * @param $id
     * @return project permissions applied
     */
    public function setProjectDefaultPermissions()
    {
        $userId = request("user_id");
        $titleId = request("title_id");
        $permissions = request("permissions");

        DefaultProjectPermissions::where("user_id", $userId)->delete();
        foreach ($permissions as $id => $value) {
            $permissionId = $value['id'];
            $actions = $value['actions'];

            foreach ($actions as $id => $actionId) {
                $userPermission = new DefaultProjectPermissions();
                $userPermission->user_id = $userId;
                $userPermission->action_id = $actionId;
                $userPermission->title_id = $titleId;
                $userPermission->permission_id = $permissionId;
                $userPermission->save();
            }
        }

        $permissionsList['title_id'] = $titleId;
        $permissionsList['permissions'] = $this->getProjectDefaultPermissions($userId);
        $permissionsList['permission_details'] = $permissions;

        return $this->success('success', $permissionsList);
    }
    
    /**
     * @param $id
     * @return user permissions applied
     */
    private function getProjectPermissionDetails($id)
    {
        $count = 1;
        $oldPermission = 0;
        $compelteList = [];
        $userPermissionsList = [];
        $userPermissions = DefaultProjectPermissions::where("user_id", $id)->orderBy("permission_id")->get()->toArray();

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
     * Description: The following method Search for User's by a keyword
     */
    public function searchUsers()
    {
        $query = User::active()->whereHas('collectorProjects')->latest()->select("username", "name", "email", "id");

        if ($key = request('key')) {
            $query->byKey($key);
        }

        return $this->successData(new Paging($query, 100));
    }
    
    public function saveUserContactLocation()
    {
        $userId = (int)request('user_id');
        
        if($userId == 0)
            return $this->failed("Invalid user id.");
        
        SurveillanceLocation::where("user_id", $userId)->delete();
        
        $location = new SurveillanceLocation();                
        $location->user_id  = request('user_id');
        $location->governorate_id  = request('governorate_id');
        $location->district_id = request('district_id');
        $location->site_id = request('site_id');
        $location->cluster_id = request('cluster_id');
        $location->population = request('population');
        $location->lat = request('lat');
        $location->lng = request('lng');
        $location->save();
        
        \App\Models\SurveillanceContact::where("user_id", $userId)->delete();
        
        $contacts = @request("contacts");
        if(isset($contacts) && !empty($contacts)){
            foreach($contacts as $contactData){
                $contact = new \App\Models\SurveillanceContact();                
                $contact->user_id  = $userId;
                $contact->contact_person  = $contactData['contact_person'];
                $contact->contact_number = $contactData['contact_number'];
                $contact->save();
            }
        }
        return $this->successData(SurveillanceLocation::with("contacts")->find($location->id));
    }
    
    /**
     * the following method is used to download the user template file
     *
     * @return void
     */
    public function downloadUserTemplate(){
        return response()->download(public_path()."/User_Template.xlsx", "User_Template.xlsx", ['Content-Type: application/vnd.ms-excel']);
    }
    
    /**
     * the following method is used to export user list
     *
     * @return void
     */
    public function exportUsers()
    {
        $userType = request("user_type"); 
        $users = User::where(function ($q) use ($userType){
                if($userType == 'surveillance')
                    $q->where('user_type', $userType)->orWhere('user_type', 'both');
                else
                    $q->where('user_type', 'survey')->orWhere('user_type', 'both');
            })->get();
            
        $userData[] = [0=>'Email',1=>'Password',2=>'Name',3=>'User Type',4=>'Status'];
        $roles = \Idea\Models\Role::pluck("slug","id")->toArray();
        $userRoles = \Idea\Models\UserRole::pluck("role_id","user_id")->toArray();
        
        foreach($users as $key => $user){
            array_push($userData, array(0=>$user->email, 1=>"", 2=>$user->name, 3=>($user->role_id>0?$roles[$user->role_id]:$roles[$userRoles[$user->id]]), 4=>($user->active == 1?'Active':'In-Active')));
        }
        
        //delete temporary table
        \Excel::create(
            "users",
            function ($excel) use ($userData) {
                // Set the title
                $excel->setTitle('Exporting users');
                
                // Chain the setters
                $excel->setCreator('IdeatoLife')->setCompany('IdeatoLife');
                    // creating the sheet and filling it with parameter data
                    $excel->sheet(
                        'Users', function ($sheet) use ($userData) {
                            $sheet->rows($userData);
                        }
                    );
                }
        )->store('xls', "/tmp");
        
        //PDF file is stored under project/public/download/info.pdf
        $file = "/tmp/users.xls";
        
        $headers = [
            'Content-Type: application/vnd.ms-excel',
        ];
        //results_export
        return response()->download($file, "Users-Export-".date("Ymd - H:i").".xls", $headers);        
    }
    
     public function importUsers() 
    {        
        if (!$this->request->hasFile("import_file")) {
            return $this->failed('Invalid Excel File');
        }
                
        Excel::load(
            $this->request->file('import_file')
                ->getRealPath(), function ($reader) {
                    $tab = 0;                    
                    $importUserType = request("user_type"); 
                    $dataList = $reader->toArray(); 
                    $roles = \Idea\Models\Role::pluck("id","slug")->toArray();
                    
                    if(isset($dataList[1][0]['user_type']) && $dataList[1][0]['user_type'] == 'super_admin')
                    {
                            foreach ($dataList as $key => $row) {                        
                            try {
                                if($tab == 0) //import Parameter Data
                                {
                                        if(count($row)>0)
                                        {
                                            foreach($row as $key =>$value)
                                            {
                                                $email = trim($value['email']);
                                                $password = trim($value['password']);
                                                $name = trim($value['name']);
                                                $userType = trim($value['user_type']);
                                                $status = trim($value['status']);     

                                                $roleId = 0;
                                                if($userType != "")
                                                    $roleId = @$roles[strtolower($userType)];

                                                if($roleId >0 && $name != "" && $email != "")
                                                {
                                                    $flag = 0;
                                                    $user = User::where("email", "LIKE", $email)->first();

                                                    if(!$user)
                                                        $flag = 1;

                                                    if(!$user && $password == "")
                                                        $password = "123456";

                                                    $user = ($user)?$user:new User();
                                                    $user->username = $email;
                                                    $user->email = $email;
                                                    $user->name = $name;

                                                    if($password != "")
                                                        $user->password = Hash::make($password);

                                                    if($user->user_type == "" && $importUserType != "")
                                                       $user->user_type =  $importUserType;

                                                    $user->active = (strtolower($status) == 'active'?1:0);
                                                    $user->role_id = $roleId;
                                                    $user->getJWTCustomClaims();
                                                    $user->assignRolePermission([$roleId]);
                                                    $user->save();

                                                    //creating empty profile
                                                    if($flag == 1)
                                                    {
                                                        $profile = new Profile();
                                                        $profile->user_id = $user->id;
                                                        $profile->first_name = $name;
                                                        $profile->save();
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
                        
                    }else{
                        if(count($dataList)>0)
                        {
                            foreach($dataList as $key =>$value)
                            {
                                $email = trim($value['email']);
                                $password = trim($value['password']);
                                $name = trim($value['name']);
                                $userType = trim($value['user_type']);
                                $status = trim($value['status']);     

                                $roleId = 0;
                                if($userType != "")
                                    $roleId = @$roles[strtolower($userType)];

                                if($roleId >0 && $name != "" && $email != "")
                                {
                                        $flag = 0;
                                        $user = User::where("email", "LIKE", $email)->first();

                                        if(!$user)
                                            $flag = 1;

                                        if(!$user && $password == "")
                                            $password = "123456";

                                        $user = ($user)?$user:new User();
                                        $user->username = $email;
                                        $user->email = $email;
                                        
                                        if($password != "")
                                            $user->password = Hash::make($password);
                                        
                                        $user->name = $name;
                                        $user->active = (strtolower($status) == 'active'?1:0);
                                        $user->role_id = $roleId;
                                        
                                        if($flag == 1)
                                            $user->getJWTCustomClaims();
                                        
                                        $user->assignRolePermission([$roleId]);
                                        $user->save();

                                        //creating empty profile
                                        if($flag == 1)
                                        {
                                            $profile = new Profile();
                                            $profile->user_id = $user->id;
                                            $profile->first_name = $name;
                                            $profile->save();
                                        }
                                }

                            }
                        }
                                
                    }
                }
        );
        
        return $this->success();        
    }
    
    /**
     * Log Out
     */
    public function signOut()
    {
        $user = \Idea\Models\User::find($this->user->id);
        $user->jwt_sign = "";
        $user->save();
        
        return $this->success();
    }
    
    /**
     * Delete a Model
     */
    public function destroy($id)
    {
        $user = User::find($id);

        //disallow the removing the id the main user
        if (!$user || in_array($user->id, [1, 2])) {
            return $this->failed("Cannot delete user");
        }

        $user->username = 'deleted_' . time() . '_' . $user->username;
        $user->save();

        GroupMember::where("user_id", $id)->delete();
        ProjectMember::where("user_id", $id)->delete();
        
        if ($user->delete()) {
            return $this->success();
        }

        return $this->failed();
    }

}
