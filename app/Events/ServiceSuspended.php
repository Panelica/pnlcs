<?php

namespace App\Events;

use App\Models\Service;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ServiceSuspended
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Service $service,
        public string $reason = ''
    ) {}
}
