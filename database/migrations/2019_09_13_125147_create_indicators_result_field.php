<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateIndicatorsResultField extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasColumn('indicators', 'results')) {
            Schema::table('indicators_results', function (Blueprint $table) {
                $table->decimal('results', 10, 8)->nullable();
            });
        }

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('indicators',
            function (Blueprint $table) {
                $table->dropColumn('results');
            });
    }
}
