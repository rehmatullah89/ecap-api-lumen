<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateProjectPermissionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create(
            'project_permissions',
            function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('permission_id');
                $table->unsignedInteger('user_id');
                $table->unsignedInteger('project_id');
                $table->unsignedInteger('action_id')->nullable();
		$table->unsignedInteger('title_id')->nullable();
                
                            $table->foreign('permission_id')
			      ->references('id')
			      ->on("permissions")
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
        Schema::drop('project_permissions');
    }
}
