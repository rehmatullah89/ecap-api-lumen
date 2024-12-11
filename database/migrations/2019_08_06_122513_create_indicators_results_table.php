<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateIndicatorsResultsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('indicators_results', function (Blueprint $table) {
			$table->unsignedBigInteger('project_id');
			$table->unsignedBigInteger('instance_id');
			$table->unsignedBigInteger('question_id');
			$table->unsignedBigInteger('response_type_id');
			$table->unsignedBigInteger('user_id');
			$table->unsignedBigInteger('site_id');
			$table->unsignedBigInteger('cluster_id');
			$table->unsignedTinyInteger('multiple');
			$table->integer('individual_chunk');
			$table->text('value')->nullable();
			$table->unsignedTinyInteger('individual_count')->default(1);
			$table->boolean('stopped')->default(0);
			$table->dateTime('date_time')->nullable();
			$table->timestamps();	
			
			$table->index('project_id');
			$table->index('question_id');
			$table->index('user_id');
			$table->index('site_id');
			$table->index('cluster_id');
		});
	}

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('indicators_results');
    }
}
