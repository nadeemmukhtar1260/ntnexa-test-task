<?php

namespace App\Enums;

/**
 * The lifecycle stages a lead can be in.
 *
 * A backed enum gives us one source of truth that is reused by the
 * migration, the Eloquent cast and the validation rules.
 */
enum LeadStatus: string
{
    case New = 'new';
    case Contacted = 'contacted';
    case Qualified = 'qualified';
    case Closed = 'closed';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
