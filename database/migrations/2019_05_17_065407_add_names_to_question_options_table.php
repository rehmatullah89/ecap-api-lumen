<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddNamesToQuestionOptionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('question_options', function (Blueprint $table) {
            $table->renameColumn('name', 'name_en');
            $table->renameColumn('mobile_name', 'name_ar');
            $table->string('name_ku')->after('mobile_name')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('question_options', function (Blueprint $table) {
            $table->renameColumn('name_en', 'name');
            $table->renameColumn('name_ar', 'mobile_name');
            $table->dropColumn('name_ku');
        });
    }
}
