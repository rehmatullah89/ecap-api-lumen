<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSiteClusterReferenceTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
         // Create sites Table
        Schema::create(
            'site_references', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('governorate_id');
				$table->unsignedBigInteger('district_id')->nullable();
                $table->string('name');
                $table->text('description')->nullable();
				$table->decimal('lat', 10, 8)->nullable();
                $table->decimal('lng', 10, 8)->nullable();
            
                $table->foreign('governorate_id')
                    ->references('id')
                    ->on("governorates")
                    ->onUpdate('CASCADE')
                    ->onDelete('NO ACTION');
            
                $table->timestamps();
                $table->softDeletes();//soft delete is better here
            }
        );
        
        // Create clusters Table
        Schema::create(
            'cluster_references', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('site_id');
                $table->string('name');
                $table->text('description')->nullable();
				$table->text('coordinates')->nullable();
                $table->decimal('lat', 10, 8)->nullable();
                $table->decimal('lng', 10, 8)->nullable();
            
                $table->foreign('site_id')
                    ->references('id')
                    ->on("site_references")
                    ->onUpdate('CASCADE')
                    ->onDelete('NO ACTION');
            
                $table->timestamps();
                $table->softDeletes();//soft delete is better here
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
		Schema::drop('site_references');
        Schema::drop('cluster_references');
    }
}
