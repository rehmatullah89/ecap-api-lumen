<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateQuestionAssignmentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('question_assignments', function (Blueprint $table) {
			$table->unsignedBigInteger('project_id');
			$table->unsignedBigInteger('form_id');
			$table->unsignedBigInteger('question_id');
			$table->boolean('clinic')->default(FALSE);
			$table->boolean('laboratory')->default(FALSE);
			$table->boolean('verifier')->default(FALSE);
			$table->boolean('higher_verifier')->default(FALSE);
			$table->boolean('data_collector')->default(FALSE);
							  
			$table->timestamps();	
		});
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop("question_assignments");
    }
}
