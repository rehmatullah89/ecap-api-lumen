<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSureillanceReportAdditionalTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
		Schema::create(
            'surveillance_report_additionals',
            function (Blueprint $table) {
                $table->bigIncrements('id');
				$table->unsignedBigInteger('project_id')->nullable();
				$table->unsignedBigInteger('user_id')->nullable();
				$table->enum('report_type', array('daily','weekly', 'monthly'))->default('weekly')->nullable();
				/*$table->unsignedBigInteger('type_number')->nullable();
				$table->unsignedBigInteger('type_year')->nullable();*/				
				$table->unsignedBigInteger('additional')->nullable();
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
        Schema::drop('surveillance_report_additionals');
    }
}
