<?php

use Illuminate\Database\Seeder;
use Idea\Models\Page;
use Idea\Models\PageTranslation;

class PageTableSeeder extends Seeder {

  /**
   * Run the database seeds.
   *
   * @return void
   */
  public function run() {
    $page = [
      ['id' => '1', 'code' => 'about_us', 'url' => ''],
    ];
    Page::insert($page);

    $pageTranslation = [
      [
        'page_id' => '1',
        'locale'  => 'en',
        'title'   => 'About Us',
        'body'    => 'lorem ipsum',
      ],
    ];
    PageTranslation::insert($pageTranslation);
  }
}
