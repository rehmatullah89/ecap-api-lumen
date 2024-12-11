<?php

use Idea\Models\Role;
use Illuminate\Database\Seeder;

class UpdatePermissionNamesSeeder extends Seeder
{
    
    /**
     * Run the database seeds.
     *
     * @return void
     *
     * insert user role
     */
    public function run() 
    {
		\DB::update('UPDATE permissions SET id= (id+1) Order By id desc');
        $items = [
			['id' => 1, 'name' => 'All', 'module'=>'all', 'code'=>'all'],
            ['id' => 2, 'name' => 'User Roles'],
            ['id' => 3, 'name' => 'Push Notification'],
            ['id' => 4, 'name' => 'Configuration'],
            ['id' => 5, 'name' => 'Feedback'],
            ['id' => 6, 'name' => 'Pages'],
            ['id' => 7, 'name' => 'Projects'],
            ['id' => 8, 'name' => 'Sites'],
            ['id' => 9, 'name' => 'Clusters'],
            ['id' => 10, 'name' => 'Teams'],
            ['id' => 11, 'name' => 'Indicators'],
			['id' => 12, 'name' => 'Collaborators'],
            ['id' => 13, 'name' => 'Guests'],
            ['id' => 14, 'name' => 'Collectors'],
            ['id' => 15, 'name' => 'Forms'],
            ['id' => 16, 'name' => 'Results'],
            ['id' => 17, 'name' => 'Trackings'],
            ['id' => 18, 'name' => 'Form Sites'],
            ['id' => 19, 'name' => 'Form Duration'],
            ['id' => 20, 'name' => 'Form Teams'],
            ['id' => 21, 'name' => 'Form Members'],
			['id' => 22, 'name' => 'Form Description'],
            ['id' => 23, 'name' => 'Export Raw Data'],
            ['id' => 24, 'name' => 'Export Comparison Data Dy Site'],
            ['id' => 25, 'name' => 'Export Specific Questions'],
        ];
        
        foreach ($items as $item) {
            \App\Models\Permission::updateOrCreate(['id' => $item['id']], $item);
        }
    }
}