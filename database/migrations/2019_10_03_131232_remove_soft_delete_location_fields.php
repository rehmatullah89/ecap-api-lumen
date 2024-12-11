<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class RemoveSoftDeleteLocationFields extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
		if (Schema::hasColumn('project_location_details', 'deleted_at')) {			
			Schema::table('project_location_details', function (Blueprint $table) {
				$table->dropColumn('deleted_at');  
			});			
		}
		
		if (Schema::hasColumn('project_details', 'deleted_at')) { 
			Schema::table('project_details', function (Blueprint $table) {
				$table->dropColumn('deleted_at');  
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
        //
    }
}
