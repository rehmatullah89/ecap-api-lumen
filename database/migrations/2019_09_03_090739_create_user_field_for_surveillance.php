<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateUserFieldForSurveillance extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users',
            function (Blueprint $table) {
                $table->string('reporting_agency', 100)->after('jwt_sign')->nullable();
				$table->integer('role_id')->after('reporting_agency')->nullable();
            });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users',
            function (Blueprint $table) {
                $table->dropColumn('reporting_agency');
				$table->dropColumn('role_id');
            });
    }
}
