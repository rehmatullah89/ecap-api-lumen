<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSystemConfigurationTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create(
            'system_permissions',
            function (Blueprint $table) {
                $table->increments('id');
                $table->string('module');
                $table->string('name');
                $table->string('code');
                $table->timestamps();
            }
        );
		
		Schema::create(
            'user_permissions',
            function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('permission_id');
                $table->unsignedInteger('user_id');
                $table->unsignedInteger('action_id')->nullable();
				
				$table->foreign('permission_id')
			      ->references('id')
			      ->on("system_permissions")
			      ->onUpdate('CASCADE')
			      ->onDelete('CASCADE');

                $table->timestamps();
            }
        );

						
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('system_permissions');
		Schema::drop('user_permissions');
    }
}
