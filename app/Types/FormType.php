<?php

namespace App\Types;

use App\Http\Controllers\WhoController;
use App\Models\Form;
use App\Models\QuestionGroup;

/**
 * Created by PhpStorm.
 * User: youssef.jradeh
 * Date: 5/24/18
 * Time: 1:19 AM
 */
class FormType extends WhoController
{

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public static function getForm($id)
    {
        $form = Form::with("types", "types.categories")->find($id);
        if (!$form) {
            return false;
        }

        if (!$form->types) {
            return $form;
        }

        foreach ($form->types as &$type) {
            if (!$type->categories) {
                continue;
            }
            foreach ($type->categories as &$category) {
                //get the root first
                $root = QuestionGroup::where('form_category_id', "=", $category->id)
                    ->whereNull("parent_group")
                    ->first();
                if (!$root) {
                    continue;
                }
                //then get all other children
                $category->groups = $root->descendantsAndSelf()
                    ->with(['questions', 'questions.options' => function ($query) {
						// the following will order the questions options with respective to the stored order_value
						$query->orderBy('order_value','ASC'); 
                    }, 'questions.responseType', 'conditions', 'questions.skipLogic.skipLogicDetails', 'questions.questionSettingOptions', 'questions.questionSettingAppearance', 'questions.questionAssignment'])
                    ->get()
                    ->toHierarchy();
            }
        }

        return $form;
    }
}
