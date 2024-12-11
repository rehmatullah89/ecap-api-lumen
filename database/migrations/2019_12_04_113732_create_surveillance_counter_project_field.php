<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSurveillanceCounterProjectField extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('surveillance_report_counters', function (Blueprint $table) {
            $table->unsignedBigInteger('project_id')->after("user_id")->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('surveillance_report_counters', function (Blueprint $table) {
             $table->dropColumn('project_id');
        });
    }
}
