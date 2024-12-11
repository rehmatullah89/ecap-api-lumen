<?php

/*
 * This file is part of the IdeaToLife package.
 *
 * (c) Youssef Jradeh <youssef.jradeh@ideatolife.me>
 *
 */

namespace App\Http\Controllers\App\Auth;

use App\Models\Profile;
use App\Models\ProjectMember;
use App\Models\User;
use Idea\Base\BaseController;
use Idea\Types\ExceptionType;
use Idea\Types\SocialType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\JWTAuth;


class AuthController extends BaseController
{

    use SocialType, ExceptionType;

    /**
     * @var \Tymon\JWTAuth\JWTAuth
     */
    protected $jwt;

    /**
     * @param \Tymon\JWTAuth\JWTAuth   $jwt
     * @param \Illuminate\Http\Request $request
     */
    public function __construct(JWTAuth $jwt, Request $request)
    {
        parent::__construct($request);

        //five request per second only
        $this->middleware('throttle:5,1');

        $this->jwt = $jwt;
    }

    protected static function validationRules()
    {
        return [
        'login' => [
        'username' => 'required',
        'password' => 'required',
        ],
        ];
    }

    /**
    * User Login
    */
    public function login()
    {
        $username = request('username');
        $password = request('password');

        try {
            // attempt to verify the credentials and create a token for the user
            if (!$token = $this->jwt->attempt(
                [
                'username' => $username,
                'password' => $password,
                ]
            )
            ) {
                return $this->failedWithErrors('idea::general.invalid_user_name_or_password');
            }

            $this->user = Auth::user();

            //check if the user has the collector access
            $collector = User::with("roles")->whereHas('collectorProjects')->find($this->user->id);
            $permissions = User::with("roles")->whereHas('collectorPermissions')->find($this->user->id);

            if (!$collector && !$permissions) {
                return $this->failedWithErrors('idea::general.could_not_authenticate_user');
            }

            //check if user deactivated
            if (!$this->user->active) {
                $toReturn = $this->user->returnUser();
                return $this->success('idea::general.your_account_is_still_deactivated', $toReturn);
                // return $this->failedWithErrors('idea::general.your_account_is_still_deactivated');
            }

            //set device and push token
            $this->user->linkDeviceIdAndPushToken();

            $toReturn          = $this->user->returnUser();
            $toReturn['token'] = $this->user->generateJWTToken();

            if($this->user->id >0){
                $locations = \App\Models\SurveillanceLocation::where("user_id", $this->user->id)->get();
                $toReturn['governorate_id'] = ($locations)?@$locations[0]->governorate_id:null;
                $toReturn['district_id'] = ($locations)?@$locations[0]->district_id:null;
                $toReturn['site_id'] = ($locations)?@$locations[0]->site_id:null;
                $toReturn['cluster_id'] = ($locations)?@$locations[0]->cluster_id:null;
            }

            return $this->success('idea::general.login_success', $toReturn);
        } catch (JWTException $e) {
            // something went wrong whilst attempting to encode the token
            return $this->failedWithErrors('idea::general.could_not_authenticate_user');
        }
    }
}