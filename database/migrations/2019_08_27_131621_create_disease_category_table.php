<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateDiseaseCategoryTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Create category Table
        Schema::create(
            'disease_categories', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('category_en');
				$table->string('category_ar')->nullable();
				$table->string('category_ku')->nullable();            
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
        Schema::drop("disease_categories");
    }
}
