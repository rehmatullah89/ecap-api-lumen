<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateResultExecutionTimeTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('result_executions', function (Blueprint $table) {
			$table->bigIncrements('id');			
			$table->unsignedBigInteger('user_id');		
			$table->dateTime('date_time')->nullable();
			$table->timestamps();
		});
		
		if (Schema::hasTable('project_details')){
			Schema::table('project_details', function (Blueprint $table) {
				$table->unsignedBigInteger('governorate_id')->change();
				$table->unsignedBigInteger('district_id')->change();
			});
		}
		
		if (Schema::hasTable('project_location_details')){
			Schema::table('project_location_details', function (Blueprint $table) {
				$table->unsignedBigInteger('governorate_id')->change();
				$table->unsignedBigInteger('district_id')->change();
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
        Schema::drop('result_executions');
    }
}
