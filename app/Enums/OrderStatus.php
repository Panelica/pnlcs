<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Cancelled = 'cancelled';
    case Fraud = 'fraud';
}
