<?php

namespace App\Http\Requests\Lead;

use App\Enums\LeadStatus;
use Illuminate\Validation\Rule;

/**
 * Field rules and messages shared by every request that writes a lead,
 * so the API, update and webhook endpoints can never drift apart.
 */
final class LeadRules
{
    /**
     * Accepts international formats such as +923001234567 or 0300-1234567.
     */
    public const PHONE_REGEX = '/^\+?[0-9][0-9\s\-()]{6,19}$/';

    /**
     * @return array<string, list<mixed>>
     */
    public static function fields(): array
    {
        return [
            'name' => ['string', 'max:150'],
            'phone' => ['string', 'max:20', 'regex:'.self::PHONE_REGEX],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'source' => ['string', 'max:50'],
            'status' => [Rule::enum(LeadStatus::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'phone.regex' => 'The phone must be a valid phone number, e.g. +923001234567.',
            'status.enum' => 'The status must be one of: '.implode(', ', LeadStatus::values()).'.',
        ];
    }
}
