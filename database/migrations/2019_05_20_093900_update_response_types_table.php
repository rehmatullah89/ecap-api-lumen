<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\QuestionResponseType;

class UpdateResponseTypesTable extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up() {
        QuestionResponseType::insert([
            [
                'id' => '13',
                'code' => 'rating',
                'name' => 'Rating',
                'setting' => NULL,
            ],
            [
                'id' => '14',
                'code' => 'slider',
                'name' => 'Slider',
                'setting' => NULL,
            ],
            [
                'id' => '15',
                'code' => 'ranking',
                'name' => 'Ranking',
                'setting' => NULL,
            ],
            [
                'id' => '16',
                'code' => 'contact_info',
                'name' => 'Contact Info',
                'setting' => NULL,
            ],
            [
                'id' => '17',
                'code' => 'range',
                'name' => 'Range',
                'setting' => NULL,
            ],
            [
                'id' => '18',
                'code' => 'question_matrix',
                'name' => 'Question Matrix',
                'setting' => NULL,
            ],
            [
                'id' => '19',
                'code' => 'consent',
                'name' => 'Consent',
                'setting' => NULL,
            ],
			[
                'id' => '20',
                'code' => 'icd',
                'name' => 'ICD',
                'setting' => NULL,
            ]
        ]);

        QuestionResponseType::where('code', 'open_response')->update(['setting' => '{"size": true}']);
        QuestionResponseType::where('code', 'multiple_choice')->update(['setting' => '{
	"allow_multiple": true,
	"allow_dropdown":true
}']);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {
        //
    }
}
