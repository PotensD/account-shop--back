<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGameInfoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'order' => 'nullable|integer',
            'name' => 'nullable|string',
            'description' => 'nullable|string',

            // Relationship rule
            'rule' => 'nullable|array',
            'rule.requiredRoleKeys' => 'nullable|array',
            'rule.requiredRoleKeys.*' => 'string',
        ];
    }
}
