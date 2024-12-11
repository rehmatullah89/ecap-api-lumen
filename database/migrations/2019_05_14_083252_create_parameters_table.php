<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateParametersTable extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up() {
        Schema::create('parameters', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('project_id');
            $table->string('name_ar')->nullable();
            $table->string('name_en')->nullable();
            $table->string('name_ku')->nullable();
			$table->string('question_en')->nullable();
			$table->string('question_ar')->nullable();
			$table->string('question_ku')->nullable();
            $table->boolean('allow_edit')->default(0);
            $table->boolean('loop')->default(0);
            $table->integer('order')->default(1);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {
        Schema::dropIfExists('parameters');
    }
}
