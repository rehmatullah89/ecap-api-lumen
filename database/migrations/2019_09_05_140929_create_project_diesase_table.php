<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateProjectDiesaseTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
		Schema::create('disease_details', function (Blueprint $table) {
			$table->unsignedBigInteger('project_id');
			$table->unsignedBigInteger('disease_category_id');
			$table->unsignedBigInteger('disease_id');
			
			$table->foreign('project_id')
			      ->references('id')
			      ->on("projects")
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
        Schema::dropIfExists('disease_details');
    }
}
