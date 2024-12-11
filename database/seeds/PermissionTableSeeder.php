<?php

use Idea\Models\Permission;

use Illuminate\Database\Seeder;

class PermissionTableSeeder extends Seeder {

  /**
   * Run the database seeds.
   *
   * @return void
   */
  public function run() {
    //
    $permissions = [
		['id' => 1, 'module' => 'all', 'name' => 'All', 'code' => 'all',],
        ['id' => 2, 'module' => 'user roles', 'name' => 'User Roles', 'code' => 'user_roles',],
        ['id' => 3, 'module' => 'push notification', 'name' => 'Push Notification', 'code' => 'push_notifications',],
        ['id' => 4, 'module' => 'configuration', 'name' => 'Configuration', 'code' => 'configuration',],
        ['id' => 5, 'module' => 'feedback', 'name' => 'Feedback', 'code' => 'feedback',],
        ['id' => 6, 'module' => 'pages', 'name' => 'Pages', 'code' => 'pages',],
        ['id' => 7, 'module' => 'projects', 'name' => 'projects', 'code' => 'projects',],
        ['id' => 8, 'module' => 'sites', 'name' => 'sites', 'code' => 'sites',],
        ['id' => 9, 'module' => 'clusters', 'name' => 'clusters', 'code' => 'clusters',],
        ['id' => 10, 'module' => 'teams', 'name' => 'teams', 'code' => 'teams',],
        ['id' => 11, 'module' => 'indicators', 'name' => 'indicators', 'code' => 'indicators',],
        ['id' => 12, 'module' => 'collaborators', 'name' => 'collaborators', 'code' => 'collaborators',],
        ['id' => 13, 'module' => 'guests', 'name' => 'guests', 'code' => 'guests',],
        ['id' => 14, 'module' => 'collectors', 'name' => 'Collect Data (collectors)', 'code' => 'collectors',],
        ['id' => 15, 'module' => 'forms', 'name' => 'forms', 'code' => 'forms',],
        ['id' => 16, 'module' => 'results', 'name' => 'results', 'code' => 'results',],
        ['id' => 17, 'module' => 'trackings', 'name' => 'trackings', 'code' => 'trackings',],
        ['id' => 18, 'module' => 'form_sites', 'name' => 'form_sites', 'code' => 'form_sites',],
        ['id' => 19, 'module' => 'form_duration', 'name' => 'form_duration', 'code' => 'form_duration',],
        ['id' => 20, 'module' => 'form_teams', 'name' => 'form_teams', 'code' => 'form_teams',],
        ['id' => 21, 'module' => 'form_members', 'name' => 'form_members', 'code' => 'form_members',],
        ['id' => 22, 'module' => 'form_description', 'name' => 'form_description', 'code' => 'form_description',],
        ['id' => 23, 'module' => 'export_raw_data', 'name' => 'export_raw_data', 'code' => 'export_raw_data',],
        ['id' => 24, 'module' => 'export_comparison_data_by_site', 'name' => 'export_comparison_data_by_site', 'code' => 'export_comparison_data_by_site',],
        ['id' => 25, 'module' => 'export_specific_questions', 'name' => 'export_specific_questions', 'code' => 'export_specific_questions',],
		['id' => 26, 'module' => 'form_preview', 'name' => 'Form Preview', 'code' => 'form_preview',]
		['id' => 27, 'module' => 'parameters', 'name' => 'Parameters', 'code' => 'parameters',]
      ];

      
    Permission::insert($permissions);
  }
}
