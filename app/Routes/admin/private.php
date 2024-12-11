<?php

//Admin
$router->group(
    ['namespace' => 'Projects', 'prefix' => 'admin'],
    function () use ($router) {
        //Project
        $router->post('projects/remove-member', 'ProjectController@destroyProjectMember');        
        $router->get('projects', 'ProjectController@index');
        $router->get('projects/list', 'ProjectController@listProjects');
        $router->post('projects/save-permissions', 'ProjectController@saveProjectPermissions');
        $router->get('projects/user-stats/{id}', 'ProjectController@projectUserStats');
        $router->get('projects/summary/{id}', 'ProjectController@projectSummary');
        $router->get('projects/location/{id}', 'ProjectController@projectLocation');
        $router->get('projects/permissions', 'ProjectController@projectPermissions');
        $router->post('projects/members', 'ProjectController@projectMembers');        
        $router->get('projects/{id}', 'ProjectController@one');
        $router->post('projects', 'ProjectController@store');
        $router->post('projects/{id}', 'ProjectController@update');
        $router->post('projects/duplicate/{id}', 'ProjectController@duplicate');
        $router->post('projects/status/{id}', 'ProjectController@updateStatus');
        $router->delete('projects/{id}', 'ProjectController@destroy');        
        $router->post('projects/duration/{id}', 'ProjectController@duration');
        $router->post('projects/goal/{id}', 'ProjectController@goal');
        $router->post('projects/description/{id}', 'ProjectController@description');
        $router->post('project-details', 'ProjectController@projectDetails');
        $router->post('project-disease-details', 'ProjectController@projectDiseaseDetails');
        $router->post('project-location-details', 'ProjectController@saveProjectLocationDetails');
        $router->get('project-location-details', 'ProjectController@getProjectLocationDetails');        
        $router->get('icd-codes', 'ProjectController@icdCodes');
        $router->get('list-locations', 'ProjectController@listSpecifyLocations');
        $router->get('get-locations', 'ProjectController@getSpecifyLocations');
        $router->post('save-locations', 'ProjectController@saveSpecifyLocations');
        $router->get('tracking-verifications', 'ProjectController@trackingVerificationList');
        $router->get('project-verifiers', 'ProjectController@projectVerifiers');
        $router->get('project-reported-diseases', 'ProjectController@projectReportedDiseases');
        $router->post('export-tracking-log', 'ProjectController@exportTrackingLog');
        $router->post('export-project-summary', 'ProjectController@exportProjectSummary');
        $router->post('export-project-locations', 'ProjectController@exportProjectLocations');
        $router->post('export-project-users', 'ProjectController@exportProjectUsers');
        $router->get('surveillance-graphs', 'ProjectController@getSurveillanceGraphs');
        $router->get('get-collection-weeks', 'ProjectController@getSurveillanceCollectionWeeks');
        $router->get('project-collectors', 'ProjectController@getProjectCollectors');
        $router->get('search-locations', 'ProjectController@searchLocations');

        //filters
        $router->get('filters', 'ResultFiltersController@index');
        $router->get('filters/{id}', 'ResultFiltersController@one');
        $router->post('filters', 'ResultFiltersController@store');
        $router->post('filters/{id}', 'ResultFiltersController@update');
        $router->delete('filters/{id}', 'ResultFiltersController@destroy');
        
        //governorates 
        $router->post('location-template', 'GovernorateController@downloadLocationTemplate');
        $router->post('locations-export', 'GovernorateController@exportLocations');
        $router->post('locations-import', 'GovernorateController@importLocations');        
        $router->get('locations-data', 'GovernorateController@locationsData');
        $router->get('governorates', 'GovernorateController@index');
        $router->get('governorate-search', 'GovernorateController@search');
        $router->get('governorates/{id}', 'GovernorateController@one');
        $router->post('governorates', 'GovernorateController@store');
        $router->post('governorates/{id}', 'GovernorateController@update');
        $router->delete('governorates/{id}', 'GovernorateController@destroy');
        
        //District
        $router->get('districts', 'DistrictController@index');
        $router->get('districts/search', 'DistrictController@search');        
        $router->post('districts/import', 'DistrictController@importData');
        $router->get('districts/{id}', 'DistrictController@one');
        $router->post('districts', 'DistrictController@store');
        $router->post('districts/{id}', 'DistrictController@update');
        $router->delete('districts/{id}', 'DistrictController@destroy');
        
        $router->get('multiple-districts', 'DistrictController@multipleDistricts');
        $router->get('multiple-sites', 'SiteReferenceController@multipleSites');
        $router->get('multiple-clusters', 'ClusterReferenceController@multipleClusters');
        $router->get('multiple-questions', 'DistrictController@multipleQuestions');
        
        //Site
        $router->get('sites', 'SiteController@index');
        $router->get('sites/{id}', 'SiteController@one');
        $router->post('sites', 'SiteController@store');
        $router->post('sites/{id}', 'SiteController@update');
        $router->post('sites/status/{id}', 'SiteController@updateStatus');
        $router->delete('sites/{id}', 'SiteController@destroy');
        
        //Site Reference
        $router->get('site-references', 'SiteReferenceController@index');
        $router->get('site-references/{id}', 'SiteReferenceController@one');
        $router->post('site-references', 'SiteReferenceController@store');
        $router->post('site-references/{id}', 'SiteReferenceController@update');
        $router->delete('site-references/{id}', 'SiteReferenceController@destroy');
        $router->post('migrate-sites', 'SiteReferenceController@migrateOldSitesAndClusters');
        $router->post('migrate-collectors', 'SiteReferenceController@migrateCollectorsData');
        $router->post('migrate-parameters', 'SiteReferenceController@migrateFormTypeParameter');
        $router->post('migrate-questions', 'SiteReferenceController@migrateQuestionsFromParent');
        $router->post('remove-duplicate-sites', 'SiteReferenceController@removeDuplicateSites');
        $router->post('remove-extra-locations', 'SiteReferenceController@removeExtraLocationData');        
        $router->post('import-summary', 'SiteReferenceController@importIndicatorSummary');
        $router->post('import-old-locations', 'SiteReferenceController@addOldDataLocations');
        $router->post('update-responsetypes', 'SiteReferenceController@updateResponseType');
        $router->post('update-base-questions', 'SiteReferenceController@updateBaseQuestions');
        
        //Category Reference
        $router->post('category-references/import', 'CategoryReferenceController@importCategory');
        $router->get('category-references', 'CategoryReferenceController@index');
        $router->get('category-references/{id}', 'CategoryReferenceController@one');
        $router->post('category-references', 'CategoryReferenceController@store');
        $router->post('category-references/{id}', 'CategoryReferenceController@update');
        $router->delete('category-references/{id}', 'CategoryReferenceController@destroy');
        
        //Disease Bank
        $router->get('project-diseases-list', 'DiseaseBankController@projectDiseases');
        $router->get('diseases', 'DiseaseBankController@index');
        $router->get('diseases/{id}', 'DiseaseBankController@one');
        $router->post('diseases', 'DiseaseBankController@store');
        $router->post('diseases/{id}', 'DiseaseBankController@update');
        $router->delete('diseases/{id}', 'DiseaseBankController@destroy');
        
        //Disease Category        
        $router->get('project-diseases', 'DiseaseCategoryController@projectDiseases');
        $router->get('disease-category', 'DiseaseCategoryController@index');
        $router->get('disease-category/{id}', 'DiseaseCategoryController@one');
        $router->post('disease-category', 'DiseaseCategoryController@store');
        $router->post('disease-category/{id}', 'DiseaseCategoryController@update');
        $router->delete('disease-category/{id}', 'DiseaseCategoryController@destroy');

        //indicators
        $router->get('indicators', 'IndicatorController@index');
        $router->get('indicators/results', 'IndicatorController@results');
        $router->post('indicators/results', 'IndicatorController@results');
        $router->get('indicators/{id}', 'IndicatorController@one');
        $router->post('indicators', 'IndicatorController@store');
        $router->post('indicators/{id}', 'IndicatorController@update');
        $router->delete('indicators/{id}', 'IndicatorController@destroy');

        //Cluster        
        $router->get('clusters', 'ClusterController@index');
        $router->get('clusters-list', 'ClusterController@clusters');
        $router->get('clusters/{id}', 'ClusterController@one');
        $router->get('governorate-sites', 'ClusterController@sites');
        $router->post('clusters', 'ClusterController@store');
        $router->post('clusters/{id}', 'ClusterController@update');        
        $router->delete('clusters/{id}', 'ClusterController@destroy');
        
        //Cluster References
        $router->get('cluster-references', 'ClusterReferenceController@index');
        $router->get('cluster-references-list', 'ClusterReferenceController@clusters');
        $router->get('cluster-references/{id}', 'ClusterReferenceController@one');
        $router->get('governorate-reference-sites', 'ClusterReferenceController@sites');
        $router->post('cluster-references', 'ClusterReferenceController@store');
        $router->post('cluster-references/{id}', 'ClusterReferenceController@update');
        $router->delete('cluster-references/{id}', 'ClusterReferenceController@destroy');
        $router->post('governorate-districts', 'ClusterReferenceController@districts');

        //Parameters
        $router->get('parameters', 'ParameterController@index');
        $router->post('parameters', 'ParameterController@store');
        $router->get('parameters/{id}', 'ParameterController@one');
        $router->post('parameters/{id}', 'ParameterController@update');
        $router->delete('parameters/{id}', 'ParameterController@destroy');

        //Categories        
        $router->get('categories', 'CategoryController@index');
        $router->post('categories', 'CategoryController@store');
        $router->get('categories/questions', 'CategoryController@categoryQuestions');
        $router->post('categories/import', 'CategoryController@importCategory');
        $router->post('categories/export', 'CategoryController@exportCategory');
        $router->get('categories/{id}', 'CategoryController@one');
        $router->post('categories/{id}', 'CategoryController@update');
        $router->delete('categories/{id}', 'CategoryController@destroy');
    }
);

$router->group(
    ['namespace' => 'User', 'prefix' => 'admin'],
    function () use ($router) {
        //User's resource
        $router->post('user-location-contacts', 'UserController@saveUserContactLocation');
        $router->post('project-permissions', 'UserController@setProjectDefaultPermissions');
        $router->post('save-user-permissions', 'UserController@savePermissions');    
        $router->post('user-template', 'UserController@downloadUserTemplate');
        $router->post('users-export', 'UserController@exportUsers');
        $router->post('users-import', 'UserController@importUsers');
        $router->get('users', 'UserController@index');
        $router->post('users', 'UserController@store');        
        $router->get('users/{id}', 'UserController@one');
        $router->post('users/{id}', 'UserController@update');
        $router->post('sign-out', 'UserController@signOut');
        $router->delete('users/{id}', 'UserController@destroy');
        
        //Group Controller
        $router->get('groups', 'GroupController@index');
        $router->post('groups', 'GroupController@store');        
        $router->get('groups/{id}', 'GroupController@one');
        $router->post('groups/{id}', 'GroupController@update');
        $router->delete('groups/{id}', 'GroupController@destroy');
        $router->get('search-members', 'GroupController@searchMembers');

        //Team
        $router->get('teams', 'TeamController@index');
        $router->get('teams/{id}', 'TeamController@one');
        $router->post('teams', 'TeamController@store');
        $router->post('teams/{id}', 'TeamController@update');
        $router->delete('teams/{id}', 'TeamController@destroy');

        //User's resource
        $router->get('collaborators', 'CollaboratorController@index');
        $router->get('collaborators/search', 'CollaboratorController@search');
        $router->post('collaborators', 'CollaboratorController@store');
        $router->post('collaborators/add', 'CollaboratorController@add');
        $router->get('collaborators/{id}', 'CollaboratorController@one');
        $router->post('collaborators/{id}', 'CollaboratorController@update');
        $router->post('collaborators-delete', 'CollaboratorController@destroy');

        //User's resource
        $router->get('guests', 'GuestController@index');
        $router->get('guests/search', 'GuestController@search');
        $router->post('guests', 'GuestController@store');
        $router->post('guests/add', 'GuestController@add');
        $router->get('guests/{id}', 'GuestController@one');
        $router->post('guests/{id}', 'GuestController@update');
        $router->post('guests-delete', 'GuestController@destroy');

        //User's resource
        $router->get('collectors', 'CollectorsController@index');
        $router->post('collectors/send-email', 'CollectorsController@sendEmailToCollectors');
        $router->get('collectors/search', 'CollectorsController@search');
        $router->post('collectors', 'CollectorsController@store');
        $router->post('collectors/add', 'CollectorsController@add');
        $router->get('collectors/{id}', 'CollectorsController@one');
        $router->post('collectors/{id}', 'CollectorsController@update');
        $router->post('collectors-delete', 'CollectorsController@destroy');
        $router->post('collectors-csv', 'CollectorsController@csv');

    }
);
$router->group(
    ['namespace' => 'Forms', 'prefix' => 'admin'],
    function () use ($router) {
        $router->get('response-types', 'ResponseTypeController@index');
        $router->post('update-results', 'ResponseTypeController@updateResults');
        $router->get('forms/{id}', 'FormController@one');
        $router->get('forms/by-project/{id}', 'FormController@byProject');
        $router->post('get-locations-for-option', 'FormController@getFormInstancesWithOption');
        $router->post('forms', 'FormController@store');
        $router->post('forms/push-to-mobile', 'FormController@pushToMobile');
        $router->post('forms/enable-form/{id}', 'FormController@enableFormStatus');
        $router->post('question-bank-import', 'QuestionBankController@importQuestionBank');
        $router->post('question-bank-export', 'QuestionBankController@exportQuestionBank');       
        $router->post('question-bank-template', 'QuestionBankController@downloadQuestionTemplate');
        $router->post('forms/category', 'FormController@saveCategory');
        $router->post('forms/export', 'FormController@exportForm');
        $router->post('forms/project-template', 'FormController@exportProjectTemplate');
        $router->post('forms/import', 'FormController@importForm');
        $router->post('duplicate-question', 'FormController@duplicateQuestion');
        $router->post('update-question', 'FormController@updateQuestion');
        $router->post('moveGroupToOtherParent', 'FormController@moveGroupToOtherParent');
        $router->post('moveQuestionToAnotherGroup', 'FormController@moveQuestionToAnotherGroup');
        $router->post('questionNumber/{id}', 'QuestionController@questionNumber');
        $router->get('search-question-bank', 'QuestionBankController@searchQuestionBank');
        $router->get('list-question-bank', 'QuestionBankController@index');
        $router->post('question-bank', 'QuestionBankController@store');
        $router->post('question-bank/{id}', 'QuestionBankController@update');
        $router->delete('question-bank/{id}', 'QuestionBankController@destroy');
        $router->post('question-bank/import/{id}', 'QuestionBankController@importQuestion');
        $router->get('project-categories/{id}', 'QuestionController@categories');
        $router->get('project-questions', 'QuestionController@questions');
        $router->get('project-questions/{id}', 'QuestionController@questionsProject');
        $router->post('export-project-questions', 'QuestionController@exportFormQuestions');
    }
);

//User
$router->group(
    ['namespace' => 'User', 'prefix' => 'user'],
    function () use ($router) {
        $router->post('edit-profile', 'ProfileController@updateProfile');
        $router->post('change-password', 'ProfileController@changePassword');
    }
);

//Results
$router->group(
    ['namespace' => 'Result', 'prefix' => 'result'],
    function () use ($router) {
        $router->get('summary/{id}', 'ResultController@summary');
        $router->post('results', 'ResultController@results');
        $router->post('results-compare', 'ResultController@resultsComparison');
        $router->post('results-comparison', 'ResultController@resultsComparison2');
        $router->post('comparison-results', 'ResultController@questionComparisonResults');
        $router->get('export/{id}', 'ResultController@export');
        $router->post('export/{id}', 'ResultController@export');
        $router->get('export-percentage/{id}', 'ResultController@exportPercentage');
        $router->get('export-percentage/{id}/{type}', 'ResultController@exportPercentage');
        $router->post('export-percentage/{id}', 'ResultController@exportPercentage');
    }
);


//Results
$router->group(
    ['namespace' => 'Result', 'prefix' => 'tracking'],
    function () use ($router) {
        $router->get('summary/{id}', 'TrackingController@summary');
        $router->post('locations/{id}', 'TrackingController@locations');
        $router->post('performance/{id}', 'TrackingController@performance');
    }
);

//Results
$router->group(
    ['namespace' => 'Result', 'prefix' => 'performance'],
    function () use ($router) {
        $router->post('byTeam/{id}', 'PerformanceController@byTeam');
    }
);
