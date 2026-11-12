<?php

namespace App\Support;

use App\Models\Setting;

/**
 * The endings the operator wants suggested, in order.
 *
 * One place reads the setting so the domain search and the front page cannot
 * disagree about which TLDs are promoted or in what order.
 */
class DomainSuggestions
{
    /** The built-in list used when the operator has not chosen one. */
    public const DEFAULT_SUGGESTIONS = '.com .net .org .io .co .dev .app .online .site .xyz';

    /**
     * The configured endings, in order, each with its leading dot.
     *
     * @return array<int, string>
     */
    public static function tlds(): array
    {
        $raw = trim((string) Setting::get('DomainSuggestionTlds', ''));
        $raw = $raw === '' ? self::DEFAULT_SUGGESTIONS : $raw;

        $tlds = [];
        foreach (preg_split('/[\s,]+/', strtolower($raw), -1, PREG_SPLIT_NO_EMPTY) as $tld) {
            $tlds[] = '.'.ltrim($tld, '.');
        }

        return array_values(array_unique($tlds));
    }

    /** How many suggestions to show next to a searched name. */
    public static function count(): int
    {
        $count = (int) Setting::get('DomainSuggestionCount', 6);

        return $count > 0 ? min($count, 12) : 6;
    }
}
