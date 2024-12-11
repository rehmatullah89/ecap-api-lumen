<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateDiseaseBankTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('disease_bank', function (Blueprint $table) {
			$table->bigIncrements('id');
			$table->string('disease_color', 100);
			$table->unsignedBigInteger('icd_code_id');
			$table->unsignedBigInteger('disease_category_id');
			$table->string('disease_group', 100)->nullable();
			$table->string('disease_type', 50)->nullable();
			$table->string('appearance_name_en', 250)->nullable();
			$table->string('appearance_name_ar', 250)->nullable();
			$table->string('appearance_name_ku', 250)->nullable();
			$table->string('district_confirmation', 100)->nullable();
			$table->string('laboratory_confirmation', 100)->nullable();
			$table->string('clinical_confirmation', 100)->nullable();
			$table->string('higher_confirmation', 100)->nullable();
							  
			$table->timestamps();	
			$table->softDeletes();//soft delete is better here			
		});
		
		// Create disease bank questions Table
        Schema::create(
            'disease_bank_questions', function (Blueprint $table) {
                $table->unsignedBigInteger('disease_bank_id');
				$table->unsignedBigInteger('question_id');
				
                $table->foreign('disease_bank_id')
                    ->references('id')
                    ->on("disease_bank")
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
        Schema::dropIfExists('disease_bank');
		Schema::dropIfExists('disease_bank_questions');
    }
}
