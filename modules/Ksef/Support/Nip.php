<?php

namespace Modules\Ksef\Support;

/**
 * Normalise a Polish NIP for KSeF.
 *
 * KSeF expects the ten digits, without the PL prefix or separators. The
 * prefix is common in VAT ids copied from invoices and must never be sent as
 * part of <Nip>/<NIP>.
 */
final class Nip
{
    public static function normalize(?string $value): string
    {
        return (string) preg_replace('/[^0-9]/', '', (string) $value);
    }

    public static function isValid(?string $value): bool
    {
        $nip = self::normalize($value);

        if (strlen($nip) !== 10) {
            return false;
        }

        $weights = [6, 5, 7, 2, 3, 4, 5, 6, 7];
        $sum = 0;

        for ($i = 0; $i < 9; $i++) {
            $sum += (int) $nip[$i] * $weights[$i];
        }

        $checksum = $sum % 11;

        return $checksum !== 10 && $checksum === (int) $nip[9];
    }
}
