<?php

namespace App\Enums;

enum ClientStatus: string
{
    case Active = "active";
    case Inactive = "inactive";
    case Closed = "closed";
}
