<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateGovernorateFieldInResults extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('form_instances',
            function (Blueprint $table) {
                $table->unsignedBigInteger('governorate_id')->after('cluster_id')->default(0);
                $table->unsignedBigInteger('district_id')->after('governorate_id')->nullable()->default(0);
            });
			
		Schema::table('indicators_results',
            function (Blueprint $table) {
                $table->unsignedBigInteger('governorate_id')->after('cluster_id')->default(0);
                $table->unsignedBigInteger('district_id')->after('governorate_id')->nullable()->default(0);
            });	
			
			Schema::table('profiles',
            function (Blueprint $table) {
				$table->string('title', 100)->after('last_name')->nullable(); 
				$table->string('position', 150)->after('title')->nullable();
            });	
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('indicators_results',
            function (Blueprint $table) {
                $table->dropColumn('governorate_id');
                $table->dropColumn('district_id');
            });
			
		Schema::table('form_instances',
            function (Blueprint $table) {
                $table->dropColumn('governorate_id');
                $table->dropColumn('district_id');
            });

		Schema::table('profiles',
            function (Blueprint $table) {
                $table->dropColumn('title');
                $table->dropColumn('position');
            });		
    }
}
