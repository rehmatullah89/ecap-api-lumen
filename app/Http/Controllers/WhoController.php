<?php

namespace App\Http\Controllers;

/**
 * Created by PhpStorm.
 * User: youssef.jradeh
 * Date: 5/24/18
 * Time: 1:19 AM
 */

use Illuminate\Support\Facades\Auth;
use Intervention\Image\Facades\Image;
use App\Models\GuestSite;

class WhoController extends \Idea\Base\BaseController
{
    
    public $filePath = "";
    
    public $isGuest = false;
    public $guestSites = false;

    /**
     * @param $user
     * @param $look
     *
     * @return array
     */
    protected function attachImage($model, $imageName, $autoSave = true) 
    {
        //validate the request
        if (!$this->request->hasFile($imageName)) {
            return false;
        }
        //remove existing image
        $this->deleteImage($model, $imageName);
        
        $file = $this->request->{$imageName};
        $this->validate($this->request, [$imageName => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048']);
        
        
        //get file path
        $filePath = $this->getFilePath();
        
        //Set public folder path
        $folderPath = public_path($filePath);
        
        //renaming the file
        $name = time() . '_' . rand(5000, 100000) . "." . $file->getClientOriginalExtension();
        
        //move the temporary file to the user folder with the name name
        if (!$file->move($folderPath, $name)) {
            return false;
        }
        //resize image quality
        Image::make($folderPath . $name)->resize(
            700,
            null,
            function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            }
        )->save($folderPath . $name, 75);
        
        chmod($folderPath . $name, 0777);
        $model->{$imageName} = $filePath . $name;
        
        if ($autoSave) {
            $model->save();
        }
        return $model;
    }
    
    protected function validatePermission()
    {
        if (empty($this->permissions[$this->action])) {
            return true;
        }

        //$userRoles = UserRole::where('user_id', \Auth::user()->id)->pluck('role_id');

        //Permission
        $code = $this->permissions[$this->action]['code'];
        //Action
        $action = $this->permissions[$this->action]['action'];

        //verify permission
        $permission = \App\Models\UserPermissions::where("user_id", \Auth::user()->id)
            ->whereHas(
                'permission',
                function ($query) use ($code) {
                    $query->where('code', $code);
                }
            )
            ->whereHas(
                'action',
                function ($query) use ($action) {
                    $query->where('name', $action);
                }
            )->first();

        if (!$permission)
        {
            $permission = \App\Models\ProjectPermission::where("user_id", \Auth::user()->id)
            ->whereHas(
                'permissionProject',
                function ($query) use ($code) {
                    $query->where('code', $code);
                }
            )
            ->whereHas(
                'action',
                function ($query) use ($action) {
                    $query->where('name', $action);
                }
            )->first();
        }
        
        //if permission exist return true
        if ($permission) {
            return true;
        }

        //return false;
        return true;
    }
    
    protected function deleteImage($model, $imageName) 
    {
        //if not image return back
        if (!$model->{$imageName}) {
            return false;
        }
        
        //remove image file from the hard disk
        $existingImage = public_path($model->{$imageName});
        
        if (is_file($existingImage)) {
            unlink($existingImage);
        }
        $model->{$imageName} = "";
        
        //updating the record
        $model->save();
        
        return true;
    }
    
    /**
     * @return string
     */
    public function getFilePath() 
    {
        $value = $this->filePath;
        
        //Replace user id if exist
        if ($user = Auth::user()) {
            $value = str_replace("{user_id}", $user->id, $value);
        }
        
        return $value;
    }
    
    protected static function validationRules() 
    {
        return [];
    }
    
    /**
     * Description: The following method is used to get all the input
     * validation error messages in errors array
     *
     * @author Shuja Ahmed - I2L
     *
     * @param $errorsArray
     *
     * @return array
     */
    protected function getAllErrorMessages($errorsArray) 
    {
        $response = [];
        foreach ($errorsArray as $errors) {
            foreach ($errors as $error) {
                array_push($response, $error);
            }
        }
        return $response;
    }

        /**
         * @param $query
         *
         * @return string
         */
    protected function addGuestFilter($query = "")
    {
        if (!$this->guestSites) {
            $guestSites = GuestSite::where('user_id', $this->user->id)->get();
            foreach ($guestSites AS $guestSite) {
                $this->guestSites[] = $guestSite->site_id;
            }
        }
        if ($this->isGuest) {
            $query .= " and fi.site_id IN (".implode(",", $this->guestSites).")";
        }
        
        return $query;
    }
}
