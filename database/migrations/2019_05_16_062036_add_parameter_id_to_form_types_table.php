<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddParameterIdToFormTypesTable extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up() {
        Schema::table('form_types', function (Blueprint $table) {
            $table->renameColumn('name', 'name_en');
            $table->string('name_ar')->after("name")->nullable();
            $table->string('name_ku')->after("name_ar")->nullable();
            $table->unsignedBigInteger('parameter_id')->after("id")->nullable();
            $table->boolean('loop')->after("allow_edit")->nullable();
            $table->integer('order')->default(1);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {
        Schema::table('form_types', function (Blueprint $table) {
            $table->renameColumn('name_en', 'name');
            $table->dropColumn('name_ar');
            $table->dropColumn('name_ku');
            $table->dropColumn('parameter_id');
            $table->dropColumn('loop');
        });
    }
}
