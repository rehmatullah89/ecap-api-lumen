<?php

use Idea\Models\Profile;
use Illuminate\Database\Seeder;

class ProfileTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $profile          = new Profile();
        $profile->user_id = 1;
        $profile->save();
    }
}
