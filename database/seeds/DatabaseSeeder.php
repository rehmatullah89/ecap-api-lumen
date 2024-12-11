<?php

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder {

    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run() {
		$this->call('SkipLogicSeeder');
        $this->call('CountryTableSeeder');
        $this->call('RoleTableSeeder');
        $this->call('UserTableSeeder');
        $this->call('ProfileTableSeeder');
        $this->call('PermissionTableSeeder');
		$this->call('SystemPermissionSeeder');
        $this->call('PageTableSeeder');
        $this->call('ActionTableSeeder');
        $this->call('LanguagesTableSeeder');
        $this->call('RolePermissionTableSeeder');
    }

}
