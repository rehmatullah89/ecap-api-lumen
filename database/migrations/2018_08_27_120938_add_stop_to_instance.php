<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class addStopToInstance extends Migration {
	
	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up() {
		Schema::table('form_instances',
			function (Blueprint $table) {
				$table->timestamp('date_start')->nullable(); // this exists in new databse, added by muhaammad abid
				$table->timestamp('date_end')->nullable(); // this exists in new databse, added by muhaammad abid
				$table->string('old_lat_lng')->nullable(); // this exists in new databse, added by muhaammad abid
				$table->boolean('stopped')->default(FALSE)->after("date_end");
				$table->boolean('individual_count')->default(FALSE)
                    ->nullable()->after("stopped");
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
