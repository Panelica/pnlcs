<?php

namespace App\Support;

use DateTimeZone;

/**
 * The canonical timezone identifiers the panel can offer.
 *
 * display_tz() validates with timezone_identifiers_list(), so this list uses
 * the same canonical set rather than the larger ALL_WITH_BC alias list: an
 * alias accepted by the form but rejected by display_tz() would silently fall
 * back to UTC.
 */
final class Timezones
{
    /** @return array<int, string> */
    public static function all(): array
    {
        $zones = timezone_identifiers_list(DateTimeZone::ALL);
        sort($zones, SORT_STRING);

        return array_values($zones);
    }

    /**
     * @return array<string, array<int, string>> region => zones
     */
    public static function grouped(): array
    {
        $groups = [];

        foreach (self::all() as $zone) {
            $region = str_contains($zone, '/') ? explode('/', $zone, 2)[0] : 'UTC';
            $groups[$region][] = $zone;
        }

        ksort($groups);

        foreach ($groups as &$zones) {
            sort($zones, SORT_STRING);
        }
        unset($zones);

        return $groups;
    }
}
