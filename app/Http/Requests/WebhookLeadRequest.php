<?php

namespace App\Http\Requests;

use App\Http\Requests\Lead\LeadRules;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Payload sent by external systems (website forms, ad platforms, etc.).
 *
 * The status is not accepted from the caller: every lead arriving through
 * the webhook starts as "new". The source defaults to "webhook".
 */
class WebhookLeadRequest extends FormRequest
{
    /**
     * The caller is authenticated by the webhook.signature middleware.
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
        unset($rules['status']);

        array_unshift($rules['name'], 'required');
        array_unshift($rules['phone'], 'required');
        array_unshift($rules['source'], 'sometimes', 'required');

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
