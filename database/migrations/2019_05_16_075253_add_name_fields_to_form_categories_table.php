<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddNameFieldsToFormCategoriesTable extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up() {
        Schema::table('form_categories', function (Blueprint $table) {
            $table->renameColumn('name', 'name_en');
            $table->renameColumn('mobile_name', 'name_ar');
            $table->string('name_ku')->after("mobile_name")->nullable(0);
            $table->integer('order')->default(1);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {
        Schema::table('form_categories', function (Blueprint $table) {
            $table->renameColumn('name_en', 'name');
            $table->renameColumn('name_ar', 'mobile_name');
            $table->dropColumn('name_ku');
            $table->dropColumn('order');
        });
    }
}
