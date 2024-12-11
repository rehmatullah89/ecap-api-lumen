<?php

use Idea\Base\BaseMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFormTables extends BaseMigration {
	
	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up() {
		//
		//		\App\Models\FormType::insert([
		//			['id' => '1', 'name' => 'Site'],
		//			['id' => '2', 'name' => 'Cluster'],
		//			['id' => '3', 'name' => 'HouseHold'],
		//			['id' => '4', 'name' => 'Individual'],
		//		]);
		//
		//Form
		Schema::create('forms', function (Blueprint $table) {
			$table->bigIncrements('id');
			$table->unsignedBigInteger('project_id');
			
			$table->foreign('project_id')
			      ->references('id')
			      ->on("projects")
			      ->onUpdate('CASCADE')
			      ->onDelete('NO ACTION');
			
			$table->timestamps();
			$table->softDeletes();//soft delete is better here
		});
		
		//Form Type  (ex : sites, cluster,HouseHold, individual)
		Schema::create('form_types', function (Blueprint $table) {
			$table->bigIncrements('id');
			$table->string('name');
			$table->unsignedBigInteger('form_id');
			
			$table->foreign('form_id')
			      ->references('id')
			      ->on("forms")
			      ->onUpdate('CASCADE')
			      ->onDelete('NO ACTION');
			
			$table->timestamps();
			$table->softDeletes();//soft delete is better here
		});
		
		
		//Form instance
		Schema::create('form_instances', function (Blueprint $table) {
			$table->bigIncrements('id');
			$table->unsignedBigInteger('project_id');
			$table->unsignedBigInteger('user_id');
			
			$table->foreign('user_id')
			      ->references('id')
			      ->on("users")
			      ->onUpdate('CASCADE')
			      ->onDelete('CASCADE');
			
			$table->foreign('project_id')
			      ->references('id')
			      ->on("projects")
			      ->onUpdate('CASCADE')
			      ->onDelete('NO ACTION');
			
			$table->timestamps();
			$table->softDeletes();//soft delete is better here
		});
		
		//Form Category ( relation : form type, project )   [ all question required, all question optional]
		Schema::create('form_categories', function (Blueprint $table) {
			$table->bigIncrements('id');
			$table->string('name');
			$table->string('mobile_name');
			$table->unsignedBigInteger('form_id');
			$table->unsignedBigInteger('form_type_id');
			$table->boolean('all_question_required')->default(FALSE);
			$table->boolean('all_question_optional')->default(FALSE);
			
			$table->foreign('form_id')
			      ->references('id')
			      ->on("forms")
			      ->onUpdate('CASCADE')
			      ->onDelete('NO ACTION');
			
			$table->foreign('form_type_id')
			      ->references('id')
			      ->on("form_types")
			      ->onUpdate('CASCADE')
			      ->onDelete('NO ACTION');
			
			$table->timestamps();
			$table->softDeletes();//soft delete is better here
		});
		
		//question group ( name , form , order, parent )
		Schema::create('question_groups', function (Blueprint $table) {
			$table->bigIncrements('id');
			$table->string('name');
			$table->unsignedBigInteger('parent_group')->nullable();
			$table->unsignedBigInteger('form_id');
			$table->unsignedBigInteger('form_type_id');
			$table->unsignedBigInteger('form_category_id');
			$table->integer('order_value')->default(0);
			$table->boolean('root_group')->default(FALSE);
			$table->unsignedBigInteger('lft')->nullable();
			$table->unsignedBigInteger('rgt')->nullable();
			$table->unsignedBigInteger('depth')->nullable();
			
			$table->foreign('parent_group')
			      ->references('id')
			      ->on("question_groups")
			      ->onUpdate('CASCADE')
			      ->onDelete('NO ACTION');
			
			$table->foreign('form_id')
			      ->references('id')
			      ->on("forms")
			      ->onUpdate('CASCADE')
			      ->onDelete('NO ACTION');
			
			$table->foreign('form_type_id')
			      ->references('id')
			      ->on("form_types")
			      ->onUpdate('CASCADE')
			      ->onDelete('NO ACTION');
			
			
			$table->foreign('form_category_id')
			      ->references('id')
			      ->on("form_categories")
			      ->onUpdate('CASCADE')
			      ->onDelete('NO ACTION');
			
			$table->timestamps();
			$table->softDeletes();//soft delete is better here
		});
		
		//Form Type  (ex : sites, cluster,HouseHold, individual)
		Schema::create('question_response_types', function (Blueprint $table) {
			$table->bigIncrements('id');
			$table->string('code');
			$table->string('name');
			$table->json('setting')->nullable();
			$table->timestamps();
			$table->softDeletes();//soft delete is better here
		});
		
		\App\Models\QuestionResponseType::insert([
			[
				'id'      => '1',
				'code'    => 'yes_no',
				'name'    => 'Yes/No',
				'setting' => NULL,
			],
			[
				'id'      => '2',
				'code'    => 'multiple_choice',
				'name'    => 'Multiple Choice',
				'setting' => '{"allow_multiple":true}',
			],
			[
				'id'      => '3',
				'code'    => 'open_response',
				'name'    => 'Open Response',
				'setting' => NULL,
			],
			[
				'id'      => '4',
				'code'    => 'number',
				'name'    => 'Number',
				'setting' => '{"minimum_value":true,"maximum_value":true}',
			],
			[
				'id'      => '5',
				'code'    => 'date',
				'name'    => 'Date',
				'setting' => '{"first_date":true,"last_date":true}',
			],
			[
				'id'      => '6',
				'code'    => 'currency',
				'name'    => 'Currency',
				'setting' => '{"currency_type":true,"minimum_value":true,"maximum_value":true}',
			],
			[
				'id'      => '7',
				'code'    => 'current_location',
				'name'    => 'Current Location',
				'setting' => NULL,
			],
			[
				'id'      => '8',
				'code'    => 'calculated_response',
				'name'    => 'Calculated Response',
				'setting' => '{"show_calculated_while_filling_form":true,"show_in_result_section":true,"allow_decimal_values":true}',
			],
			[
				'id'      => '9',
				'code'    => 'barcode',
				'name'    => 'Barcode',
				'setting' => NULL,
			],
			[
				'id'      => '10',
				'code'    => 'image',
				'name'    => 'Image',
				'setting' => NULL,
			],
			[
				'id'      => '11',
				'code'    => 'signature_capture',
				'name'    => 'Signature Capture',
				'setting' => NULL,
			],
			[
				'id'      => '12',
				'code'    => 'instructions',
				'name'    => 'Instructions',
				'setting' => NULL,
			],
		]);
		
		//question : Group , Label , Name, type , required , multiple , setting
		Schema::create('questions', function (Blueprint $table) {
			$table->bigIncrements('id');
			$table->string('name')->nullable();
			$table->string('mobile_name')->nullable();
			$table->string('label')->nullable();
			$table->string('mobile_label')->nullable();
			$table->text('consent')->nullable();
			$table->text('mobile_consent')->nullable();
			$table->boolean('required')->default(FALSE);
			$table->boolean('multiple')->default(FALSE);
			$table->unsignedBigInteger('question_group_id')->nullable();
			$table->unsignedBigInteger('form_id');
			$table->unsignedBigInteger('response_type_id');
			$table->unsignedInteger('order_value')->default(0);
			$table->json('setting')->nullable();
			
			$table->foreign('question_group_id')
			      ->references('id')
			      ->on("question_groups")
			      ->onUpdate('CASCADE')
			      ->onDelete('CASCADE');
			
			$table->foreign('response_type_id')
			      ->references('id')
			      ->on("question_response_types")
			      ->onUpdate('CASCADE')
			      ->onDelete('NO ACTION');
			
			$table->foreign('form_id')
			      ->references('id')
			      ->on("forms")
			      ->onUpdate('CASCADE')
			      ->onDelete('NO ACTION');
			
			$table->timestamps();
			$table->softDeletes();//soft delete is better here
		});
		
		//options
		Schema::create('question_options', function (Blueprint $table) {
			$table->bigIncrements('id');
			$table->string('name')->nullable();
			$table->string('mobile_name')->nullable();
			$table->unsignedBigInteger('question_id');
			$table->integer('order_value')->default(0);
			
			$table->foreign('question_id')
			      ->references('id')
			      ->on("questions")
			      ->onUpdate('CASCADE')
			      ->onDelete('CASCADE');
			$table->timestamps();
			$table->softDeletes();//soft delete is better here
		});
		
		//visibility
		Schema::create('question_group_conditions', function (Blueprint $table) {
			$table->bigIncrements('id');
			$table->unsignedBigInteger('question_group_id');
			$table->unsignedBigInteger('question_id');
			$table->integer('order_value')->default(0);
			$table->string('type')->equal("=");// = or > or >= or < or <=
			$table->string('value');//or value
			$table->string('max_value');
			$table->string('operation')->default("AND");
			
			$table->foreign('question_id')
			      ->references('id')
			      ->on("questions")
			      ->onUpdate('CASCADE')
			      ->onDelete('CASCADE');
			
			$table->foreign('question_group_id')
			      ->references('id')
			      ->on("question_groups")
			      ->onUpdate('CASCADE')
			      ->onDelete('CASCADE');
			
			$table->timestamps();
			$table->softDeletes();//soft delete is better here
		});
		
		//answers : Group , Label , Name, type , required , multiple , setting
		Schema::create('question_answers', function (Blueprint $table) {
			$table->bigIncrements('id');
			$table->unsignedBigInteger('form_instance_id');
			$table->unsignedBigInteger('response_type_id');
			$table->unsignedBigInteger('question_id');
			$table->text('value')->nullable();
			$table->boolean('multiple')->default(FALSE);
			$table->integer('individual_chunk')->default(0);
			
			$table->foreign('form_instance_id')
			      ->references('id')
			      ->on("form_instances")
			      ->onUpdate('CASCADE')
			      ->onDelete('NO ACTION');
			
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
	}
	
	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down() {
		Schema::drop('form_types');
		Schema::drop('forms');
		Schema::drop('form_categories');
		Schema::drop('form_instances');
		Schema::drop('question_groups');
		Schema::drop('question_response_types');
		Schema::drop('questions');
		Schema::drop('question_options');
		Schema::drop('question_conditions');
		Schema::drop('question_answers');
	}
}
