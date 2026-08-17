<?php

namespace App\Support;

use App\Models\Client;
use App\Models\CustomField;

/**
 * Resolve a registrar field from a client using a manually mapped field name
 * or a list of auto-detected candidates.
 *
 * Checks the client's own attributes first, then its custom fields, so any
 * module can map PESEL / NIP / CSA (or whatever it needs) without knowing
 * where the panel stores them.
 */
trait MapsClientFields
{
    /**
     * Parse a JSON field map (or accept an already-decoded array). An empty or
     * invalid value yields an empty map, so callers can always iterate it.
     *
     * @param  array<string, string>|string|null  $map
     * @return array<string, string>
     */
    protected function fieldMap(array|string|null $map): array
    {
        if (is_array($map)) {
            return $map;
        }

        if (! is_string($map) || trim($map) === '') {
            return [];
        }

        $decoded = json_decode($map, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<int, string>  $autoDetect
     */
    protected function resolveClientField(Client $client, ?string $mappedField, array $autoDetect = []): ?string
    {
        $candidates = array_values(array_filter(array_merge($mappedField ? [$mappedField] : [], $autoDetect)));

        foreach ($candidates as $name) {
            $value = $this->clientAttribute($client, $name)
                ?? $this->clientCustomField($client, $name);

            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    protected function clientAttribute(Client $client, string $name): ?string
    {
        $value = $client->getAttribute($name);

        return ($value !== null && $value !== '') ? (string) $value : null;
    }

    protected function clientCustomField(Client $client, string $name): ?string
    {
        $field = CustomField::where('type', 'client')
            ->whereRaw('LOWER(field_name) = ?', [strtolower($name)])
            ->first();

        if (! $field) {
            return null;
        }

        $value = $field->valueFor($client->id);

        return ($value !== null && $value !== '') ? (string) $value : null;
    }
}
