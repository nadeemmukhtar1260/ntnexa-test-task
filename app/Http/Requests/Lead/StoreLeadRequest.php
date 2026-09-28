<?php

namespace App\Http\Requests\Lead;

use Illuminate\Foundation\Http\FormRequest;

class StoreLeadRequest extends FormRequest
{
    /**
     * Access is already restricted by the auth:sanctum middleware.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = LeadRules::fields();

        foreach (['name', 'phone', 'source', 'status'] as $field) {
            array_unshift($rules[$field], 'required');
        }

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
