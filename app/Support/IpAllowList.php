<?php

namespace App\Support;

/**
 * The addresses an API credential may be used from.
 *
 * ApiKeyAuth has always enforced a credential's allowed_ips, but nothing could
 * set them: the credential screen had no field for it and neither had the API.
 * Both now read the list through here, so they accept the same thing - IPv4 or
 * IPv6 addresses and CIDR ranges, separated by commas, spaces or new lines -
 * and ApiKeyAuth is never handed an entry it cannot match against.
 */
class IpAllowList
{
    /** More would be a list nobody reads; a range covers a network. */
    public const MAX_ENTRIES = 100;

    /**
     * The list, or the first entry that is not an address or a range.
     *
     * @return array{0: ?array<int, string>, 1: ?string} [entries or null, bad entry or null]
     */
    public static function parse(mixed $input): array
    {
        $entries = is_array($input)
            ? $input
            : preg_split('/[\s,]+/', (string) $input, -1, PREG_SPLIT_NO_EMPTY);

        $list = [];
        foreach ($entries as $entry) {
            $entry = trim((string) $entry);
            if ($entry === '') {
                continue;
            }
            if (! self::valid($entry)) {
                return [null, $entry];
            }
            $list[] = $entry;
        }

        $list = array_values(array_unique($list));

        if (count($list) > self::MAX_ENTRIES) {
            return [null, $list[self::MAX_ENTRIES]];
        }

        return [$list, null];
    }

    private static function valid(string $entry): bool
    {
        if (filter_var($entry, FILTER_VALIDATE_IP) !== false) {
            return true;
        }

        if (! preg_match('#^([^/]+)/(\d{1,3})$#', $entry, $m) || filter_var($m[1], FILTER_VALIDATE_IP) === false) {
            return false;
        }

        $max = filter_var($m[1], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false ? 32 : 128;

        return (int) $m[2] <= $max;
    }
}
