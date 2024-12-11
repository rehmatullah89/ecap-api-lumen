<?php

use Idea\Base\BaseMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProjectTables extends BaseMigration
{
    
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up() 
    {
        
        // Create projects Table
        Schema::create(
            'projects', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('logo')->nullable();
                $table->string('frontend_color')->nullable();
                $table->string('frontend_icon')->nullable();
                $table->decimal('lat', 10, 8)->nullable();
                $table->decimal('lng', 10, 8)->nullable();
                $table->integer('goal')->nullable();
                $table->unsignedSmallInteger('status')->default(0);
                $table->timestamp('date_start')->nullable();
                $table->timestamp('date_end')->nullable();
                $table->timestamp('planned_date_start')->nullable();
                $table->timestamp('planned_date_end')->nullable();
                $table->unsignedBigInteger('cloned_from')->nullable();
            
                $table->foreign('cloned_from')
                    ->references('id')
                    ->on("projects")
                    ->onUpdate('CASCADE')
                    ->onDelete('NO ACTION');
            
                $table->timestamps();
                $table->softDeletes();//soft delete is better here
            }
        );
        
        // Create sites Table
        Schema::create(
            'sites', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('project_id');
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('frontend_color')->nullable();
                $table->string('frontend_icon')->nullable();
                $table->decimal('lat', 10, 8)->nullable();
                $table->decimal('lng', 10, 8)->nullable();
                $table->unsignedSmallInteger('status')->default(0);
            
                $table->foreign('project_id')
                    ->references('id')
                    ->on("projects")
                    ->onUpdate('CASCADE')
                    ->onDelete('NO ACTION');
            
                $table->timestamps();
                $table->softDeletes();//soft delete is better here
            }
        );
        
        // Create clusters Table
        Schema::create(
            'clusters', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('project_id');
                $table->unsignedBigInteger('site_id');
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('frontend_color')->nullable();
                $table->string('frontend_icon')->nullable();
                $table->decimal('lat', 10, 8)->nullable();
                $table->decimal('lng', 10, 8)->nullable();
            
                $table->foreign('project_id')
                    ->references('id')
                    ->on("projects")
                    ->onUpdate('CASCADE')
                    ->onDelete('NO ACTION');
                $table->foreign('site_id')
                    ->references('id')
                    ->on("sites")
                    ->onUpdate('CASCADE')
                    ->onDelete('NO ACTION');
            
                $table->timestamps();
                $table->softDeletes();//soft delete is better here
            }
        );
        
        // Create teams Table
        Schema::create(
            'teams', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('name');
                $table->unsignedBigInteger('project_id');
                $table->unsignedBigInteger('site_id');
            
                $table->foreign('project_id')
                    ->references('id')
                    ->on("projects")
                    ->onUpdate('CASCADE')
                    ->onDelete('NO ACTION');
            
                $table->foreign('site_id')
                    ->references('id')
                    ->on("sites")
                    ->onUpdate('CASCADE')
                    ->onDelete('NO ACTION');
            
                $table->timestamps();
                $table->softDeletes();//soft delete is better here
            }
        );
        
        // Create team_clusters
        Schema::create(
            'team_clusters', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('team_id');
                $table->unsignedBigInteger('cluster_id');
            
                $table->foreign('team_id')
                    ->references('id')
                    ->on("teams")
                    ->onUpdate('CASCADE')
                    ->onDelete('NO ACTION');
            
                $table->foreign('cluster_id')
                    ->references('id')
                    ->on("clusters")
                    ->onUpdate('CASCADE')
                    ->onDelete('NO ACTION');
            
                $table->timestamps();
                $table->softDeletes();//soft delete is better here
            }
        );
        
        // Create indicator
        Schema::create(
            'indicators', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('project_id');
                $table->string('name');
                $table->string('description');
                $table->decimal('upper_threshold', 65, 6)->nullable();
                $table->decimal('lower_threshold', 65, 6)->nullable();
                $table->text('arithmetic')->nullable();
                $table->string('result_type')->default("number");
                $table->boolean('show_on_project_summary')->default(true);
                $table->boolean('show_on_site_summary')->default(true);
            
                $table->foreign('project_id')
                    ->references('id')
                    ->on("projects")
                    ->onUpdate('CASCADE')
                    ->onDelete('CASCADE');
            
                $table->timestamps();
                $table->softDeletes();//soft delete is better here
            }
        );
        
        // Create project_members
        Schema::create(
            'project_members', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('project_id');
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('team_id')->nullable();//only for collectors
            
                $table->foreign('team_id')
                    ->references('id')
                    ->on("teams")
                    ->onUpdate('CASCADE')
                    ->onDelete('NO ACTION');
            
                $table->foreign('project_id')
                    ->references('id')
                    ->on("projects")
                    ->onUpdate('CASCADE')
                    ->onDelete('CASCADE');
            
                $table->foreign('user_id')
                    ->references('id')
                    ->on("users")
                    ->onUpdate('CASCADE')
                    ->onDelete('CASCADE');
            
                $table->timestamps();
                $table->softDeletes();//soft delete is better here
            }
        );
        
        // Create guest_sites
        Schema::create(
            'guest_sites', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('site_id');
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('project_id');
            
                $table->foreign('project_id')
                    ->references('id')
                    ->on("projects")
                    ->onUpdate('CASCADE')
                    ->onDelete('CASCADE');
            
                $table->foreign('site_id')
                    ->references('id')
                    ->on("sites")
                    ->onUpdate('CASCADE')
                    ->onDelete('CASCADE');
            
                $table->foreign('user_id')
                    ->references('id')
                    ->on("users")
                    ->onUpdate('CASCADE')
                    ->onDelete('CASCADE');
            
                $table->timestamps();
                $table->softDeletes();//soft delete is better here
            }
        );
        //Form Type  (ex : sites, cluster,HouseHold, individual)
        //Form Category ( relation : form type, project )   [ all question required, all question optional]
        
        //question group ( name , form , order, parent )
        
        //question : Group , Label , Name, type , required , multiple , setting
        
        //answers :
        
        //visibility
        
        
    }
    
    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() 
    {
        Schema::drop('projects');
        Schema::drop('sites');
        Schema::drop('groups');
        Schema::drop('teams');
    }
}
