<?php

namespace App\Contracts;

use App\Models\Domain;

/**
 * Optional capability: a registrar module that can read authoritative domain
 * state back from the registry (expiry, status, lock, nameservers).
 *
 * Kept separate from RegistrarModuleInterface so that existing and third-party
 * modules which cannot sync (e.g. the Manual registrar) stay valid.
 */
interface SyncsDomainData
{
    /**
     * Fetch the registrar's current view of a domain.
     *
     * @return array{
     *     success: bool,
     *     message?: string,
     *     expiry_date?: string|null,
     *     status?: string|null,
     *     locked?: bool|null,
     *     nameservers?: array<int, string>,
     *     id_protection?: bool|null
     * }
     *
     * id_protection is whether the registrar hides the registrant in public
     * WHOIS/RDAP. Leave it out (or null) when the registrar does not say.
     */
    public function syncDomain(Domain $domain): array;
}
