<?php

namespace App\Contracts;

use App\Models\Domain;

/**
 * A registrar module that can switch a domain's WHOIS privacy on and off.
 * Optional: without it the customer sees the setting but cannot change it.
 */
interface ManagesWhoisPrivacy
{
    /** @return array{success: bool, message: string} */
    public function setPrivacy(Domain $domain, bool $enabled): array;
}
