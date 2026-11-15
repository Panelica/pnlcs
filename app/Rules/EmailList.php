<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * One or more email addresses, separated by commas or semicolons.
 *
 * The copy-to fields are read that way where mail is sent
 * (ApplyEmailTemplate), which skips what is not an address without a word -
 * so a typo has to be refused here, where the operator can still see it.
 */
class EmailList implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        foreach (preg_split('/[,;]/', (string) $value) ?: [] as $address) {
            $address = trim($address);

            if ($address !== '' && filter_var($address, FILTER_VALIDATE_EMAIL) === false) {
                $fail(__('validation.email'));

                return;
            }
        }
    }
}
