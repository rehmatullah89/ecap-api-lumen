<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateDefaultPermissionTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
		if (!Schema::hasTable('default_user_permissions')){
			Schema::create('default_user_permissions', function (Blueprint $table) {
				$table->bigIncrements('id');
				$table->unsignedBigInteger('permission_id');
				$table->unsignedBigInteger('user_id');
				$table->unsignedBigInteger('action_id')->nullable();
				$table->timestamps();
				
				$table->index('id');
				$table->index('permission_id');
				$table->index('user_id');
			});
		}
		
		if (!Schema::hasTable('default_project_permissions')){
			Schema::create('default_project_permissions', function (Blueprint $table) {
				$table->bigIncrements('id');
				$table->unsignedBigInteger('permission_id');
				$table->unsignedBigInteger('user_id');
				$table->unsignedBigInteger('action_id')->nullable();
				$table->unsignedInteger('title_id')->nullable();
				$table->timestamps();
				
				$table->index('id');
				$table->index('permission_id');
				$table->index('user_id');
			});
		}
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
         Schema::dropIfExists('default_user_permissions');
		 Schema::dropIfExists('default_project_permissions');
    }
}
