<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddNamesFieldsToQuestionsTable extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up() {
        Schema::table('questions', function (Blueprint $table) {
            $table->renameColumn('name', 'name_en');
            $table->renameColumn('mobile_name', 'name_ar');
            $table->renameColumn('label', 'name_ku');
            $table->renameColumn('mobile_label', 'question_code');
            $table->renameColumn('order_value', 'order');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {
        Schema::table('questions', function (Blueprint $table) {
            $table->renameColumn('name_en', 'name');
            $table->renameColumn('name_ar', 'mobile_name');
            $table->renameColumn('name_ku', 'label');
            $table->renameColumn('question_code', 'mobile_label');
            $table->renameColumn('order', 'order_value');
        });
    }
}
