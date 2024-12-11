<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTempFormInstances extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('temp_form_instances', function (Blueprint $table) {
			$table->bigIncrements('id');
			$table->unsignedBigInteger('project_id');
			$table->unsignedBigInteger('user_id');			
			$table->decimal('lat', 10, 8)->nullable();
			$table->decimal('lng', 10, 8)->nullable();
			$table->unsignedBigInteger('governorate_id')->nullable();
			$table->unsignedBigInteger('district_id')->nullable();
			$table->unsignedBigInteger('site_id')->nullable();
			$table->unsignedBigInteger('cluster_id')->nullable();
			$table->timestamp('date_start')->nullable(); // this exists in new databse, added by muhaammad abid
			$table->timestamp('date_end')->nullable(); // this exists in new databse, added by muhaammad abid
			$table->string('old_lat_lng')->nullable(); // this exists in new databse, added by muhaammad abid			
			$table->boolean('individual_count')->default(FALSE)->nullable();
			$table->boolean('stopped')->default(FALSE);
					
			$table->timestamps();
			$table->softDeletes();//soft delete is better here
		});
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
