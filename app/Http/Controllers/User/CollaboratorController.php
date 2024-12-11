<?php

/*
 * This file is part of the IdeaToLife package.
 *
 * (c) Youssef Jradeh <youssef.jradeh@ideatolife.me>
 *
 */

namespace App\Http\Controllers\User;

use App\Http\Controllers\WhoController;
use App\Models\ProjectMember;
use App\Models\User;
use Idea\Models\Profile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class CollaboratorController extends WhoController
{
    
    protected $permissions
    = [
    "one"     => [
                "code"   => "collaborators",
                "action" => "read",
    ],
    "index"   => [
                "code"   => "collaborators",
                "action" => "read",
    ],
    "destroy" => [
                "code"   => "collaborators",
                "action" => "write",
    ],
    "store"   => [
                "code"   => "collaborators",
                "action" => "write",
    ],
    "update"  => [
                "code"   => "collaborators",
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
        'existing_user_id' => 'exists:users,id',
        'project_id' => 'required|exists:projects,id',
        'email'      => 'required|email|unique:users,username,'.request('existing_user_id').',id',
        'password'   => 'required',
        'name'       => 'required',
        'role'       => [
        'required',
        Rule::in(['project_manager', 'supervisor']),
        ],
        ],
        'add'   => [
        'existing_user_id' => 'exists:users,id',
        'project_id' => 'required|exists:projects,id',
        'role'       => [
        'required',
        Rule::in(['project_manager', 'supervisor']),
        ],
        ],
        'update'  => [
        'name' => 'required',
        'role' => [
        'required',
        Rule::in(['project_manager', 'supervisor']),
        ],
        ],
        'destroy' => [
        'ids.*' => 'required|exists:users,id',
        'project_id' => 'exists:projects,id'
        ],
        ];
    }
    
    public function one($id) 
    {
        
        $collaborator = User::with("roles", "projects")->whereHas(
            'roles',
            function ($q) {
                $q->whereIn('slug', ['project_manager', 'supervisor']);
            }
        )->find($id);
        
        if (!$collaborator) {
            return $this->failed("Invalid collaborator Id");
        }
        return $this->success('idea::general.general_data_fetch_message', $collaborator);
    }
	
	public function search(){
        
        $projectManagersQuery = User::with("roles")->whereHas(
            'roles',
            function ($q) {
                $q->whereIn('slug', ['project_manager']);
            }
        );
        
        if (request("search")) {
            $search = "%" . request("search") . "%";
            $projectManagersQuery->where("name", 'like', $search);
        }
        
        $supervisorQuery = User::with("roles")->whereHas(
            'roles',
            function ($q) {
                $q->whereIn('slug', ['supervisor']);
            }
        );
        if (request("search")) {
            $search = "%" . request("search") . "%";
            $supervisorQuery->where("name", 'like', $search);
        }
        
        return $this->successData(
            [
            "project_managers" => $projectManagersQuery->get(),
            "supervisor"       => $supervisorQuery->get(),
            ]
        );		
	}
    public function index() 
    {
        if (empty(request('project_id'))) {
            return $this->failed("Invalid Project");
        }
        
        $projectManagersQuery = User::with("roles")->whereHas(
            'roles',
            function ($q) {
                $q->whereIn('slug', ['project_manager']);
            }
        )->whereHas(
            'projects',
            function ($q) {
                $q->where('project_id', request('project_id'));
            }
        );
        
        if (request("search")) {
            $search = "%" . request("search") . "%";
            $projectManagersQuery->where("name", 'like', $search);
        }
        
        $supervisorQuery = User::with("roles")->whereHas(
            'roles',
            function ($q) {
                $q->whereIn('slug', ['supervisor']);
            }
        )->whereHas(
            'projects',
            function ($q) {
                $q->where('project_id', request('project_id'));
            }
        );
        if (request("search")) {
            $search = "%" . request("search") . "%";
            $supervisorQuery->where("name", 'like', $search);
        }
        
        return $this->successData(
            [
            "project_managers" => $projectManagersQuery->get(),
            "supervisor"       => $supervisorQuery->get(),
            ]
        );
    }
    

    public function add()
    {
        $collaborator = User::find(request('existing_user_id'));
        // checking if user has one of them role project_manager, supervisor
        if(!$collaborator->hasAnyOfRoles(['project_manager', 'supervisor'])) { return $this->failedWithErrors('idea::general.couldnt_created_new_collaborator_please_try_again_later');
        }

        // check if user already exists in this project
        if(ProjectMember::where('user_id', $collaborator->id)->where('project_id', request('project_id'))->exists()) {
            return $this->failedWithErrors('general.user_already_exists_in_this_project');
        }
            
        //Add to the members of the project
        $projectMember             = new ProjectMember();
        $projectMember->user_id    = $collaborator->id;
        $projectMember->project_id = request('project_id');
        $projectMember->save();

                    
        return $this->success(
            'success', User::with("profile", "roles")
            ->find($collaborator->id)
        );
    }

    public function store() 
    {
        // new user registration
        $collaborator           = new User();
        $collaborator->username = request('email');
        $collaborator->email    = request('email');
        $collaborator->password = Hash::make(request('password'));
        $collaborator->name     = request('name');
        $collaborator->active   = request('active', 1);
            
        $collaborator->getJWTCustomClaims();
        $roles = ['project_manager' => 4, 'supervisor' => 5];
        $collaborator->assignRolePermission([$roles[request('role')]]);
        if (!$collaborator->save()) {
            return $this->failedWithErrors('idea::general.couldnt_created_new_collaborator_please_try_again_later');
        }

        // check if user already exists in this project
        if(ProjectMember::where('user_id', $collaborator->id)->where('project_id', request('project_id'))->exists()) {
            return $this->failedWithErrors('general.user_already_exists_in_this_project');
        }
        
        //Add to the members of the project
        $projectMember             = new ProjectMember();
        $projectMember->user_id    = $collaborator->id;
        $projectMember->project_id = request('project_id');
        $projectMember->save();

        //creating empty profile
        $profile             = new Profile();
        $profile->user_id    = $collaborator->id;
        $profile->first_name = request('name');
            
        if($profile->save()) {
            // send welcome email
            $collaborator->sendWelcomeEmail(request('password'), request('role'));
        }
        
        return $this->success(
            'success', User::with("profile", "roles")
                                             ->find($collaborator->id)
        );
    }
    
    public function update($id) 
    {
        $collaborator = User::with("profile", "roles")->find($id);
        if (!$collaborator) {
            return $this->failed();
        }
        
        //validate the uniqueness of the email
        $this->validate(
            $this->request, [
            'email' => Rule::unique('users')
                ->ignore($collaborator->id, 'id'),
            ]
        );
        
        $collaborator->username = request('email');
        $collaborator->email    = request('email');
        $collaborator->name     = request('name');
        $collaborator->active   = request('active', $collaborator->active);
        
        if ($password = request('password')) {
            $collaborator->password = Hash::make($password);
            $collaborator->getJWTCustomClaims();
        }
        $roles = ['project_manager' => 4, 'supervisor' => 5];
        $collaborator->assignRolePermission([$roles[request('role')]]);
        if (!$collaborator->save()) {
            return $this->failed('idea::general.couldnt_update_collaborator_please_try_again_later');
        }
        
        //save profile
        $collaborator->profile->first_name = request('name');
        $collaborator->profile->save();
        
        return $this->success('success', $collaborator);
    }
    
    /**
     * Delete a Model
     */
    public function destroy() 
    {
        
        foreach (request('ids') AS $id) {
            
            $collaborator = User::find($id);
            if (!$collaborator || in_array($collaborator->id, [1, 2])) {
                return $this->failed("Cannot delete user");
            }
            
            if(request('project_id')) {
                if (!ProjectMember::where('user_id', $id)->where('project_id', request('project_id'))->delete()) {
                    return $this->failed();
                }
            }else{
                $collaborator->username = 'deleted_' . time() . '_' . $collaborator->username;
                $collaborator->save();
                if(!$collaborator->delete()) { return $this->failed();
                }
            }
            
        }
        return $this->success();
        
    }
    
}