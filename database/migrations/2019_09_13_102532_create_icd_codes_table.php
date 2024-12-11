<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateIcdCodesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
		if (!Schema::hasTable('icd_codes')){
           Schema::create(
				'icd_codes', function (Blueprint $table) {
					$table->bigIncrements('id');
					$table->string('code', 50);
					$table->string('disease_name', 250);
				}
			);
		}
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('icd_codes');
    }
}
