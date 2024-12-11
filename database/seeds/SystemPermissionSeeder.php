<?php

use Illuminate\Database\Seeder;

class SystemPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
		
		$systemPermissions = [
			['id' => 1, 'module' => 'all', 								'name' => 'All',   						'code' => 'all'],
			['id' => 2, 'module' => 'user configuration',  				'name' => 'User Configuration',   		'code' => 'user_configuration'],
			['id' => 3, 'module' => 'site configuration', 				'name' => 'Site Configuration',   		'code' => 'site_configuration'],
			['id' => 4, 'module' => 'cluster configuration', 			'name' => 'Cluster Configuration',  	'code' => 'cluster_configuration'],
			['id' => 5, 'module' => 'question bank', 					'name' => 'Question Bank',   			'code' => 'question_bank'],
			['id' => 6, 'module' => 'category library', 				'name' => 'Category Library',  			'code' => 'category_library'],
			['id' => 7, 'module' => 'projects', 						'name' => 'Projects',  					'code' => 'projects'],
			['id' => 8, 'module' => 'groups', 							'name' => 'Groups',  					'code' => 'groups'],
			['id' => 9, 'module' => 'survey projects',          		'name' => 'Survey Projects',        	'code' => 'survey_projects'],
			['id' => 10, 'module' => 'surveillance projects',   		'name' => 'Surveillance Projects',  	'code' => 'surveillance_projects'],
			['id' => 11, 'module' => 'survey location management', 		'name' => 'Survey Location Management', 'code' => 'survey_location_management'],
			['id' => 12, 'module' => 'surveillance location management', 'name' => 'Surveillance Location Management', 'code' => 'surveillance_location_management'],
			['id' => 13, 'module' => 'survey user management',       	'name' => 'Survey User Management',     'code' => 'survey_user_management'],
			['id' => 14, 'module' => 'surveillance user management',       	'name' => 'Surveillance User Management',     'code' => 'surveillance_user_management'],
			['id' => 15, 'module' => 'survey library management',       'name' => 'Survey Library Management',     'code' => 'survey_library_management'],
			['id' => 16, 'module' => 'surveillance library management',       'name' => 'Surveillance Library Management',     'code' => 'surveillance_library_management'],			
		];
		
	    //\App\Models\SystemPermissions::insert($systemPermissions);
		foreach ($systemPermissions as $permission) {
            \App\Models\SystemPermission::updateOrCreate(['id' => $permission['id']], $permission);
        }
    }
}
