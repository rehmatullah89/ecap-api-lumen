<?php

/*
 * This file is part of the IdeaToLife package.
 *
 * (c) Youssef Jradeh <youssef.jradeh@ideatolife.me>
 *
 */

namespace App\Http\Controllers\User;

use App\Http\Controllers\WhoController;
use App\Models\GuestSite;
use App\Models\ProjectMember;
use App\Models\User;
use Idea\Models\Profile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class GuestController extends WhoController
{
    
    protected $permissions
        = [
            "one"     => [
                "code"   => "guests",
                "action" => "read",
            ],
            "index"   => [
                "code"   => "guests",
                "action" => "read",
            ],
            "destroy" => [
                "code"   => "guests",
                "action" => "write",
            ],
            "store"   => [
                "code"   => "guests",
                "action" => "write",
            ],
            "add"   => [
                "code"   => "guests",
                "action" => "write",
            ],
            "update"  => [
                "code"   => "guests",
                "action" => "write",
            ],
        ];
    
    /**
     * @return array
     */
    protected static function validationRules()
    {
        return [
            'store'   => [
                'project_id' => 'required|exists:projects,id',
                'email'      => 'required|email|unique:users,username',
                'password'   => 'required',
                'name'       => 'required',
                'sites.*'    => 'required|exists:sites,id',
            ],
            'add'   => [
                'existing_user_id' => 'exists:users,id',
                'project_id' => 'required|exists:projects,id',
                'sites.*'    => 'required|exists:sites,id',
            ],
            'update'  => [
                'project_id' => 'required|exists:projects,id',
                'name'       => 'required',
                'sites.*'    => 'required|exists:sites,id',
            ],
            'destroy' => [
                'ids.user_id.*' => 'required|exists:users,id',
                'project_id' => 'exists:projects,id'
            ],
        ];
    }
    
    public function one($id)
    {
        
        $guest = User::with("roles", "projects")->whereHas(
            'roles',
            function ($q) {
                $q->whereIn('slug', ['guest','super_guest']);
            }
        )->find($id);
        
        if (!$guest) {
            return $this->failed("Invalid collector Id");
        }
        
        return $this->success('idea::general.general_data_fetch_message', $guest);
    }

    public function search()
    {
        
        $query = User::with("roles", "guestSites")->whereHas(
            'roles',
            function ($q) {
                $q->whereIn('slug', ['guest','super_guest']);
            }
        );

        if (request("search")) {
            $search = "%" . request("search") . "%";
            $query->where("name", 'like', $search);
        }
        
        $guests = $query->get();
        return $this->successData(
            [
                "guests" => $guests,
            ]
        );
    }
    
    public function index()
    {
        if (empty(request('project_id'))) {
            return $this->failed("Invalid Project");
        }
        
        $guests = User::with("roles", "guestSites")->whereHas(
            'roles',
            function ($q) {
                $q->whereIn('slug', ['guest','super_guest']);
            }
        )->whereHas(
            'projects',
            function ($q) {
                $q->where('project_id', request('project_id'));
            }
        )->get();
        
        return $this->successData(
            [
                "guests" => $guests,
            ]
        );
    }

    /**
     * the following method is used to add a existing guest to a project
     *
     * @return void
     */
    public function add()
    {
        $guest = User::find(request('existing_user_id'));

        // checking if user has one of them role project_manager, supervisor
        if(!$guest->hasAnyOfRoles(['super_guest', 'guest'])) { return $this->failedWithErrors('idea::general.couldnt_created_new_collaborator_please_try_again_later');
        }

        // check if user already exists in this project
        if(ProjectMember::where('user_id', $guest->id)->where('project_id', request('project_id'))->exists()) {
            return $this->failedWithErrors('general.user_already_exists_in_this_project');
        }
        
        //Add to the members of the project
        $projectMember             = new ProjectMember();
        $projectMember->user_id    = $guest->id;
        $projectMember->project_id = request('project_id');
        $projectMember->save();
        
        foreach (request("sites") as $siteId) {
            $guestSite             = new GuestSite();
            $guestSite->user_id    = $guest->id;
            $guestSite->project_id = request('project_id');
            $guestSite->site_id    = $siteId;
            $guestSite->save();
        }

        return $this->success(
            'success',
            User::with("profile", "guestSites", "roles")
                ->find($guest->id)
        );
    }
    
    public function store()
    {
        // new user registration
        $guest           = new User();
        $guest->username = request('email');
        $guest->email    = request('email');
        $guest->password = Hash::make(request('password'));
        $guest->name     = request('name');
        $guest->active   = request('active', 1);
            
        $guest->getJWTCustomClaims();
        if (request('super_guest')) {
            $guest->assignRolePermission([8]);
        } else {
            $guest->assignRolePermission([6]);
        }
        if (!$guest->save()) {
            return $this->failedWithErrors('idea::general.couldnt_created_new_guest_please_try_again_later');
        }

        // check if user already exists in this project
        if(ProjectMember::where('user_id', $guest->id)->where('project_id', request('project_id'))->exists()) {
            return $this->failedWithErrors('general.user_already_exists_in_this_project');
        }
        //Add to the members of the project
        $projectMember             = new ProjectMember();
        $projectMember->user_id    = $guest->id;
        $projectMember->project_id = request('project_id');
        $projectMember->save();
        
        foreach (request("sites") as $siteId) {
            $guestSite             = new GuestSite();
            $guestSite->user_id    = $guest->id;
            $guestSite->project_id = request('project_id');
            $guestSite->site_id    = $siteId;
            $guestSite->save();
        }

            //creating empty profile
        $profile             = new Profile();
        $profile->user_id    = $guest->id;
        $profile->first_name = request('name');
            
        if($profile->save()) {
            // send welcome email
            if(request('super_guest')) { $guest->sendWelcomeEmail(request('password'), 'super_guest');
            } else { $guest->sendWelcomeEmail(request('password'), 'guest');
            }
        }
        
        return $this->success(
            'success',
            User::with("profile", "guestSites", "roles")
                ->find($guest->id)
        );
    }
    
    public function update($id)
    {
        $guest = User::with("profile", "guestSites")->whereHas(
            'projects',
            function ($q) {
                $q->where('project_id', request('project_id'));
            }
        )->find($id);
        
        if (!$guest) {
            return $this->failed();
        }
        
        //validate the uniqueness of the email
        $this->validate(
            $this->request,
            [
                'email' => Rule::unique('users')
                ->ignore($guest->id, 'id'),
            ]
        );
        
        $guest->username = request('email');
        $guest->email    = request('email');
        $guest->name     = request('name');
        $guest->active   = request('active', $guest->active);
        if ($password = request('password')) {
            $guest->password = Hash::make($password);
            $guest->getJWTCustomClaims();
        }

        // ECAP-150 the role update incase of user shifted from guest to super guest or viceversa
        if (request('super_guest')) {
            $guest->assignRolePermission([8]);
        } else {
            $guest->assignRolePermission([6]);
        }
        
        if (!$guest->save()) {
            return $this->failed('idea::general.couldnt_update_guest_please_try_again_later');
        }
        
        GuestSite::where("user_id", $guest->id)
                 ->where("project_id", request("project_id"))
                 ->delete();
        foreach (request("sites") as $siteId) {
            $guestSite             = new GuestSite();
            $guestSite->user_id    = $guest->id;
            $guestSite->project_id = request('project_id');
            $guestSite->site_id    = $siteId;
            $guestSite->save();
        }
        
        //save profile
        $guest->profile->first_name = request('name');
        $guest->profile->save();
        
        return $this->success(
            'success',
            User::with("profile", "guestSites", "roles")
                ->find($guest->id)
        );
    }
    
    /**
     * Delete a Model
     */
    public function destroy()
    {
        
        if (empty(request('ids'))) {
            return $this->failed('Please select at least one guest');
        }
        
        foreach (request('ids') AS $id) {
            
            if (!is_array($id) || !is_array($id['site_ids'])) {
                continue;
            }
            
            $guest = User::with(
                ["guestSites" => function ($q) {
                    $q->where('guest_sites.project_id', request('project_id'));
                }]
            )->find($id['user_id']);
            
            if (!$guest || in_array(
                $guest->id,
                [
                        1,
                        2,
                    ]
            ) || empty($guest->guestSites)
            ) {
                return $this->failed("Cannot delete user");
            }
            
            if(request('project_id')) {
                if (!ProjectMember::where('user_id', $id['user_id'])->where('project_id', request('project_id'))->delete()) {
                    return $this->failed();
                }
            }else{
                $guest->username = 'deleted_'.time().'_'.$guest->username;
                $guest->save(); 
    
                if(!$guest->delete()) { return $this->failed();
                }
            }
                
            GuestSite::whereIn("site_id", $id['site_ids'])
                         ->where("user_id", $guest->id)
                         ->delete();
            
        }
        
        return $this->success();
        
    }
    
}