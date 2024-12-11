<?php

use Idea\Models\Action;
use Illuminate\Database\Seeder;

class ActionTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $actions = array(
            array('id'=>'1' , 'name'=>'read' ),
            array('id'=>'2' , 'name'=>'write' ),
            array('id'=>'3' , 'name'=>'export' ), // this exists in new databse, added by muhaammad abid
            array('id'=>'4' , 'name'=>'new' ), // this exists in new databse, added by muhaammad abid
        );

        Action::insert($actions);
    }
}
