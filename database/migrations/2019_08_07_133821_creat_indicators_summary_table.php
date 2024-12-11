<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatIndicatorsSummaryTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('indicators_summary', function (Blueprint $table) {
			$table->unsignedBigInteger('project_id');
			$table->unsignedTinyInteger('level');
			$table->string('name', 100);
			$table->dateTime('latest')->nullable();
			$table->dateTime('first')->nullable();
			$table->unsignedBigInteger('total');
			$table->timestamps();	
			
			$table->index('project_id');
			$table->index('level');
		});
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('indicators_summary');
    }
}
