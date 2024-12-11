<?php

use Idea\Models\Country;
use Illuminate\Database\Seeder;

class SkipLogicSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
		
		$QuestionOperator =[
			['operator' => 'And', 		'description' => 'AND'],
			['operator' => 'Or', 		'description' => 'OR'],
			['operator' => 'And Not', 	'description' => 'AND NOT']
		];
		
		$OptionsOperator = [
			['operator' => '=',  'description' => 'Is'],
			['operator' => '!=', 'description' => 'Is not'],
			['operator' => '<>', 'description' => 'Contains'],
			['operator' => '!<>','description' => 'Does not Contain'],
			['operator' => '>',  'description' => 'Is greater than >'],
			['operator' => '>=', 'description' => 'Is at least >='],
			['operator' => '<',  'description' => 'Is less than <'],
			['operator' => '<=', 'description' => 'Is less than or equal to <='],
			['operator' => '=',  'description' => 'Is equal to ='],
			['operator' => '=/=',  'description' => 'Is not equal to =/=']
		];
		
		\App\Models\QuestionOperator::insert($QuestionOperator);
        \App\Models\OptionsOperator::insert($OptionsOperator);
    }
}
