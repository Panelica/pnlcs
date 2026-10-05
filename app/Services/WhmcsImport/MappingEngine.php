<?php

namespace App\Services\WhmcsImport;

/**
 * Turns a source row into a target row using a user-supplied mapping.
 *
 * A mapping is shaped:
 *
 *     [
 *         'columns'   => ['firstname' => 'first_name', ...], // source => target
 *         'constants' => ['country' => 'PL', ...],           // target => value
 *     ]
 *
 * Column mappings support many-to-one: when several source columns point at
 * the same target the non-empty values are joined, so `firstname + lastname`
 * become one name field. Constants fill a target outright. Both feed a pluggable
 * transform registry, so value transformations (today: the WHMCS status words)
 * are registered in one place and applied without touching the mapping flow.
 */
class MappingEngine
{
    /**
     * Target field => callable that rewrites the resolved value.
     *
     * @var array<string, callable(mixed): mixed>
     */
    protected array $transforms = [];

    public function __construct()
    {
        $this->register('status', fn ($value) => $this->statusValue($value));
    }

    /**
     * Register a value transform for a target field. Later importers register
     * their own here instead of special-casing values in the mapper.
     */
    public function register(string $field, callable $transform): void
    {
        $this->transforms[$field] = $transform;
    }

    /**
     * Suggest a PNLCS target for a WHMCS source column, or null when none fits.
     */
    public function suggest(string $sourceColumn): ?string
    {
        $key = strtolower(trim($sourceColumn));

        // Custom fields arrive namespaced as `custom:{fieldname}`.
        if (str_starts_with($key, 'custom:')) {
            $key = substr($key, 7);
        }

        $aliases = [
            'firstname' => 'first_name',
            'first_name' => 'first_name',
            'lastname' => 'last_name',
            'last_name' => 'last_name',
            'companyname' => 'company_name',
            'company_name' => 'company_name',
            'company' => 'company_name',
            'email' => 'email',
            'billing_email' => 'billing_email',
            'address1' => 'address1',
            'address_1' => 'address1',
            'address2' => 'address2',
            'address_2' => 'address2',
            'city' => 'city',
            'state' => 'state',
            'postcode' => 'postcode',
            'zip' => 'postcode',
            'zipcode' => 'postcode',
            'country' => 'country',
            'phonenumber' => 'phone_number',
            'phone' => 'phone_number',
            'phone_number' => 'phone_number',
            'tax_id' => 'tax_id',
            'taxid' => 'tax_id',
            'vat' => 'tax_id',
            'nip' => 'tax_id',
            'status' => 'status',
            'language' => 'language',
            'client_type' => 'client_type',
            'tax_office' => 'tax_office',
            'national_id' => 'national_id',
            'default_payment_method' => 'default_payment_method',
        ];

        if (isset($aliases[$key])) {
            return $aliases[$key];
        }

        // Substring hints for custom fields like "PESEL/NIP" or "Numer VAT".
        if (str_contains($key, 'nip') || str_contains($key, 'vat')) {
            return 'tax_id';
        }
        if (str_contains($key, 'pesel')) {
            return 'national_id';
        }

        return null;
    }

    /**
     * Apply the mapping to a single source row.
     *
     * @param  array<string, mixed>  $row
     * @param  array{columns?: array<string, string|null>, constants?: array<string, string>}  $mapping
     * @return array<string, mixed>
     */
    public function apply(array $row, array $mapping): array
    {
        $result = [];

        foreach (($mapping['constants'] ?? []) as $target => $value) {
            $result[$target] = $value;
        }

        // Group column mappings by target so several sources can feed one field.
        $grouped = [];
        foreach (($mapping['columns'] ?? []) as $source => $target) {
            if (! is_string($target) || $target === '') {
                continue;
            }
            $grouped[$target][] = $source;
        }

        foreach ($grouped as $target => $sources) {
            $values = [];
            foreach ($sources as $source) {
                $value = $row[$source] ?? null;
                if ($value !== null && trim((string) $value) !== '') {
                    $values[] = trim((string) $value);
                }
            }

            if ($values !== []) {
                $result[$target] = implode(' ', $values);
            }
        }

        foreach ($result as $field => $value) {
            if (isset($this->transforms[$field])) {
                $result[$field] = ($this->transforms[$field])($value);
            }
        }

        return $result;
    }

    /** Map WHMCS status words onto PNLCS's active/inactive/closed. */
    protected function statusValue(mixed $value): string
    {
        $map = [
            'active' => 'active',
            'inactive' => 'inactive',
            'suspended' => 'inactive',
            'closed' => 'closed',
        ];

        $normalized = strtolower(trim((string) $value));

        return $map[$normalized] ?? $normalized;
    }
}
