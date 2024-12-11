<?php

use Idea\Base\BaseMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;


class AddForeignKeysToBaseTables extends BaseMigration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table(
            "user_roles",
            function (Blueprint $table) {
                $table->foreign('user_id')->references('id')->on("users")
                    ->onUpdate('CASCADE')->onDelete('CASCADE');
                $table->foreign('role_id')->references('id')->on("roles")
                    ->onUpdate('CASCADE')->onDelete('NO ACTION');
            }
        );

        Schema::table(
            "user_provider_tokens",
            function (Blueprint $table) {
                $table->foreign('user_id')->references('id')->on("users")
                    ->onUpdate('CASCADE')->onDelete('CASCADE');
            }
        );
        Schema::table(
            "user_notifications",
            function (Blueprint $table) {
                $table->foreign('user_id')->references('id')->on("users")
                    ->onUpdate('CASCADE')->onDelete('CASCADE');
                $table->foreign('target_id')->references('id')->on("users")
                    ->onUpdate('CASCADE')->onDelete('CASCADE');
            }
        );
        Schema::table(
            "devices",
            function (Blueprint $table) {
                $table->foreign('user_id')->references('id')->on("users")
                    ->onUpdate('CASCADE')->onDelete('CASCADE');
            }
        );
        Schema::table(
            "feedbacks",
            function (Blueprint $table) {
                $table->foreign('user_id')->references('id')->on("users")
                    ->onUpdate('CASCADE')->onDelete('CASCADE');
            }
        );
        Schema::table(
            "pages",
            function (Blueprint $table) {
                $table->foreign('parent_id')->references('id')->on("pages")
                    ->onUpdate('CASCADE')->onDelete('CASCADE');
            }
        );
        Schema::table(
            "role_permissions",
            function (Blueprint $table) {
                $table->foreign('permission_id')->references('id')->on("permissions")
                    ->onUpdate('CASCADE')->onDelete('NO ACTION');
                $table->foreign('role_id')->references('id')->on("roles")
                    ->onUpdate('CASCADE')->onDelete('NO ACTION');
            }
        );
        Schema::table(
            "profiles",
            function (Blueprint $table) {
                $table->foreign('user_id')->references('id')->on("users")
                    ->onUpdate('CASCADE')->onDelete('CASCADE');
            }
        );

        Schema::table(
            "role_permissions",
            function (Blueprint $table) {
                $table->foreign('action_id')->references('id')->on("actions")
                    ->onUpdate('CASCADE')->onDelete('NO ACTION');
            }
        );

        Schema::table(
            "push_notification_histories",
            function (Blueprint $table) {
                $table->foreign('target_id')->references('id')->on("users")
                    ->onUpdate('CASCADE')->onDelete('NO ACTION');
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
        Schema::table(
            "user_roles",
            function (Blueprint $table) {
                $table->dropForeign(['user_id']);
                $table->dropForeign(['role_id']);
            }
        );
        Schema::table(
            "user_provider_tokens",
            function (Blueprint $table) {
                $table->dropForeign(['user_id']);
            }
        );
        Schema::table(
            "user_notifications",
            function (Blueprint $table) {
                $table->dropForeign(['target_id']);
                $table->dropForeign(['user_id']);
            }
        );
        Schema::table(
            "devices",
            function (Blueprint $table) {
                $table->dropForeign(['user_id']);
            }
        );
        Schema::table(
            "configurations",
            function (Blueprint $table) {
                $table->dropForeign(['user_id']);
            }
        );
        Schema::table(
            "feedbacks",
            function (Blueprint $table) {
                $table->dropForeign(['user_id']);
            }
        );
        Schema::table(
            "pages",
            function (Blueprint $table) {
                $table->dropForeign(['parent_id']);
            }
        );
        Schema::table(
            "role_permissions",
            function (Blueprint $table) {
                $table->dropForeign(['permission_id']);
                $table->dropForeign(['role_id']);
            }
        );
        Schema::table(
            "profiles",
            function (Blueprint $table) {
                $table->dropForeign(['user_id']);
            }
        );
        Schema::table(
            "permissions",
            function (Blueprint $table) {
                $table->dropForeign(['action_id']);
            }
        );
        
        Schema::table(
            "push_notification_histories",
            function (Blueprint $table) {
                $table->dropForeign(['target_id']);
            }
        );
        
    }
}