<?php

namespace App\Events;

use App\Models\Service;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ServiceUnsuspended
{
    use Dispatchable, SerializesModels;

    public function __construct(public Service $service) {}
}
