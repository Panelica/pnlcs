<?php

namespace App\Support;

use App\Models\Setting;

/**
 * What a legally valid invoice needs from the buyer.
 *
 * One list, read from three places: Client::missingBillingIdentity(), which
 * finds the gap; the admin screens, which mark the gap; and the forms, which
 * need to know which field carries a star. Three copies would age apart - the
 * tax office field was added to the client once and never reached the admin
 * form, which is exactly how this class came to exist.
 *
 * The rules depend on where the seller is. A seller in Turkey has to identify
 * a company buyer by trade title, tax office and tax number and a private
 * buyer by national ID; elsewhere a tax number for companies is the common
 * case and nothing beyond the address for individuals.
 */
class BillingIdentity
{
    /** Asked of every customer, whatever their type. */
    public const COMMON = ['first_name', 'last_name', 'address1', 'city', 'country'];

    /** Field name to translation key. */
    private const LABELS = [
        'first_name' => 'common.form.first_name',
        'last_name' => 'common.form.last_name',
        'address1' => 'common.form.street_address',
        'city' => 'common.form.city',
        'country' => 'common.form.country',
        'phone_number' => 'client.form.phone',
        'client_type' => 'client.form.client_type',
        'company_name' => 'client.form.company_title',
        'tax_office' => 'client.form.tax_office',
        'tax_id' => 'common.form.tax_id',
        'national_id' => 'client.form.national_id',
    ];

    /** Whether the seller is subject to the Turkish invoicing rules. */
    public static function turkish(): bool
    {
        try {
            return strtoupper(trim((string) Setting::get('Country', ''))) === 'TR';
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * The fields required for the given customer type.
     *
     * With no type chosen only the common fields and the type itself are
     * asked: which identity is needed only becomes clear once the type is.
     *
     * @return array<int, string>
     */
    public static function required(?string $clientType): array
    {
        $fields = self::COMMON;

        if (self::turkish()) {
            $fields = array_merge($fields, ['phone_number', 'client_type']);

            if ($clientType === 'company') {
                return array_merge($fields, ['company_name', 'tax_office', 'tax_id']);
            }

            if ($clientType === 'individual') {
                return array_merge($fields, ['national_id']);
            }

            return $fields;
        }

        if ($clientType === 'company') {
            return array_merge($fields, ['company_name', 'tax_id']);
        }

        return $fields;
    }

    /** The readable name of one field; an unknown field comes back as is. */
    public static function label(string $field): string
    {
        return isset(self::LABELS[$field]) ? __(self::LABELS[$field]) : $field;
    }

    /**
     * Readable names for a list of fields.
     *
     * @param  array<int, string>  $fields
     * @return array<int, string>
     */
    public static function labels(array $fields): array
    {
        return array_map([self::class, 'label'], $fields);
    }
}
