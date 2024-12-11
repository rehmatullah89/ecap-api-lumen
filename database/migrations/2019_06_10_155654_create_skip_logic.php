<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSkipLogic extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {

        Schema::create('question_operators',
            function (Blueprint $table) {
                $table->increments('id');
                $table->string('operator')->nullable();
                $table->text('description')->nullable();
            });

        Schema::create('options_operators',
            function (Blueprint $table) {

                $table->increments('id');
                $table->string('operator')->nullable();
                $table->text('description')->nullable();
            });

        Schema::create('skip_logic_questions', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('form_id');
            $table->unsignedBigInteger('parent_id');
            $table->unsignedBigInteger('question_id');
            $table->unsignedBigInteger('operator_id');
            $table->unsignedBigInteger('condition_id');
            $table->timestamps();
            $table->softDeletes(); //soft delete is better here

        });

        Schema::create('skip_logic_question_details', function (Blueprint $table) {
			$table->increments('id');
			$table->unsignedBigInteger('skip_logic_id');
            $table->unsignedBigInteger('question_id');
            $table->unsignedBigInteger('parent_id');
            $table->unsignedBigInteger('operator_id');
            $table->unsignedBigInteger('option_value_id');
			$table->string('option_value')->nullable();
            $table->timestamps();
            $table->softDeletes(); //soft delete is better here

        });

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('skip_logic_questions');
        Schema::drop('question_operators');
        Schema::drop('options_operators');
        Schema::drop('skip_logic_question_details');
    }
}
