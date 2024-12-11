<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatPushToMobileTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('push_to_mobile', function (Blueprint $table) {
			$table->bigIncrements('id');
			$table->unsignedBigInteger('project_id');
			$table->unsignedBigInteger('form_id');
			$table->integer('version')->nullable();
			$table->text('response_data')->nullable();
			$table->timestamps();				
			
			$table->index('id');
			$table->index('project_id');
		});
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('push_to_mobile');
    }
}
