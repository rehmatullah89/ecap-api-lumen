<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAppVersionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // app versions
        Schema::create(
            'app_versions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('version');
            $table->enum('device_type', array('android', 'ios'))->default('ios');
            $table->enum('update_type', array('major', 'minor'))->default('minor');
            $table->integer('active')->unsigned()->default(1);
            $table->timestamps();
            $table->softDeletes();
        }
        );
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('app_versions');
    }
}
