<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateProjectDetailsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
		Schema::create('project_details', function (Blueprint $table) {
			$table->unsignedBigInteger('project_id');
			$table->unsignedBigInteger('governorate_id');
			$table->unsignedBigInteger('district_id');
			$table->unsignedBigInteger('site_id');
			$table->unsignedBigInteger('cluster_id');
			
			$table->foreign('project_id')
			      ->references('id')
			      ->on("projects")
			      ->onUpdate('CASCADE')
			      ->onDelete('CASCADE');
			
			$table->foreign('governorate_id')
			      ->references('id')
			      ->on("governorates")
			      ->onUpdate('CASCADE')
			      ->onDelete('CASCADE');
                        
			$table->foreign('district_id')
			      ->references('id')
			      ->on("districts")
			      ->onUpdate('CASCADE')
			      ->onDelete('CASCADE');
                        
			$table->foreign('site_id')
			      ->references('id')
			      ->on("site_references")
			      ->onUpdate('CASCADE')
			      ->onDelete('CASCADE');
			
			$table->foreign('cluster_id')
			      ->references('id')
			      ->on("cluster_references")
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
		Schema::drop('project_details');
    }
}
