<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSurveillanceInstanceDiseaseField extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasColumn('surveillance_form_instances', 'disease_id')) {
			Schema::table('surveillance_form_instances', function (Blueprint $table) {
				$table->unsignedBigInteger('disease_id')->after("instance_status")->default(0)->nullable();
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
        Schema::table('surveillance_form_instances', function (Blueprint $table) {
            $table->dropColumn('disease_id');
        });
    }
}
