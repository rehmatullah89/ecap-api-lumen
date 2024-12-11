<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateProjectTypeFieldsForLoopableQuestion extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('form_types', function (Blueprint $table) {
            $table->integer('question_id')->after("loop")->nullable();
			$table->string('question_en', 150)->after("question_id")->nullable();
			$table->string('question_ar', 150)->after("question_en")->nullable();
			$table->string('question_ku', 150)->after("question_ar")->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
         Schema::table('form_types', function (Blueprint $table) {
            $table->dropColumn('question_id');
			$table->dropColumn('question_en');
			$table->dropColumn('question_ar');
			$table->dropColumn('question_ku');
        });
    }
}
