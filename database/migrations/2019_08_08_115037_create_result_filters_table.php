<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateResultFiltersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
         Schema::create('result_filters', function (Blueprint $table) {
			$table->bigIncrements('id');
			$table->unsignedBigInteger('project_id');
			$table->string('title',150)->nullable();
			$table->string('site_ids',150)->nullable();
			$table->string('cluster_ids',150)->nullable();
			$table->string('question_ids',150)->nullable();
			$table->string('collector_ids',150)->nullable();
			$table->dateTime('date_from')->nullable();
			$table->dateTime('date_to')->nullable();
			$table->timestamps();	
			
			$table->index('id');
			$table->index('project_id');
		});
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('result_filters');
    }
}
