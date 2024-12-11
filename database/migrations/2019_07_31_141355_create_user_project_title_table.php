<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateUserProjectTitleTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
		Schema::create('project_user_titles', function (Blueprint $table) {
			$table->unsignedBigInteger('project_id');
			$table->unsignedBigInteger('user_id');
			$table->unsignedBigInteger('title_id');
			
			$table->foreign('project_id')
			      ->references('id')
			      ->on("projects")
			      ->onUpdate('CASCADE')
			      ->onDelete('CASCADE');
				  
			$table->foreign('user_id')
			      ->references('id')
			      ->on("users")
			      ->onUpdate('CASCADE')
			      ->onDelete('CASCADE');	  
							  
			$table->timestamps();				  
		});
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
		Schema::drop('project_user_titles');
    }
}
