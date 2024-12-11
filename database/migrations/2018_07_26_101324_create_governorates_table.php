<?php

use Idea\Base\BaseMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGovernoratesTable extends BaseMigration {
	
	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up() {
		Schema::create('governorates', function (Blueprint $table) {
			$table->bigIncrements('id');
			$table->string('name')->nullable();
			
			$table->timestamps();
			$table->softDeletes();//soft delete is better here
		});
		
		Schema::table('sites',
			function (Blueprint $table) {
				
				$table->unsignedBigInteger('governorate_id')
				      ->nullable()
				      ->after("id");
				$table->foreign('governorate_id')
				      ->references('id')
				      ->on("governorates")
				      ->onUpdate('CASCADE')
				      ->onDelete('CASCADE');
			});
		
		\App\Models\Governorate::insert([
			['name' => 'Al Anbar'],
			['name' => 'Babil'],
			['name' => 'Baghdad'],
			['name' => 'Basra'],
			['name' => 'Dhi Qar	'],
			['name' => 'Al-Qādisiyyah'],
			['name' => 'Diyala'],
			['name' => 'Dohuk'],
			['name' => 'Erbil'],
			['name' => 'Halabja'],
			['name' => 'Karbala'],
			['name' => 'Kirkuk'],
			['name' => 'Maysan'],
			['name' => 'Muthanna'],
			['name' => 'Najaf'],
			['name' => 'Nineveh'],
			['name' => 'Saladin'],
			['name' => 'Sulaymaniyah'],
			['name' => 'Wasit']
		]);
	}
	
	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down() {
		Schema::drop('governorates');
	}
}
