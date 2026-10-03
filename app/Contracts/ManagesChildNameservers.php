<?php

namespace App\Contracts;

use App\Models\Domain;

/**
 * A registrar module that can manage a domain's own nameservers (glue
 * records: ns1.example.com and its address, registered at the registry).
 * Optional: without it the customer has no glue form.
 */
interface ManagesChildNameservers
{
    /**
     * The domain's child nameservers, or null when they cannot be read.
     *
     * @return list<array{host: string, ips: list<string>}>|null
     */
    public function getChildNameservers(Domain $domain): ?array;

    /**
     * Register a child nameserver, or change the addresses of one that exists.
     *
     * @param  list<string>  $ips
     * @return array{success: bool, message: string}
     */
    public function saveChildNameserver(Domain $domain, string $host, array $ips, bool $exists): array;

    /** @return array{success: bool, message: string} */
    public function deleteChildNameserver(Domain $domain, string $host): array;
}
