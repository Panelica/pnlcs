<?php

namespace App\Contracts;

use App\Models\Domain;

/**
 * A registrar module that can read and change a domain's WHOIS contacts.
 * Optional: without it the customer has no contact form.
 *
 * A contact is a flat array: first_name, last_name, company_name, email,
 * phone (international, +90 532...), address1, city, state, postcode and
 * country (two letters).
 */
interface ManagesDomainContacts
{
    /** The registrant contact the registry holds, or null when it cannot be read. */
    public function getContact(Domain $domain): ?array;

    /**
     * Set the contact for every role the registry keeps (registrant,
     * administrative, technical, billing).
     *
     * @return array{success: bool, message: string}
     */
    public function saveContact(Domain $domain, array $contact): array;
}
