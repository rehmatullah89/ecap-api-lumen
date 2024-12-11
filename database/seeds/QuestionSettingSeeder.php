<?php

use Idea\Models\Role;
use Illuminate\Database\Seeder;

class QuestionSettingSeeder extends Seeder
{
    
    /**
     * Run the database seeds.
     *
     * @return void
     *
     * insert user role
     */
    public function run() 
    {
        $items = [
            ['id' => 1, 'setting' => '{
    "icon": "fa fa-check fa-lg"
}'],
            ['id' => 2, 'setting' => '{
    "icon": "fa fa-list fa-lg"
}'],
            ['id' => 3, 'setting' => '{
    "icon": "fa fa-reply fa-lg",
    "size": true
}'],
            ['id' => 4, 'setting' => '{
    "icon": "fa fa-list-ol fa-lg",
    "size": true
}'],
            ['id' => 5, 'setting' => '{
    "icon": "fa fa-calendar fa-lg",
    "size": true
}'],
            ['id' => 6, 'setting' => '{
    "icon": "fa fa-dollar fa-lg",
    "size": true
}'],
            ['id' => 7, 'setting' => '{
    "icon": "fa fa-map-marker fa-2x"
}'],
            ['id' => 8, 'setting' => '{
    "icon": "fa fa-calculator  fa-lg"
}'],
            ['id' => 9, 'setting' => '{
    "icon": "fa fa-barcode  fa-lg"
}'],
            ['id' => 10, 'setting' => '{
    "icon": "fa fa-image  fa-lg"
}'],
            ['id' => 11, 'setting' => '{
    "icon": "fa fa-edit  fa-lg"
}'],
            ['id' => 12, 'setting' => '{
    "icon": "fa fa-star  fa-lg"
}'],
            ['id' => 13, 'setting' => '{
    "icon": "fa fa-star  fa-lg"
}'],
            ['id' => 14, 'setting' => '{
    "icon": "fa fa-sliders  fa-lg"
}'],
            ['id' => 15, 'setting' => '{
    "icon": "fa fa-thumbs-up  fa-lg"
}'],
            ['id' => 16, 'setting' => '{
    "icon": "fa fa-envelope fa-lg"
}'],
            ['id' => 17, 'setting' => '{
    "icon": "fa fa-exchange  fa-lg"
}'],
            ['id' => 18, 'setting' => '{
    "icon": "fa fa-question-circle  fa-lg"
}'],
            ['id' => 19, 'setting' => '{
    "icon": "fa fa-text-height  fa-lg"
}'],
            ['id' => 20, 'setting' => '{
    "icon": "fa fa-code fa-lg"
}', 'code'=>'icd', 'name'=>'ICD'],
	];
        
        foreach ($items as $item) {
            \App\Models\QuestionResponseType::updateOrCreate(['id' => $item['id']], $item);
        }
    }
}