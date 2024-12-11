<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePowerBiReportTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create(
            'pbi_data',
            function (Blueprint $table) {
				$table->string('project', 100)->default('Dummy Project');
				$table->string('governorate', 100)->default('Non-specified-governorate');
				$table->string('district', 100)->default('Non-specified-district');
				$table->string('site', 100)->default('Non-specified-site');
				$table->string('cluster', 100)->default('Non-specified-cluster');
				$table->string('unique_district', 100)->default('Non-specified-district');
				$table->string('unique_site', 100)->default('Non-specified-site');
				$table->string('unique_cluster', 100)->default('Non-specified-cluster');
				$table->string('age', 50)->default('Age<5');
				$table->unsignedInteger('age_less_than_5')->default(0);
				$table->unsignedInteger('age_greater_than_5')->default(0);
				$table->string('gender', 50)->default('Male');
				$table->unsignedInteger('male')->default(0);
				$table->unsignedInteger('female')->default(0);
				$table->decimal('lat', 10, 8)->nullable();
                $table->decimal('long', 10, 8)->nullable();
				$table->string('week', 50)->default('week0');
				$table->unsignedInteger('week_no')->default(0);
				$table->unsignedInteger('month')->default(0);
				$table->unsignedInteger('year')->default(0);
				$table->string('disease', 200)->default('Non-specified-disease');
				$table->string('disease_type', 200)->default('Non-specified-disease-type');				
				$table->unsignedInteger('value')->default(0);
				$table->string('user_agency', 100)->default('Non-specified-agency');
				$table->timestamps();
            }
        );
		
		Schema::create(
            'pbi_alerts',
            function (Blueprint $table) {
				$table->string('project', 100)->default('Dummy Project');
				$table->string('governorate', 100)->default('Non-specified-governorate');
				$table->string('district', 100)->default('Non-specified-district');
				$table->string('site', 100)->default('Non-specified-site');
				$table->string('cluster', 100)->default('Non-specified-cluster');
				$table->string('unique_district', 100)->default('Non-specified-district');
				$table->string('unique_site', 100)->default('Non-specified-site');
				$table->string('unique_cluster', 100)->default('Non-specified-cluster');
				$table->string('gender', 50)->default('Male');
				$table->decimal('lat', 10, 8)->nullable();
                $table->decimal('long', 10, 8)->nullable();
				$table->string('week', 50)->default('week0');
				$table->unsignedInteger('week_no')->default(0);
				$table->unsignedInteger('month')->default(0);
				$table->unsignedInteger('year')->default(0);
				$table->string('disease', 200)->default('Non-specified-disease');
				$table->string('disease_type', 200)->default('Non-specified-disease-type');				
				$table->string('user_agency', 100)->default('Non-specified-agency');
				$table->string('inv_result', 100)->default('Pending');
				$table->string('date_of_collection', 20)->default(0);
				$table->string('date_of_result', 20)->default(0);
				$table->timestamps();
            }
        );
		
		Schema::create(
            'pbi_consultations',
            function (Blueprint $table) {
				$table->string('project', 100)->default('Dummy Project');
				$table->string('governorate', 100)->default('Non-specified-governorate');
				$table->string('district', 100)->default('Non-specified-district');
				$table->string('site', 100)->default('Non-specified-site');
				$table->string('cluster', 100)->default('Non-specified-cluster');
				$table->string('disease', 200)->default('Non-specified-disease');				
				$table->string('collector', 100)->default('Non-specified-user');
				$table->string('unique_district', 100)->default('Non-specified-district');
				$table->string('unique_site', 100)->default('Non-specified-site');
				$table->string('unique_cluster', 100)->default('Non-specified-cluster');
				$table->string('week', 50)->default('week0');
				$table->unsignedInteger('week_no')->default(0);
				$table->unsignedInteger('month')->default(0);
				$table->unsignedInteger('year')->default(0);
				$table->unsignedInteger('total_consultations')->default(0);
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
        Schema::drop('pbi_data');
		Schema::drop('pbi_alerts');
		Schema::drop('pbi_consultations');
    }
}
