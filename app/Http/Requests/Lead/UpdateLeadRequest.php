<?php

namespace App\Http\Requests\Lead;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Every field is optional ("sometimes"), but when present it must be
     * valid and non-empty. This supports both PUT and PATCH semantics.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = LeadRules::fields();

        foreach (['name', 'phone', 'source', 'status'] as $field) {
            array_unshift($rules[$field], 'sometimes', 'required');
        }

        array_unshift($rules['email'], 'sometimes');

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return LeadRules::messages();
    }
}
