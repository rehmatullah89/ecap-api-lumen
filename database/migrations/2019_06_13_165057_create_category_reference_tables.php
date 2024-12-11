<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCategoryReferenceTables extends Migration
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
            'category_references', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('category_en');
				$table->string('category_ar')->nullable();
				$table->string('category_ku')->nullable();            
                $table->timestamps();
                $table->softDeletes();//soft delete is better here
            }
        );
        
        // Create cateory questions Table
        Schema::create(
            'category_questions', function (Blueprint $table) {
                $table->unsignedBigInteger('category_id');
				$table->unsignedBigInteger('question_id');
				
                $table->foreign('category_id')
                    ->references('id')
                    ->on("category_references")
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
        //
    }
}
