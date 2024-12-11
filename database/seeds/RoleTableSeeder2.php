<?php

use Idea\Models\Role;
use Illuminate\Database\Seeder;

class RoleTableSeeder2 extends Seeder
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
        ['id' => 9, 'slug' => 'super_admin', 'type'=>'user'],
        ['id' => 10, 'slug' => 'admin', 'type'=>'user'],
        ['id' => 11, 'slug' => 'normal', 'type'=>'user'],
        ['id' => 12, 'slug' => 'collector', 'type'=>'project'],
        ];
        Role::insert($roles);
    }
}