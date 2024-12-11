<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSurveillanceReportsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create(
            'surveillance_report_counters',
            function (Blueprint $table) {
                $table->bigIncrements('id');
				$table->unsignedBigInteger('disease_id')->nullable();
				$table->unsignedBigInteger('user_id')->nullable();
				$table->unsignedBigInteger('age')->nullable();
				$table->unsignedBigInteger('number')->nullable();
				$table->string('gender', 20)->nullable();
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
        Schema::drop('surveillance_report_counters');
    }
}
