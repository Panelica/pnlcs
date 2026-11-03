<?php

namespace App\Support;

use App\Models\Setting;

/**
 * The pricing category a TLD belongs to: the operator's own country TLD
 * ("local"), another country's ccTLD ("country"), one of the legacy gTLDs
 * ("generic") or a post-2012 gTLD ("new").
 */
trait MapsTldCategories
{
    /** The generic gTLDs that predate the 2012 gTLD expansion. */
    private const LEGACY_GTLDS = [
        'com', 'net', 'org', 'info', 'biz', 'name', 'pro', 'mobi', 'tel',
        'travel', 'asia', 'cat', 'jobs', 'aero', 'museum', 'coop', 'xxx',
    ];

    protected function tldCategory(string $extension): string
    {
        // No default country: an installation that has not set one must not
        // have another country's extensions called its own.
        $local = strtolower(trim((string) Setting::get('Country', '')));

        // The operator's own country TLD and its second-level spaces.
        if ($local !== '' && ($extension === $local || str_ends_with($extension, '.'.$local))) {
            return 'local';
        }

        // A ccTLD, or a country second-level domain: the last label is 2 letters.
        $last = str_contains($extension, '.')
            ? substr($extension, strrpos($extension, '.') + 1)
            : $extension;

        if (strlen($last) === 2) {
            return 'country';
        }

        if (in_array($extension, self::LEGACY_GTLDS, true)) {
            return 'generic';
        }

        return 'new';
    }
}
