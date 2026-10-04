<?php

namespace App\Events;

use App\Models\Domain;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** The domain passed its expiry date and moved to grace, redemption or expired ($status). */
class DomainExpired
{
    use Dispatchable, SerializesModels;

    public function __construct(public Domain $domain, public string $status) {}
}
