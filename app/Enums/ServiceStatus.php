<?php

namespace App\Enums;

enum ServiceStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Suspended = 'suspended';
    case Terminated = 'terminated';
    case Cancelled = 'cancelled';
    case Fraud = 'fraud';
    case Completed = 'completed';
}
