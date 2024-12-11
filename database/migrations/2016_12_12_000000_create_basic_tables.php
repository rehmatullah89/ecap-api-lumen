<?php

use Idea\Base\BaseMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBasicTables extends BaseMigration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create(
            'languages',
            function (Blueprint $table) {
                $table->increments('id');
                $table->string('title');
                $table->string('locale', 3);
                $table->timestamps();
            }
        );
        Schema::create(
            'devices',
            function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('type', 20);
                $table->string('version')->nullable();
                $table->string('uuid');
                $table->boolean('active')->default(true);
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('locale', 3)->default("en");
                $table->string('token')->nullable();
                $table->timestamp('last_access')->nullable();
                $table->timestamps();
            }
        );
        Schema::create(
            'users',
            function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('username', 100)->unique();
                $table->string('name', 100);
                $table->string('password', 60);
                $table->boolean('active')->default(false);
                $table->integer('role_id')->default(11);
                $table->string('email')->nullable();
                //used to make handle changing password or signing out from other devices
                $table->string('jwt_sign', 100)->nullable();
                // Email verification
                $table->string('email_confirm_code', 100)->nullable();
                $table->dateTime('email_confirm_expiry')->nullable();
                $table->dateTime('email_confirmed_at')->nullable();
                // Forgot password
                $table->string('password_change_code', 100)->nullable();
                $table->dateTime('password_change_expiry')->nullable();
                $table->dateTime('password_changed_at')->nullable();
                $table->softDeletes();
                $table->timestamps();
            }
        );
        Schema::create(
            'roles',
            function (Blueprint $table) {
                $table->increments('id');
                $table->string('slug', 50);
                $table->string('type', 20)->default("project");;
                $table->timestamps();
                $this->setMainTable($table);
            }
        );
        //create translate table for the roles table
        $this->translateMainTable();

        Schema::create(
            'user_roles',
            function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedBigInteger('user_id');
                $table->unsignedInteger('role_id');
                $table->timestamps();
            }
        );

        Schema::create(
            'user_notifications',
            function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('target_id')->nullable();
                $table->string('type', 100);
                $table->string('desc');
                $table->boolean('read')->default(false);
                $table->timestamps();
            }
        );
        Schema::create(
            'failed_jobs',
            function (Blueprint $table) {
                $table->increments('id');
                $table->text('connection');
                $table->text('queue');
                $table->longText('payload');
                $table->longText('exception');
                $table->timestamp('failed_at')->useCurrent();
            }
        );
        Schema::create(
            'user_provider_tokens',
            function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('user_id');
                $table->string('from', 50);
                $table->string('token_id');
                $table->text('token_value')->nullable();
                $table->dateTime('expiry_date')->nullable();
                $table->timestamps();
            }
        );
        Schema::create(
            'configurations',
            function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('value');
                $table->string('code')->unique();
                $table->string('text');
                $table->timestamps();
            }
        );
        Schema::create(
            'feedbacks',
            function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('user_id');
                $table->string('subject');
                $table->text('body');
                $table->timestamps();
            }
        );
        Schema::create(
            'pages',
            function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('parent_id')->nullable();
                $table->string('code')->nullable()->unique();
                $table->string('url')->nullable();
                $table->text('image')->nullable();
                $table->timestamps();

                $table->text('body')->nullable()->translate();
                $table->string('title')->translate();
                $this->setMainTable($table);
            }
        );
        $this->translateMainTable(true);

        Schema::create(
            'role_permissions',
            function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('permission_id');
                $table->unsignedInteger('role_id');
                $table->unsignedInteger('action_id')->nullable();
                $table->timestamps();
            }
        );

        Schema::create(
            'permissions',
            function (Blueprint $table) {
                $table->increments('id');
                $table->string('module');
                $table->string('name');
                $table->string('code');
                $table->timestamps();
            }
        );

        Schema::create(
            'actions',
            function (Blueprint $table) {
                $table->increments('id');
                $table->string('name');
                $table->timestamps();
            }
        );

        Schema::create(
            'profiles',
            function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('user_id')->unique();
                $table->string('first_name')->nullable();
	            $table->string('middle_name')->nullable(); // this exists in new databse, added by muhaammad abid 
	            $table->string('last_name')->nullable();
	            $table->string('phone')->nullable();				
	            $table->string('image')->nullable();
	            $table->enum('gender', array('male', 'female'))->nullable();
	            $table->date('dob')->nullable();
	            $table->integer('country_id')->nullable();
	            $table->text('description')->nullable();
	            $table->timestamps();
            }
        );

        Schema::create(
            'countries',
            function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('code');

                $table->string('name')->translate();
                $table->timestamps();
                $this->setMainTable($table);
            }
        );
        $this->translateMainTable(true);

        //push_notification_histories
        Schema::create(
            'push_notification_histories',
            function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('target_id')->nullable()->default(null);
                $table->string('devices', 20)->nullable()->default(null);
                $table->text('text')->default(null);
                $table->boolean('sent')->default('0');
                $table->timestamp('push_date')->default(null);
                $table->timestamps();

            }
        );
        //push_notification_topics
        Schema::create(
            'push_notification_topics',
            function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('name');
                $table->string('code');
                $table->timestamps();
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
        Schema::drop('languages');
        Schema::drop('devices');
        Schema::drop('users');
        Schema::drop('roles');
        Schema::drop('user_roles');
        Schema::drop('user_notifications');
        Schema::drop('failed_jobs');
        Schema::drop('user_provider_tokens');
        Schema::drop('configurations');
        Schema::drop('feedbacks');
        Schema::drop('pages');
        Schema::drop('countries');
        Schema::drop('profiles');
        Schema::drop('role_permissions');
        Schema::drop('permissions');
        Schema::drop('push_notification_histories');
    }
}