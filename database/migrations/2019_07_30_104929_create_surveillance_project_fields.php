<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSurveillanceProjectFields extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up() {
        Schema::table('projects', function (Blueprint $table) {
            $table->enum('project_type', array('survey', 'surveillance'))->after("cloned_from")->default('survey');
            $table->unsignedBigInteger('created_by')->nullable();
        });
		
		Schema::table('roles', function (Blueprint $table) {
            $table->string('type', 10)->after("slug")->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropColumn('project_type');
            $table->dropColumn('created_by');
        });
		
		Schema::table('roles', function (Blueprint $table) {
             $table->dropColumn('type');
        });
    }
}
