<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDistrictIdToTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasColumn('site_references', 'district_id')) {
            Schema::table('site_references', function (Blueprint $table) {
                $table->unsignedBigInteger('district_id')->nullable();
            });
        }

        if (!Schema::hasColumn('form_instances', 'district_id')) {
            Schema::table('form_instances', function (Blueprint $table) {
                $table->unsignedBigInteger('district_id')->after('governorate_id')->nullable()->default(0);
            });
        }

        if (!Schema::hasColumn('indicators_results', 'district_id')) {
            Schema::table('indicators_results', function (Blueprint $table) {
                $table->unsignedBigInteger('district_id')->after('governorate_id')->nullable()->default(0);
            });
        }

        if (!Schema::hasColumn('temp_form_instances', 'district_id')) {
            Schema::table('temp_form_instances', function (Blueprint $table) {
                $table->unsignedBigInteger('district_id')->after('governorate_id')->nullable()->default(0);
            });
        }

        if (!Schema::hasColumn('surveillance_locations', 'district_id')) {
            Schema::table('surveillance_locations', function (Blueprint $table) {
                $table->unsignedBigInteger('district_id');

                $table->foreign('district_id')
                    ->references('id')
                    ->on("districts")
                    ->onUpdate('CASCADE')
                    ->onDelete('NO ACTION');
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
        Schema::dropIfExists('push_to_mobile');
    }
}
