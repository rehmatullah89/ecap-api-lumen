<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateUserConfigurationFields extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
		Schema::table('users', function (Blueprint $table) {
            $table->enum('user_type', array('survey', 'surveillance', 'both'))->after("jwt_sign")->default('both');
        });
		
        Schema::table('groups', function (Blueprint $table) {
            $table->enum('group_type', array('survey', 'surveillance', 'both'))->after("description")->default('both');
        });
		
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
		Schema::table('users', function (Blueprint $table) {
             $table->dropColumn('user_type');
        });
        
		Schema::table('groups', function (Blueprint $table) {
             $table->dropColumn('group_type');
        });
    }
}
