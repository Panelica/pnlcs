<?php

namespace App\Support;

/**
 * The grace and redemption periods (days) a TLD's registry actually grants.
 *
 * The ICANN gTLD default is 5 days of renew grace and 30 of redemption, but
 * most ccTLDs have no renew grace — an expired name goes straight to a
 * redemption/quarantine — and a few (notably DENIC's .de) have no restore at
 * all. A registrar does not publish these, so the common ones are mapped here;
 * anything unmapped falls back to the gTLD default.
 */
trait MapsTldGracePeriods
{
    /**
     * @return array{0: int, 1: int} [grace days, redemption days]
     */
    protected function tldGracePeriod(string $extension): array
    {
        // DENIC .de: the name lapses straight away, with no restore period.
        if ($extension === 'de') {
            return [0, 0];
        }

        // NASK .pl (and its second-level spaces) and Nominet .uk: no renew
        // grace, a 30-day quarantine instead.
        if ($extension === 'pl' || str_ends_with($extension, '.pl') || str_ends_with($extension, '.uk')) {
            return [0, 30];
        }

        // European ccTLDs that also go straight to a 30-day redemption.
        $noGrace = ['eu', 'fr', 'it', 'nl', 'be', 'es', 'se', 'dk', 'no', 'fi', 'at', 'cz', 'ch', 'pt', 'ie'];
        if (in_array($extension, $noGrace, true)) {
            return [0, 30];
        }

        return [5, 30];
    }
}
