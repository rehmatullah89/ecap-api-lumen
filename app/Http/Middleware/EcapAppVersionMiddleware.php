<?php

/*
 * This file is part of the IdeaToLife package.
 * This is a middleware that ensure following things:
 * 1. First the incoming request has a device_type and version.
 * 2. Secondly the incoming version is match from the database or not
 *
 * (c) Muhammad Abid <muhammad.abd@ideatolife.me>
 *
 */
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Idea\Types\ExceptionType;
use Illuminate\Http\Response;
use Illuminate\Http\Exceptions\HttpResponseException;
use App\Models\AppVersion;


class EcapAppVersionMiddleware
{
    use ExceptionType;
    public $request;


    /**
     * Create a new BaseMiddleware instance.
     * @param Idea\Models\AppVersion
     * @return void
     */
    public function __construct(AppVersion $appVersionModel)
    {
        $this->appVersionModel = $appVersionModel;
    }

    /**
     * Description: The following method is used to check the incoming app version from DB
     *
     * @author Muhammad Abid - I2L
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure $next
     *
     * @return mixed
     * @throws \Illuminate\Http\Exceptions\HttpResponseException
     */
    public function handle(Request $request, Closure $next)
    {
        $errors = [];
        // $response = new Response();
        $this->request = $request;
        $deviceType = $request->headers->get('device_type');
        $appVersion = $request->headers->get('app_version');

        if (!isset($deviceType) || !isset($appVersion)){
            if(empty($deviceType)){
                $this->raiseAuthorizationException('error', ['no_device_type_found']);
            }
            if(empty($appVersion)){
                $this->raiseAuthorizationException('error', ['no_app_version_found']);
            }
            
        }

        $version = $this->appVersionModel->where('device_type', $deviceType)
                                            ->where('version', $appVersion)
                                            ->where('active', 1)
                                            ->first();

        $response = $next($request);
        if (!$version) {

            $this->raiseInvalidRequestException('app_version_obsolete', ['You have an old version, please update to the latest from appstore']);

        }
        
        return $response;
    }

    /**
     * get the latest version for the respective device type
     *
     * @param  $deviceType
     * @return array
     */
    private function getLatestVersion($deviceType)
    {
        $version = $this->appVersionModel->where('device_type', $deviceType)->where('active', '1')->first();
        $latestVersion = [];
        if ($version){
            $latestVersion['update_type'] = $version->update_type;
            $latestVersion['version']     = $version->version;
        }
        return $latestVersion;
    }


    

}
