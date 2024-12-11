<?php

/*
 * This file is part of the IdeaToLife package.
 *
 * (c) Youssef Jradeh <youssef.jradeh@ideatolife.me>
 *
 */

namespace App\Http\Controllers\User;

use Idea\Http\Controllers\User\ProfileController as IdeaProfileController;
use Idea\Models\User;
use Carbon\Carbon;
use Intervention\Image\Facades\Image;
use Illuminate\Support\Facades\Hash;

class ProfileController extends IdeaProfileController {
	
	/**
	 *
	 * @return array
	 */
	protected static function validationRules() {
		return [
			'get'           => [
				'user_id' => 'required|exists:users,id',
			],
			'updateProfile' => [
				'first_name' => 'required',
				'last_name'  => 'required',
				'phone'      => 'required',
			],
		];
	}
	
	/**
	 * Description: The following method gets the profile details
	 */
	public function get() {
		$user = User::where('id', request('user_id'))->with('profile')->first();
		return $this->successData($user);
	}
	
	/**
	 * Description: Update profile
	 * number again
	 *
	 * @return static
	 */
	public function updateProfile() {
		$profile              = \App\Models\Profile::byUser($this->user->id)
		                                           ->first();
		$profile->first_name  = request('first_name');
		$profile->last_name   = request('last_name');
		$profile->phone       = request('phone');
		$profile->image       = $this->saveImage(request('user_profile_picture'), $profile);
		$profile->description = request('description');
		$profile->save();
		return $this->successData($profile);
	}
	
	
	public function saveImage($base64 = "", $profile) {
		if (!$base64) {
			return "";
		}
		
		if (is_file($profile->image)) {
			unlink($profile->image);
			$profile->image = "";
		}
		
		$fileName   = rand() . "-" . time() . ".png";
		$folderPath = public_path('files/profile');
		
		if (!is_dir($folderPath)) {
			mkdir($folderPath, 0777, TRUE);
		}
		
		$path = $folderPath . '/' . $fileName;
		try {
			Image::make(file_get_contents($base64))->save($path);
			return 'files/profile/' . $fileName;
		} catch (\Exception $e) {
			return "";
		}
	}
        
    /**
     * Function to change password
     *
     * @int user_id
     * @string current_password
     * @string new_password
     * @return static
     */
    public function changePassword()
    {
        $user = User::find(request('user_id'));

        if (!$user) {
            return $this->failed("idea::general.invalid_user_password");
        }

        if (!Hash::check(request('current_password'), $user->password)) {
            return $this->failed("idea::general.invalid_user_password");
        }

        $current = Carbon::now();

        //update user request
        $user->password_changed_at = $current;
        $user->password = Hash::make($this->in('new_password'));
        $user->save();

        return $this->success();
    }
	
}