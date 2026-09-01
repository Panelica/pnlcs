<?php

namespace Modules\CompanyLookup\Contracts;

use Modules\CompanyLookup\CompanyData;

/**
 * A single registry source for company data. New providers (CEIDG, KRS, ...)
 * can be added later without touching CompanyLookupService — it consumes this
 * interface and merges whatever each provider returns.
 */
interface CompanyDataProviderInterface
{
    /**
     * Look a company up by NIP (already validated and normalised to 10 digits).
     *
     * Returns null when the registry knows nothing about the NIP. Providers
     * throw ProviderException for transport/format/rate problems, which the
     * service converts into per-source warnings instead of failing the whole
     * lookup.
     */
    public function findByNip(string $nip): ?CompanyData;
}
