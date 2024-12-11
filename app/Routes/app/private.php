<?php


//User
$router->group(
	['namespace' => 'App\Account', 'prefix' => 'user'],
	function () use ($router) {
		$router->post('change-user-password', 'AccountController@changePassword');
                $router->get('locations', 'AccountController@getUserLocations');
	}

);

//Projects
$router->group(
	['namespace' => 'App\Project', 'prefix' => 'project'],
	function () use ($router) {
		$router->get('all', 'ProjectController@all');
		$router->get('project-questions', 'ProjectController@projectQuestions');
		$router->get('sites', 'SiteController@index');
		$router->get('clusters', 'ClusterController@index');
                $router->get('project-locations', 'ProjectController@projectLocations');
                $router->get('verification-data-list', 'ProjectController@verificationDataList');
                $router->get('get-verification-data', 'ProjectController@getSurveillanceInstanceData');
                $router->get('get-collection-data', 'ProjectController@getCollectionInstanceData');
                $router->post('assign-verification-data', 'ProjectController@assignVerificationData');   
                $router->get('user-collections', 'ProjectController@userCollectionList');   
                $router->get('immediate-collections', 'ProjectController@immediateCollectionList');  
                $router->get('weekly-collections', 'ProjectController@weeklyCollectionList');  
                $router->get('monthly-collections', 'ProjectController@monthlyCollectionList');        
                $router->post('export-daily-collections', 'ProjectController@exportDailyCollections');
                $router->post('export-weekly-collections', 'ProjectController@exportWeeklyCollections');  
                $router->post('export-monthly-collections', 'ProjectController@exportMonthlyCollections');        
                $router->delete('remove-collection/{id}', 'ProjectController@removeCollection');
                $router->get('user-feedbacks', 'ProjectController@userFeedBackList');
                $router->post('save-report-counter', 'ProjectController@saveReportCounter');  
                $router->post('save-report-additional', 'ProjectController@saveReportAdditional');
                $router->get('user-projects', 'ProjectController@userProjcts');
	}
);

//Form
$router->group(
	['namespace' => 'App\Form', 'prefix' => 'form'],
	function () use ($router) {
		$router->get('get/{id}', 'FormController@get');
		$router->get('get-mobile-forms/{id}', 'FormController@getMobileData');
                $router->get('get-surveillance-forms', 'FormController@getSurveillanceData');
		$router->post('submit-answers', 'FormController@store');
                $router->post('submit-surveillance-answers', 'FormController@saveSurveillanceAnswers');
                $router->post('submit-verifier-answers', 'FormController@saveVerifierAnswers');
                $router->post('contact-us-notification', 'FormController@contactUsNotification');
	}
);