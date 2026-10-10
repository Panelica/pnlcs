<?php

namespace Modules\Servers\AaPanel;

/** Strict API numbers: no booleans, fractions, exponents, overflow or non-finite values. */
final class AaPanelValue
{
    public static function integer(mixed $value, int $minimum = 0): int
    {
        if (! is_int($value) && ! is_string($value)) {
            throw new AaPanelException('aaPanel returned an invalid integer value.');
        }
        $number = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => $minimum]]);
        if ($number === false) {
            throw new AaPanelException('aaPanel returned an invalid integer value.');
        }

        return $number;
    }
}
