<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateUserLocationManagementFields extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasColumn('project_location_details', 'governorate_id')) {
			Schema::table('project_location_details', function (Blueprint $table) {
				$table->unsignedBigInteger('governorate_id')->after("user_id")->default(0)->nullable();
			});   
		}
		
		if (!Schema::hasColumn('project_location_details', 'district_id')) {
			Schema::table('project_location_details', function (Blueprint $table) {
				$table->unsignedBigInteger('district_id')->after("governorate_id")->default(0)->nullable();
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
        Schema::table('project_location_details', function (Blueprint $table) {
            $table->dropColumn('governorate_id');
        });
		
		Schema::table('project_location_details', function (Blueprint $table) {
            $table->dropColumn('district_id');
        });
    }
}
