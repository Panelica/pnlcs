<?php

namespace App\Contracts;

/**
 * Optional capability: a registrar module that can ask about several domain
 * names in one request.
 *
 * The domain search asks about the name typed plus up to six suggested
 * endings. One call per name made the customer wait for seven round trips to
 * the registry, one after another; a registrar that takes a list answers them
 * together. Kept separate from RegistrarModuleInterface so modules that can
 * only ask one name at a time stay valid.
 */
interface ChecksAvailabilityInBulk
{
    /**
     * Ask the registry about every name at once.
     *
     * Keyed by the lower-cased domain name. A name the registry did not answer
     * for is left out rather than reported as taken - the caller falls back to
     * WHOIS for it. An empty array means the call failed altogether.
     *
     * @param  array<int, string>  $domains
     * @return array<string, array{available: bool}>
     */
    public function checkAvailabilityBulk(array $domains): array;
}
