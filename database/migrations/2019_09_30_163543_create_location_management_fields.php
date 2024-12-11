<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateLocationManagementFields extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasColumn('project_details', 'governorate_id')) {
			Schema::table('project_details', function (Blueprint $table) {
				$table->unsignedBigInteger('governorate_id')->after("project_id")->default(0)->nullable();
			});   
		}
		
		if (!Schema::hasColumn('project_details', 'district_id')) {
			Schema::table('project_details', function (Blueprint $table) {
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
        Schema::table('project_details', function (Blueprint $table) {
            $table->dropColumn('governorate_id');
        });
		
		Schema::table('project_details', function (Blueprint $table) {
            $table->dropColumn('district_id');
        });
    }
}
