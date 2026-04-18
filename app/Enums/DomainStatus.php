<?php

namespace App\Enums;

enum DomainStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Grace = 'grace';
    case Redemption = 'redemption';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
    case Fraud = 'fraud';
    case TransferredAway = 'transferred_away';
}
