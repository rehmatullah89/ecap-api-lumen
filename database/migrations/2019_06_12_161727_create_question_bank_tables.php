<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateQuestionBankTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //question : Group , Label , Name, type , required , multiple , setting
		Schema::create('question_bank', function (Blueprint $table) {
			$table->bigIncrements('id');
			$table->string('name_en')->nullable();
			$table->string('name_ar')->nullable();
			$table->string('name_ku')->nullable();
			$table->string('question_code')->nullable();
			$table->text('consent')->nullable();
			$table->text('mobile_consent')->nullable();
			$table->boolean('required')->default(FALSE);
			$table->boolean('multiple')->default(FALSE);
			$table->unsignedBigInteger('response_type_id');
			$table->unsignedInteger('order')->default(0);
			$table->text('setting')->nullable();
			$table->string('question_number')->nullable();
			
			$table->foreign('response_type_id')
			      ->references('id')
			      ->on("question_response_types")
			      ->onUpdate('CASCADE')
			      ->onDelete('NO ACTION');
			
			$table->timestamps();
			$table->softDeletes();//soft delete is better here
		});
		
		//options
		Schema::create('question_bank_options', function (Blueprint $table) {
			$table->bigIncrements('id');
			$table->string('name_en')->nullable();
			$table->string('name_ar')->nullable();
			$table->string('name_ku')->nullable();
			$table->unsignedBigInteger('question_id');
			$table->boolean('stop_collect')->default(FALSE);
			$table->integer('order_value')->default(0);
			
			$table->foreign('question_id')
			      ->references('id')
			      ->on("question_bank")
			      ->onUpdate('CASCADE')
			      ->onDelete('CASCADE');
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
        Schema::drop('question_bank');
		Schema::drop('question_bank_options');
    }
}
