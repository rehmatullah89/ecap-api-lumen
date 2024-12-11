<?php

use Idea\Models\Language;
use Illuminate\Database\Seeder;

class LanguagesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $languages = array(
            array(
                'title'  => 'English',
                'locale' => 'en',
            ),
            array(
                'title'  => 'عربي',
                'locale' => 'ar',
            ),
        );
        Language::insert($languages);
    }
}
