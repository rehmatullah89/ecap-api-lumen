<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSurveillanceCollectionTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('surveillance_form_instances', function (Blueprint $table) {
			$table->bigIncrements('id');
			$table->unsignedBigInteger('project_id');
			$table->unsignedBigInteger('user_id');
			$table->decimal('lat', 10, 8)->nullable();
			$table->decimal('lng', 10, 8)->nullable();
			$table->string('old_lat_lng')->nullable();
			$table->unsignedBigInteger('governorate_id')->default(0);
			$table->unsignedBigInteger('district_id')->default(0);		
			$table->unsignedBigInteger('site_id')->default(0);
			$table->unsignedBigInteger('cluster_id')->default(0);			
			$table->timestamp('date_start')->nullable();
			$table->timestamp('date_end')->nullable(); 
			$table->enum('instance_type', array('collection','verification'))->default('collection')->nullable();
			$table->string('instance_status', 20)->nullable();
			$table->unsignedBigInteger('disease_id')->default(0)->nullable();
			$table->boolean('individual_count')->nullable()->default(FALSE);
			$table->boolean('stopped')->nullable()->default(FALSE);
				
			$table->foreign('project_id')
			      ->references('id')
			      ->on("projects")
			      ->onUpdate('CASCADE')
			      ->onDelete('NO ACTION');
			
			$table->foreign('user_id')
			      ->references('id')
			      ->on("users")
			      ->onUpdate('CASCADE')
			      ->onDelete('CASCADE');
			
			/*$table->foreign('governorate_id')
				      ->references('id')
				      ->on("governorates")
				      ->onUpdate('CASCADE')
				      ->onDelete('CASCADE');
					  
			$table->foreign('district_id')
				      ->references('id')
				      ->on("districts")
				      ->onUpdate('CASCADE')
				      ->onDelete('CASCADE');		  
				  
			$table->foreign('site_id')
				      ->references('id')
				      ->on("site_references")
				      ->onUpdate('CASCADE')
				      ->onDelete('CASCADE');
				
			$table->foreign('cluster_id')
				      ->references('id')
				      ->on("cluster_references")
				      ->onUpdate('CASCADE')
				      ->onDelete('CASCADE');*/
			
			$table->timestamps();
			$table->softDeletes();//soft delete is better here
		});
		
		Schema::create('surveillance_question_answers', function (Blueprint $table) {
			$table->unsignedBigInteger('surveillance_form_instance_id');
			$table->unsignedBigInteger('project_id')->nullable();			
			$table->unsignedBigInteger('response_type_id');			
			$table->unsignedBigInteger('question_id');
			$table->text('value')->nullable();
			$table->boolean('multiple')->default(FALSE);
			$table->integer('individual_chunk')->default(0);
			
			$table->foreign('project_id')
				      ->references('id')
				      ->on("projects")
				      ->onUpdate('CASCADE')
				      ->onDelete('CASCADE');
			
			$table->foreign('question_id')
			      ->references('id')
			      ->on("questions")
			      ->onUpdate('CASCADE')
			      ->onDelete('NO ACTION');
			
			$table->foreign('response_type_id')
			      ->references('id')
			      ->on("question_response_types")
			      ->onUpdate('CASCADE')
			      ->onDelete('NO ACTION');
			
			$table->timestamps();
			$table->softDeletes();//soft delete is better here
		});
		
		Schema::create('disease_verifications', function (Blueprint $table) {
			$table->bigIncrements('id');
			$table->unsignedBigInteger('disease_id');
			$table->unsignedBigInteger('surveillance_form_instance_id');			
			$table->unsignedBigInteger('project_id')->nullable();	
			$table->unsignedBigInteger('user_id')->nullable();
			$table->enum('confirmation_level', array('DL','LL','CL','HL'))->default('DL')->nullable();
			$table->string('confirmation_status', 20)->nullable();	
			$table->timestamp('date_start')->nullable(); 			
			$table->timestamp('date_end')->nullable(); 			
			$table->unsignedBigInteger('confirmed_by')->nullable();		
			$table->unsignedBigInteger('verifier_instance_id')->nullable();			
			
			$table->foreign('project_id')
				      ->references('id')
				      ->on("projects")
				      ->onUpdate('CASCADE')
				      ->onDelete('CASCADE');
					  
			$table->foreign('surveillance_form_instance_id')
			      ->references('id')
			      ->on("surveillance_form_instances")
			      ->onUpdate('CASCADE')
			      ->onDelete('NO ACTION');
				  
			$table->foreign('user_id')
			      ->references('id')
			      ->on("users")
			      ->onUpdate('CASCADE')
			      ->onDelete('CASCADE');	  
			
			$table->timestamps();			
		});
		
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('surveillance_form_instances');
    }
}
