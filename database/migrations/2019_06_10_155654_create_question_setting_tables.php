<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateQuestionSettingTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {

        Schema::create('question_setting_options',
            function (Blueprint $table) {
				$table->unsignedBigInteger('project_id');
				$table->unsignedBigInteger('form_id');
				$table->unsignedBigInteger('question_id');
				$table->string('guide_en')->nullable();
				$table->string('guide_ar')->nullable();
				$table->string('guide_ku')->nullable();
				$table->string('note_en')->nullable();
				$table->string('note_ar')->nullable();
				$table->string('note_ku')->nullable();
				
				$table->foreign('project_id')
			      ->references('id')
			      ->on("projects")
			      ->onUpdate('CASCADE')
			      ->onDelete('NO ACTION');
				  
				$table->foreign('form_id')
			      ->references('id')
			      ->on("forms")
			      ->onUpdate('CASCADE')
			      ->onDelete('NO ACTION');  
				  
				$table->foreign('question_id')
			      ->references('id')
			      ->on("questions")
			      ->onUpdate('CASCADE')
			      ->onDelete('NO ACTION');    
			
				$table->timestamps();
				$table->softDeletes();//soft delete is better here
			
            });

        Schema::create('question_setting_appearance',
            function (Blueprint $table) {
				$table->unsignedBigInteger('project_id');
				$table->unsignedBigInteger('form_id');
				$table->unsignedBigInteger('question_id');
				$table->string('font')->nullable();
				$table->string('color')->nullable();
				$table->string('highlight')->nullable();
				$table->enum('positioning', ['vertical', 'horizontal'])->nullable();
				$table->enum('capitalization', ['lowercase', 'uppercase', 'titlecase'])->nullable();
				$table->enum('font_style', ['bold', 'italic', 'underline', 'crossed'])->nullable();
				
				$table->foreign('project_id')
			      ->references('id')
			      ->on("projects")
			      ->onUpdate('CASCADE')
			      ->onDelete('NO ACTION');
				  
				$table->foreign('form_id')
			      ->references('id')
			      ->on("forms")
			      ->onUpdate('CASCADE')
			      ->onDelete('NO ACTION');  
				  
				$table->foreign('question_id')
			      ->references('id')
			      ->on("questions")
			      ->onUpdate('CASCADE')
			      ->onDelete('NO ACTION');    
			
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
        Schema::drop('question_setting_options');
        Schema::drop('question_setting_appearance');
    }
}
