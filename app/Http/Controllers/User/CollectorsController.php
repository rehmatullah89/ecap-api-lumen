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
use Excel;
use Idea\Models\Profile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Idea\Helpers\Paging;
use Illuminate\Support\Facades\Mail;

class CollectorsController extends WhoController
{

    public $filePath = "csv";

    protected $permissions
    = [
    "one"     => [
                "code"   => "collectors",
                "action" => "read",
    ],
    "index"   => [
                "code"   => "collectors",
                "action" => "read",
    ],
    "destroy" => [
                "code"   => "collectors",
                "action" => "write",
    ],
    "store"   => [
                "code"   => "collectors",
                "action" => "write",
    ],
    "add"   => [
                "code"   => "collectors",
                "action" => "write",
    ],
    "update"  => [
                "code"   => "collectors",
                "action" => "write",
    ],
    "csv"     => [
                "code"   => "collectors",
                "action" => "write",
    ],
    ];

    /**
     * @return array
     */
    protected static function validationRules()
    {
        return [
        /*'store'   => [
        'project_id' => 'required|exists:projects,id',
        'email'      => 'required|email|unique:users,username',
        'password'   => 'required',
        'name'       => 'required',
        'team_id'    => 'required|exists:teams,id',
        ],
        'add' =>[
        'existing_user_id' => 'exists:users,id',
        'project_id' => 'required|exists:projects,id',
        'team_id'    => 'required|exists:teams,id',
        ],
        'update'  => [
        'project_id' => 'required|exists:projects,id',
        'name'       => 'required',
        'team_id'    => 'required|exists:teams,id',
        ],
        'destroy' => [
        'ids.*' => 'required|exists:users,id',
        'project_id' => 'exists:projects,id'
        ],*/
        ];
    }

    public function one($id)
    {

        $collector = User::with("roles", "projects")->find($id);

        if (!$collector) {
            return $this->failed("Invalid collector Id");
        }
        return $this->success('idea::general.general_data_fetch_message', $collector);
    }

    public function search()
    {

        $query = User::with("roles", "projects");

        if (request("search")) {
            $search = "%" . request("search") . "%";
            $query->where("name", 'like', $search);
        }

        $collectors = $query->get();
        return $this->successData(
            [
            "collectors" => $collectors,
            ]
        );
    }

    public function index()
    {
        if (empty(request('project_id'))) {
            return $this->failed("Invalid Project");
        }

        $members = ProjectMember::where("project_id", request('project_id'))->pluck("user_id","user_id")->toArray();
        $collectors = \Idea\Models\UserRole::whereIn("user_id",$members)->where("role_id",12)->pluck("user_id","user_id")->toArray();

        // only loading  project instance of the current project given
        $query = User::with(["roles", "projects"])->whereHas(
            'projects',
            function ($q) use ($collectors) {
                $q->where('project_id', request('project_id'))->whereIn("user_id",$collectors);
            }
        );

        if (request("search")) {
            $search = "%" . request("search") . "%";
            $query->where("name", 'like', $search);
        }

        //$collectors = $query->paginate(10);

        return $this->successData(new Paging($query));
        /*return $this->successData(
            [
            "collectors" => $collectors,
            ]
        );*/
    }

    /**
     * the following method is used tof rcases where an existing collector is being added to respective project
     *
     * @return void
     */
    public function add()
    {
        return $this->failed("Invalid Request");
        /*$collector = User::find(request('existing_user_id'));
        // checking if user has one of them role project_manager, supervisor
        if(!$collector->hasAnyOfRoles(['external'])) { return $this->failed('idea::general.couldnt_created_new_collaborator_please_try_again_later');
        }

        // check if user already exists in this project
        if(ProjectMember::where('user_id', $collector->id)->where('project_id', request('project_id'))->exists()) {
            return $this->failedWithErrors('general.user_already_exists_in_this_project');
        }

        //Add to the members of the project
        $projectMember             = new ProjectMember();
        $projectMember->user_id    = $collector->id;
        $projectMember->project_id = request('project_id');
        $projectMember->team_id    = request('team_id');
        $projectMember->save();

        return $this->success(
            'success', User::with(["roles", "projects" => function($q){
				$q->where('project_id', request('project_id'));
			}, "teams" => function($q){
				$q->where('teams.project_id', request('project_id'));
			}])
                                             ->find($collector->id)
        );*/
    }

	/**
	 * the following method is used to store/register a new user
	 *
	 * @return void
	 */
    public function store()
    {
        return $this->failed("Invalid Request");
        // new user registration
        /*$collector           = new User();
        $collector->username = request('email');
        $collector->email    = request('email');
        $collector->password = Hash::make(request('password'));
        $collector->name     = request('name');
        $collector->active   = request('active', 1);

        $collector->getJWTCustomClaims();
        $collector->assignRolePermission([7]);
        if (!$collector->save()) {
            return $this->failedWithErrors('idea::general.couldnt_created_new_collector_please_try_again_later');
        }

        // check if user already exists in this project
        if(ProjectMember::where('user_id', $collector->id)->where('project_id', request('project_id'))->exists()) {
            return $this->failedWithErrors('general.user_already_exists_in_this_project');
        }

        //Add to the members of the project
        $projectMember             = new ProjectMember();
        $projectMember->user_id    = $collector->id;
        $projectMember->project_id = request('project_id');
        $projectMember->team_id    = request('team_id');
        $projectMember->save();

        //creating empty profile
        $profile             = new Profile();
        $profile->user_id    = $collector->id;
        $profile->first_name = request('name');
        $profile->save();

        return $this->success(
            'success', User::with(["roles", "projects" => function($q){
				$q->where('project_id', request('project_id'));
			}, "teams" => function($q){
				$q->where('teams.project_id', request('project_id'));
			}])->find($collector->id)
        );*/
    }

    public function update($id)
    {
        return $this->failed("Invalid Request");
        /*$collector = User::with("profile")->whereHas(
            'projects',
            function ($q) {
                $q->where('project_id', request('project_id'));
            }
        )->find($id);

        if (!$collector) {
            return $this->failed();
        }

        //validate the uniqueness of the email
        $this->validate(
            $this->request, [
            'email' => Rule::unique('users')
                ->ignore($collector->id, 'id'),
            ]
        );

        $collector->username = request('email');
        $collector->email    = request('email');
        $collector->name     = request('name');
        $collector->active   = request('active', $collector->active);

        if ($password = request('password')) {
            $collector->password = Hash::make($password);
            $collector->getJWTCustomClaims();
        }

        if (!$collector->save()) {
            return $this->failed('idea::general.couldnt_update_collector_please_try_again_later');
        }


        ProjectMember::where("user_id", $collector->id)
                     ->where("project_id", request('project_id'))
                     ->delete();

        //Add to the members of the project
        $projectMember             = new ProjectMember();
        $projectMember->user_id    = $collector->id;
        $projectMember->project_id = request('project_id');
        $projectMember->team_id    = request('team_id');
        $projectMember->save();

        //save profile
        $collector->profile->first_name = request('name');
        $collector->profile->save();

        return $this->success(
            'success', User::with(["roles", "projects" => function($q){
				$q->where('project_id', request('project_id'));
			}, "teams" => function($q){
				$q->where('teams.project_id', request('project_id'));
			}])->find($collector->id)
        );*/
    }

    /**
     * Function send to the welcome email for new users
     * @param $password
     * @param $role
     */
    public function sendEmailToCollectors()
    {
        $collectors = request('users');
        $projectId = request('project_id');

        if($projectId > 0)
            $project = \App\Models\Project::find($projectId);

        foreach ($collectors as $collector){

            $user = User::find($collector);
            $view = 'emails.collectors';
            $data['email'] = $user->username;
            $data['url'] = ($project->project_type == 'surveillance'?"https://collector-uat-phase2.ideatolife.me":"https://websurvey-uat-phase2.ideatolife.me");
            $data['user'] = $user->name;
            $subject = 'e-CAP - Collection';

            Mail::send($view, $data, function ($message) use ($user, $subject) {
                $message->to($user->email)->subject($subject);
            });

        }
        return $this->successData();
    }

    /**
     * Delete a Model
     */
    public function destroy()
    {

        foreach (request('ids') AS $id) {

            $collector = User::find($id);
            if (!$collector || in_array($collector->id, [1, 2])) {
                return $this->failed("Cannot delete user");
            }

            if(request('project_id')) {
                if (!ProjectMember::where('user_id', $id)->where('project_id', request('project_id'))->delete()) {
                    return $this->failed();
                }
            }else{
                $collector->username = 'deleted_' . time() . '_' . $collector->username;
                $collector->save();

                if(!$collector->delete()) { return $this->failed();
                }
            }

        }
        return $this->success();

    }

    /**
     * Delete a Model
     */
    public function csv()
    {

        if (!$this->request->hasFile("import_file")) {
            return $this->failed('Invalid CSV');
        }

        Excel::load(
            $this->request->file('import_file')
                ->getRealPath(), function ($reader) {
                    foreach ($reader->toArray() as $key => $row) {
                        //validate the uniqueness of the email
                        try {
                            if (User::where("email", $row["email"])->exists()) {
                                continue;
                            }

                            $collector           = new User();
                            $collector->username = $row["email"];//
                            $collector->email    = $row["email"];//email
                            $collector->password = Hash::make($row["password"]);//password
                            $collector->name     = $row["name"];//name
                            $collector->active   = $row["active"];//active

                            $collector->getJWTCustomClaims();
                            $collector->assignRolePermission([7]);
                            if (!$collector->save()) {
                                return $this->failed('idea::general.couldnt_created_new_collector_please_try_again_later');
                            }

                            //Add to the members of the project
                            $projectMember             = new ProjectMember();
                            $projectMember->user_id    = $collector->id;
                            $projectMember->project_id = $row["project_id"];//project_id
                            $projectMember->team_id    = $row["team_id"];//team_id
                            $projectMember->save();

                            //creating empty profile
                            $profile             = new Profile();
                            $profile->user_id    = $collector->id;
                            $profile->first_name = $row["name"];
                            $profile->save();
                        } catch (\Exception $exception) {
                            throw $exception;
                            continue;
                        }
                    }
                }
        );

        return $this->success();

    }

}