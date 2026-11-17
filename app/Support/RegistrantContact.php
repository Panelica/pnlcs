<?php

namespace App\Support;

/**
 * The registrant details a registrar is sent come from the customer, never
 * from a placeholder. A domain registered under "Admin, 123 Main St,
 * Anytown" belongs on paper to nobody, breaks the registry's accuracy rules
 * and can cost the customer the domain - so a missing detail stops the
 * registration and says which one, and the admin can retry once the
 * customer's profile is complete.
 */
class RegistrantContact
{
    /** Details every registrar here needs. State and postcode do not exist in every country. */
    public const REQUIRED = ['firstname', 'lastname', 'email', 'phone', 'address', 'city', 'country'];

    /**
     * The required details that are empty.
     *
     * @param  array<string, mixed>  $contact
     * @return list<string>
     */
    public static function missing(array $contact): array
    {
        return array_values(array_filter(
            self::REQUIRED,
            fn (string $field) => trim((string) ($contact[$field] ?? '')) === ''
        ));
    }

    /** @param  list<string>  $missing */
    public static function message(array $missing): string
    {
        return 'The registrant details are incomplete (missing: '.implode(', ', $missing)
            .'). Complete the client\'s profile and register the domain again.';
    }
}
