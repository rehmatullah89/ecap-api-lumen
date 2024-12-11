<?php

namespace App\Models;

class Profile extends \Idea\Models\Profile {
	
	protected $appends = ['user_profile_picture'];
	
	protected $hidden = ['image'];
	
	
	/**
	 * @return null
	 */
	public function getUserProfilePictureAttribute() {
		return $this->image;
	}
}
