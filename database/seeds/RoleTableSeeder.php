<?php

use Idea\Models\Role;
use Illuminate\Database\Seeder;

class RoleTableSeeder extends Seeder
{
    
    
    //project manager => read write export
    //suppervisor => read, export
    //Guest => read (specific)
    
    /**
     * Run the database seeds.
     *
     * @return void
     *
     * insert user role
     */
    public function run() 
    {
        $roles = [
        ['id' => 1, 'slug' => 'admin', 'type'=>'project'],
        ['id' => 2, 'slug' => 'owner', 'type'=>'project'],
        ['id' => 3, 'slug' => 'project_administrator', 'type'=>'project'],//same as admin with ability to create admin
        ['id' => 4, 'slug' => 'project_manager', 'type'=>'project'],//per project (read, write , export)
        ['id' => 5, 'slug' => 'supervisor', 'type'=>'project'],//per project (read , export)
        ['id' => 6, 'slug' => 'guest', 'type'=>'project'],//per project and SITES (read)
        ['id' => 7, 'slug' => 'external', 'type'=>'project'],
        ['id' => 8, 'slug' => 'super_guest', 'type'=>'project'],
        ['id' => 9, 'slug' => 'super_admin', 'type'=>'user'],
        ['id' => 10, 'slug' => 'admin', 'type'=>'user'],
        ['id' => 11, 'slug' => 'normal', 'type'=>'user'],
        ['id' => 12, 'slug' => 'collector', 'type'=>'project'],
		['id' => 13, 'slug' => 'verifier_district', 'type'=>'verifier'],
		['id' => 14, 'slug' => 'laboratory', 'type'=>'verifier'],
		['id' => 15, 'slug' => 'clinic', 'type'=>'verifier'],
		['id' => 16, 'slug' => 'higher_verifier', 'type'=>'verifier'],
        ];
        //Role::insert($roles);
		
        foreach ($roles as $role) {
            \Idea\Models\Role::updateOrCreate(['id' => $role['id']], $role);
        }
    }
}