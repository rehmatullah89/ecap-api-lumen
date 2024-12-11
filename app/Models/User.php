<?php

namespace App\Models;

use App\Jobs\SendSMS;
use Carbon\Carbon;
use Idea\Models\Profile;
use Idea\Models\User as IdeaUser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

class User extends IdeaUser
{
    /*
     * this function is set user relation with roles
     * a user can have many roles
     */
    public function projects()
    {
        return $this->belongsToMany(
            Project::class,
            'project_members',
            'user_id',
            'project_id'
        );
    }
    
    /*
     * this function is set user relation with roles
     * a user can have many roles
     */
    public function role()
    {
        return $this->hasOne(\Idea\Models\UserRole::class,'user_id','id');
    }
    
    /*
     * this function is set user relation with roles
     * a user can have many roles
     */
    public function projectUserTitle()
    {
        return $this->belongsTo(
            ProjectUserTitles::class,
                'id',
            'user_id'
        );
    }
    
    /*
     * this function is set user relation with roles
     * a user can have many roles
     */
    public function collectorProjects()
    {
        return $this->belongsToMany(
            Project::class,
            'project_location_details',
            'user_id',
            'project_id'             
        )->groupBy('project_id');
    }
    
    /*
     * this function is set user relation with roles
     * a user can have many roles
     */
    public function collectorPermissions()
    {
        return $this->belongsToMany(
            Project::class,
            'project_permissions',
            'user_id',
            'project_id'             
        )->where("title_id", 12)->groupBy('project_id');
    }
    
    /*
     * this function is set user relation with roles
     * a user can have many roles
     */
    public function groups()
    {
        return $this->belongsToMany(
            Group::class,
            'group_members',
            'user_id',
            'group_id'
        );
    }

    /*
     * this function is set user relation with roles
     * a user can have many roles
     */
    public function teams()
    {
        return $this->belongsToMany(
            Team::class,
            'project_members',
            'user_id',
            'team_id'
        );
    }

    /*
     * this function is set user relation with roles
     * a user can have many roles
     */
    public function permissions()
    {
        return $this->hasMany(
            UserPermissions::class,
            'user_id',
            'id'
        );
    }

    /*
     * this function is set user relation with roles
     * a user can have many roles
     */
    public function guestSites()
    {
        return $this->belongsToMany(
            Site::class,
            'guest_sites',
            'user_id',
            'site_id'
        );
    }

    public function location()
    {
        return $this->hasOne(SurveillanceLocation::class, "user_id", "id");
    }
    
    public function setUpNewUser()
    {
        //fire new user event
        $this->notifyUser();
        $this->notifyOwner();

        //assign external role
        $this->assignExternalRole();
    }

    /*
     * this function setup the new user ,set user role ,fire an event
     * */

    /**
     * Function to send verification code
     * to user on registration
     */
    public function notifyUser()
    {
        if (config('auth.verify_sms')) {
            $this->sendActivateSMS();
        } elseif (config('auth.verify_emails') && $this->email) {
            $this->sendActivateEmail();
        } else {
            $this->activate();
            $this->sendWelcomeEmail();
        }
    }

    private function sendActivateSMS()
    {
        $this->sms_confirm_code = str_random(5);
        $this->sms_confirm_expiry = Carbon::now()->addMinutes(60);
        $this->save();

        $userProfile = Profile::where('user_id', $this->id)->first();
        $phone = $this->normalizePhoneNumber($userProfile->phone);
        $data = [
            'template' => 'emails.verify',
            'to' => $phone,
            'from' => 'WHO',
            'text' => 'Welcome to WHO, your verification code is ' . $this->sms_confirm_code,
        ];

        dispatch(new SendSMS($data));

        return true;
    }

    /**
     * Description: Function to normalize phone number
     * so we can send an sms to it.
     *
     * @param $phoneNumber
     *
     * @return string
     */
    private function normalizePhoneNumber($phoneNumber)
    {
        $phoneArray = explode('-', $phoneNumber);

        return implode('', $phoneArray);
    }

    /**
     * this function is to return user's info
     *
     * @param $user
     * @param bool $withToken
     *
     * @return   array
     * @internal param $token
     */
    public function returnUser()
    {
        $userProfile = Profile::byUser($this->id)->first();

        //return user info
        $toReturn = [
            'user' => [
                "id" => $this->id,
                "username" => $this->username,
                //        "user_full_name"       => $this->name,
                "first_name" => !empty($userProfile->first_name) ? $userProfile->first_name : '',
                "last_name" => !empty($userProfile->last_name) ? $userProfile->last_name : '',
                "phone" => !empty($userProfile->phone) ? $userProfile->phone : '',
                "email" => $this->email,
                "user_profile_picture" => !empty($userProfile->image) ? $userProfile->image : '',
                "active" => $this->active,
                "roles" => $this->roles,
            ],
            'info' => $this->getMoreInfo(),
        ];

        /*$userRoles        = UserRole::byUserId($this->id)->get();
        $permissionValues = [];
        foreach ($userRoles AS $userRole) {
        $rolePermissions = RolePermission::whereRoleId($userRole->role_id)
        ->with("permission", "action")
        ->get();
        foreach ($rolePermissions AS $rolePermission) {
        if (!empty($rolePermission->permission->code) && !empty($rolePermission->action->name)) {
        $permissionValues [$rolePermission->permission->code][] = $rolePermission->action->name;
        }
        }
        }*/
        $completeList = [];
        $permissionsList = [];

        $systemPermissions = SystemPermission::get();
        $actions = Action::pluck('id', 'name')->toArray();
        $userPermissions = UserPermissions::where("user_id", $this->id)->get()->toArray();

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
                
                if($this->role_id == 9)
                    $tempList['allowed'] = 1;
                else
                    $tempList['allowed'] = (int)@$userPermissionList[$permission['id']][$id];

                $permissionsList['actions'][] = $tempList;
            }

            $completeList[] = $permissionsList;
            $permissionsList = [];
        }

        $toReturn['permissions'] = $completeList;
        
        if($this->id >0){
                $locations = \App\Models\SurveillanceLocation::where("user_id", $this->id)->get();
                $toReturn['governorate_id'] = ($locations)?@$locations[0]->governorate_id:null;
                $toReturn['district_id'] = ($locations)?@$locations[0]->district_id:null;
                $toReturn['site_id'] = ($locations)?@$locations[0]->site_id:null;
                $toReturn['cluster_id'] = ($locations)?@$locations[0]->cluster_id:null;
                $toReturn['lat'] = ($locations)?@$locations[0]->lat:null;
                $toReturn['lng'] = ($locations)?@$locations[0]->lng:null;
        }

        return $toReturn;
    }

    /**
     * Function to return more info in the login Function
     *
     * @return array
     */
    public function getMoreInfo()
    {
        if (!empty(Auth::user()->id)) {
            return ['for_later_use' => ''];
        }
    }

    public function hasRole($roleSlug)
    {
        $roles = $this->roles()->get();
        $hasRole = false;
        foreach ($roles as $role) {
            if ($role->slug == $roleSlug) {
                $hasRole = true;
            }
        }

        return $hasRole;
    }

    public function hasAnyOfRoles($rolesSlug)
    {
        $roles = $this->roles()->get();
        foreach ($roles as $role) {
            if (in_array($role->slug, $rolesSlug)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Function send to the welcome email for new users
     * @param $password
     * @param $role
     */
    public function sendWelcomeEmail($password, $role)
    {
        $roles = [9 => 'Super Admin', 10 => 'Admin', 11 => 'Normal'];
        if ($this->hasAnyOfRoles(['admin', 'super_admin', 'normal'])) {

            $user = $this;
            $view = 'emails.welcome-user';
            $data['password'] = $password;
            $data['role'] = $roles[$role]; //$this->getRoleOrigianlName($role);
            $data['app_url'] = env('APP_CMS', 'https://ecap-stg-cms.ideatolife.me/');
            $data['user'] = $user;
            $subject = 'Welcome to e-CAP - You\'ve been invited';

            Mail::send($view, $data, function ($message) use ($user, $subject) {
                $message->to($user->email)->subject($subject);
            });

        }
    }
    
    public function isCmsUser()
    {     
        return true;
    }

    /**
     * Function translate the role name
     * @param $role
     * @return $translatedNAme
     */
    public function getRoleOrigianlName($role)
    {
        switch ($role) {
            case 'admin':
                $originalName = 'Admin';
                break;
            case 'owner':
                $originalName = 'Owner';
                break;
            case 'project_administrator':
                $originalName = 'Project Administrator';
                break;
            case 'project_manager':
                $originalName = 'Project Manager';
                break;
            case 'supervisor':
                $originalName = 'Supervisor';
                break;
            case 'guest':
                $originalName = 'Guest';
                break;
            case 'external':
                $originalName = 'External';
                break;
            default:
                $originalName = 'Super Guest';
        }

        return $originalName;
    }

}
