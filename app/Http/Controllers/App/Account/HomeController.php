<?php

/*
 * This file is part of the IdeaToLife package.
 *
 * (c) Youssef Jradeh <youssef.jradeh@ideatolife.me>
 *
 */

namespace App\Http\Controllers\App\Account;


use Idea\Base\BaseController;
use Idea\Models\Page;

class HomeController extends BaseController {
	
	protected static function validationRules() {
		return [];
	}
	
	/*
	 * Description: The following method return a static page by it's code
	 */
	public function home() {
		$aboutUs = Page::with('children')->where('code', "about_us")->get();
		$tandc   = Page::with('children')->where('code', "tandc")->get();
		
		return $this->successData([
			"about_us" => $aboutUs,
			"tandc"    => $tandc,
		]);
	}
	
}