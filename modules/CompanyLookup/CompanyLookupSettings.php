<?php

namespace Modules\CompanyLookup;

use App\Models\AddonSetting;

/**
 * Resolves the Company Lookup addon's effective configuration: built-in
 * defaults from config/company_lookup.php overlaid with operator values stored
 * in the generic addon settings store (addon = "company_lookup"). Nothing here
 * reads the environment; the GUS and CEIDG API keys live only in the database.
 */
final class CompanyLookupSettings
{
    /**
     * Config fields declared by the addon, rendered and saved by the addon
     * skeleton (AddonController / addon-output view).
     *
     * @return array<int, array{name: string, label: string, type: string, default?: mixed, hint?: string}>
     */
    public static function fields(): array
    {
        $d = config('company_lookup');

        return [
            [
                'name' => 'gus_api_key',
                'label' => __('messages.company_lookup.gus_api_key'),
                'type' => 'password',
                'hint' => __('messages.company_lookup.gus_api_key_hint'),
            ],
            ['name' => 'gus_endpoint', 'label' => __('messages.company_lookup.gus_endpoint'), 'type' => 'text', 'default' => $d['gus']['endpoint']],
            [
                'name' => 'ceidg_api_key',
                'label' => __('messages.company_lookup.ceidg_api_key'),
                'type' => 'password',
                'hint' => __('messages.company_lookup.ceidg_api_key_hint'),
            ],
            ['name' => 'ceidg_endpoint', 'label' => __('messages.company_lookup.ceidg_endpoint'), 'type' => 'text', 'default' => $d['ceidg']['endpoint']],
            [
                'name' => 'openbris_api_key',
                'label' => __('messages.company_lookup.openbris_api_key'),
                'type' => 'password',
                'hint' => __('messages.company_lookup.openbris_api_key_hint'),
            ],
            ['name' => 'openbris_endpoint', 'label' => __('messages.company_lookup.openbris_endpoint'), 'type' => 'text', 'default' => $d['openbris']['endpoint']],
            ['name' => 'mf_endpoint', 'label' => __('messages.company_lookup.mf_endpoint'), 'type' => 'text', 'default' => $d['mf']['endpoint']],
            ['name' => 'cache_ttl', 'label' => __('messages.company_lookup.cache_ttl'), 'type' => 'text', 'default' => $d['cache_ttl']],
            ['name' => 'connect_timeout', 'label' => __('messages.company_lookup.connect_timeout'), 'type' => 'text', 'default' => $d['http']['connect_timeout']],
            ['name' => 'request_timeout', 'label' => __('messages.company_lookup.request_timeout'), 'type' => 'text', 'default' => $d['http']['request_timeout']],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function resolve(): array
    {
        $defaults = config('company_lookup');
        $stored = AddonSetting::getForAddon('company_lookup');

        return [
            'gus' => [
                'key' => self::pick($stored, 'gus_api_key', $defaults['gus']['key'] ?? null),
                'endpoint' => self::pick($stored, 'gus_endpoint', $defaults['gus']['endpoint']),
            ],
            'mf' => [
                'endpoint' => self::pick($stored, 'mf_endpoint', $defaults['mf']['endpoint']),
            ],
            'ceidg' => [
                'key' => self::pick($stored, 'ceidg_api_key', $defaults['ceidg']['key'] ?? null),
                'endpoint' => self::pick($stored, 'ceidg_endpoint', $defaults['ceidg']['endpoint']),
            ],
            'openbris' => [
                'key' => self::pick($stored, 'openbris_api_key', $defaults['openbris']['key'] ?? null),
                'endpoint' => self::pick($stored, 'openbris_endpoint', $defaults['openbris']['endpoint']),
            ],
            'cache_ttl' => (int) self::pick($stored, 'cache_ttl', $defaults['cache_ttl']),
            'http' => [
                'connect_timeout' => (int) self::pick($stored, 'connect_timeout', $defaults['http']['connect_timeout']),
                'request_timeout' => (int) self::pick($stored, 'request_timeout', $defaults['http']['request_timeout']),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $stored
     */
    private static function pick(array $stored, string $key, mixed $default): mixed
    {
        $value = $stored[$key] ?? null;

        return ($value === null || $value === '') ? $default : $value;
    }
}
