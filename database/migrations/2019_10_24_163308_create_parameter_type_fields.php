<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateParameterTypeFields extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('parameters', function (Blueprint $table) {
            $table->enum('parameter_type', array('collection','verification'))->after("name_ku")->default('collection')->nullable();
			$table->enum('parameter_level', array('verifier','laboratory','clinic','higher_verification'))->after("parameter_type")->nullable();
        });
		
		Schema::table('form_types', function (Blueprint $table) {
            $table->enum('parameter_type', array('collection','verification'))->after("allow_edit")->default('collection')->nullable();
			$table->enum('parameter_level', array('verifier','laboratory','clinic','higher_verification'))->after("parameter_type")->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('parameters', function (Blueprint $table) {
             $table->dropColumn('parameter_type');
			 $table->dropColumn('parameter_level');
        });
		
		Schema::table('form_types', function (Blueprint $table) {
             $table->dropColumn('parameter_type');
			 $table->dropColumn('parameter_level');
        });
    }
}
