<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSruveillanceContactAndLocationTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('surveillance_locations', function (Blueprint $table) {
			$table->bigIncrements('id');
			$table->unsignedBigInteger('user_id');
			$table->unsignedBigInteger('governorate_id');
			$table->unsignedBigInteger('district_id');
			$table->unsignedBigInteger('site_id');
			$table->unsignedBigInteger('cluster_id');
			$table->unsignedBigInteger('population')->default(0);
			$table->decimal('lat', 10, 8)->nullable();
			$table->decimal('lng', 10, 8)->nullable();

			$table->foreign('user_id')
				->references('id')
				->on("users")
				->onUpdate('CASCADE')
				->onDelete('NO ACTION');
				
			$table->foreign('governorate_id')
				->references('id')
				->on("governorates")
				->onUpdate('CASCADE')
				->onDelete('NO ACTION');

			$table->foreign('district_id')
				->references('id')
				->on("districts")
				->onUpdate('CASCADE')
				->onDelete('NO ACTION');
				
			$table->foreign('site_id')
				->references('id')
				->on("site_references")
				->onUpdate('CASCADE')
				->onDelete('NO ACTION');
					
			$table->foreign('cluster_id')
				->references('id')
				->on("cluster_references")
				->onUpdate('CASCADE')
				->onDelete('NO ACTION');
				
			$table->timestamps();	
		});
		
		// Create disease bank questions Table
        Schema::create(
            'surveillance_contacts', function (Blueprint $table) {
				$table->bigIncrements('id');
                $table->unsignedBigInteger('user_id');
				$table->string('contact_person', 100)->nullable();
				$table->string('contact_number', 100)->nullable();
				
                $table->foreign('user_id')
                    ->references('id')
                    ->on("users")
                    ->onUpdate('CASCADE')
                    ->onDelete('NO ACTION');
            
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
		Schema::dropIfExists('surveillance_locations');
		Schema::dropIfExists('surveillance_contacts');
    }
}
