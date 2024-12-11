<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class addMoreFieldsToForms extends Migration {
	
	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up() {
		Schema::table('form_instances',
			function (Blueprint $table) {
				
				$table->decimal('lat', 10, 8)->after("user_id")->nullable();
				$table->decimal('lng', 10, 8)->after("lat")->nullable();
				
				$table->unsignedBigInteger('site_id')->nullable()->after("lng");
				$table->unsignedBigInteger('cluster_id')
				      ->nullable()
				      ->after("site_id");
				
				$table->foreign('site_id')
				      ->references('id')
				      ->on("sites")
				      ->onUpdate('CASCADE')
				      ->onDelete('CASCADE');
				
				$table->foreign('cluster_id')
				      ->references('id')
				      ->on("clusters")
				      ->onUpdate('CASCADE')
				      ->onDelete('CASCADE');
			});
		
		Schema::table('form_types',
			function (Blueprint $table) {
				$table->boolean('allow_edit')
				      ->after("form_id")
				      ->default(TRUE)
				      ->nullable();
			});
	}
	
	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down() {
	}
}
