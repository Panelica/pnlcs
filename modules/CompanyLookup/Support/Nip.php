<?php

namespace Modules\CompanyLookup\Support;

/**
 * Polish NIP (tax identification number) validation.
 *
 * A NIP is ten digits. The first nine carry weights 6,5,7,2,3,4,5,6,7 and
 * the tenth is the checksum: (sum of digit*weight) mod 11. A remainder of 10
 * never appears on a real NIP.
 */
final class Nip
{
    private const WEIGHTS = [6, 5, 7, 2, 3, 4, 5, 6, 7];

    /**
     * Strip every separator (spaces, dashes, dots, slashes) leaving digits only.
     */
    public static function normalize(string $nip): string
    {
        $digits = preg_replace('/\D/', '', $nip);

        return $digits ?? '';
    }

    /**
     * Whether a raw, possibly formatted NIP is a valid NIP.
     */
    public static function isValid(string $nip): bool
    {
        return self::isValidDigits(self::normalize($nip));
    }

    /**
     * Whether an already-normalised (digits only) NIP is valid.
     */
    public static function isValidDigits(string $digits): bool
    {
        if (strlen($digits) !== 10 || ! ctype_digit($digits)) {
            return false;
        }

        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $sum += (int) $digits[$i] * self::WEIGHTS[$i];
        }

        $check = $sum % 11;
        if ($check === 10) {
            return false;
        }

        return $check === (int) $digits[9];
    }
}
