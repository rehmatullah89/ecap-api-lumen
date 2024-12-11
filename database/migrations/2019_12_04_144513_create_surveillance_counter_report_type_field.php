<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSurveillanceCounterReportTypeField extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('surveillance_report_counters', function (Blueprint $table) {
            $table->enum('report_type', array('daily','weekly', 'monthly'))->default('weekly')->after("user_id")->nullable();
        });
		
		if (!Schema::hasColumn('surveillance_report_additionals', 'report_type')) {
			Schema::table('surveillance_report_additionals', function (Blueprint $table) {
				$table->enum('report_type', array('daily','weekly', 'monthly'))->default('weekly')->after("user_id")->nullable();
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
        Schema::table('surveillance_report_counters', function (Blueprint $table) {
             $table->dropColumn('report_type');
        });
    }
}
