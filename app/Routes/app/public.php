<?php


//Auth
$router->group(
	['namespace' => 'App\Auth', 'prefix' => 'auth'],
	function () use ($router) {
		$router->post('login', 'AuthController@login');
//		$router->post('adddefaultDataData', 'AuthController@adddefaultDataData');
	}
);


//Page
$router->group(
	['namespace' => 'App\Account', 'prefix' => 'account'],
	function () use ($router) {
		
		$router->get('home', 'HomeController@home');
		$router->get('page/{code}', 'PageController@pageByCode');
	}

);
