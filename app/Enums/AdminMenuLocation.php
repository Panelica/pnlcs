<?php

namespace App\Enums;

enum AdminMenuLocation: string
{
    case Clients = 'clients';
    case Orders = 'orders';
    case Billing = 'billing';
    case Support = 'support';
    case Utilities = 'utilities';
    case Extensions = 'extensions';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
