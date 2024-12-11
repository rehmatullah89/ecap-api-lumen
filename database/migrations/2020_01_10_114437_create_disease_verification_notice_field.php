<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateDiseaseVerificationNoticeField extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('disease_verifications', function (Blueprint $table) {
            $table->char('notified', 3)->default('Yes')->after("verifier_instance_id")->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('disease_verifications', function (Blueprint $table) {
             $table->dropColumn('notified');
        });
    }
}
