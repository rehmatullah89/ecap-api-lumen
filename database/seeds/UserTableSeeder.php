<?php

use Idea\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserTableSeeder extends Seeder {

  /**
   * Run the database seeds.
   *
   * @return void
   *
   * insert admin user
   */
  public function run() {
    $defaultUser           = new User();
    $defaultUser->email    = 'default@ideatolife.me';
    $defaultUser->password = Hash::make('default_A123');
    $defaultUser->name     = 'default';
    $defaultUser->username = 'default@ideatolife.me';
    $defaultUser->active   = 1;
    $defaultUser->getJWTCustomClaims();
    $defaultUser->assignAdminRole();
    $defaultUser->save();

    $admin           = new User();
    $admin->email    = 'test.ideatolife@gmail.com';
    $admin->password = Hash::make('admi_1@n123');
    $admin->name     = 'admin';
    $admin->username = 'test.ideatolife@gmail.com';
    $admin->active   = 1;
    $admin->getJWTCustomClaims();
    $admin->assignAdminRole();
    $admin->save();
  }


}