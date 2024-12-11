<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class addMoreFieldsToInstance extends Migration {
	
	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up() {
		Schema::table('question_answers',
			function (Blueprint $table) {
				
				$table->unsignedBigInteger('project_id')
				      ->nullable()
				      ->after("form_instance_id");
				$table->foreign('project_id')
				      ->references('id')
				      ->on("projects")
				      ->onUpdate('CASCADE')
				      ->onDelete('CASCADE');
			});
		
		$affected = DB::update('UPDATE question_answers qa SET qa.project_id=(SELECT fi.project_id FROM form_instances fi where fi.id=qa.form_instance_id)', []);
	}
	
	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down() {
	}
}
