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

class PageController extends BaseController {
	
	protected static function validationRules() {
		return [];
	}
	
	/*
	 * Description: The following method return a static page by it's code
	 */
	public function pageByCode($code) {
		$child = Page::with('children')
		             ->where('code', $code)
		             ->get();
		if (!$child) {
			return $this->failed("invalid page");
		}
		return $this->successData($child);
	}
	
}