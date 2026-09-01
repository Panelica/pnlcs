<?php

namespace Modules\Ksef;

use App\Models\AddonSetting;

/**
 * Resolves the KSeF addon's effective configuration: built-in defaults from
 * config/ksef.php overlaid with operator values stored in the generic addon
 * settings store (addon = "ksef").
 */
final class KsefSettings
{
    /**
     * Config fields declared by the addon, rendered and saved by the addon
     * skeleton (AddonController / addon-output view).
     *
     * @return array<int, array{name: string, label: string, type: string, default?: mixed, hint?: string}>
     */
    public static function fields(): array
    {
        $d = config('ksef');

        return [
            ['name' => 'nip', 'label' => __('messages.ksef.nip'), 'type' => 'text', 'hint' => __('messages.ksef.nip_hint')],
            ['name' => 'environment', 'label' => __('messages.ksef.environment'), 'type' => 'select', 'options' => ['integration' => 'Integracja', 'demo' => 'Demo', 'prod' => 'Prod'], 'default' => $d['environment']],
            ['name' => 'private_key', 'label' => __('messages.ksef.private_key'), 'type' => 'textarea', 'hint' => __('messages.ksef.private_key_hint')],
            ['name' => 'private_key_passphrase', 'label' => __('messages.ksef.private_key_passphrase'), 'type' => 'password', 'hint' => __('messages.ksef.private_key_passphrase_hint')],
            ['name' => 'certificate', 'label' => __('messages.ksef.certificate'), 'type' => 'textarea', 'hint' => __('messages.ksef.certificate_hint')],
            ['name' => 'connect_timeout', 'label' => __('messages.ksef.connect_timeout'), 'type' => 'text', 'default' => $d['http']['connect_timeout']],
            ['name' => 'request_timeout', 'label' => __('messages.ksef.request_timeout'), 'type' => 'text', 'default' => $d['http']['request_timeout']],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function resolve(): array
    {
        $defaults = config('ksef');
        $stored = AddonSetting::getForAddon('ksef');

        return [
            'nip' => self::pick($stored, 'nip', $defaults['nip']),
            'environment' => self::pick($stored, 'environment', $defaults['environment']),
            'private_key' => self::pick($stored, 'private_key', $defaults['private_key']),
            'private_key_passphrase' => self::pick($stored, 'private_key_passphrase', $defaults['private_key_passphrase']),
            'certificate' => self::pick($stored, 'certificate', $defaults['certificate']),
            'endpoint' => self::endpoint(self::pick($stored, 'environment', $defaults['environment'])),
            'http' => [
                'connect_timeout' => (int) self::pick($stored, 'connect_timeout', $defaults['http']['connect_timeout']),
                'request_timeout' => (int) self::pick($stored, 'request_timeout', $defaults['http']['request_timeout']),
            ],
        ];
    }

    /**
     * Whether the addon has enough configuration to talk to KSeF.
     */
    public static function configured(): bool
    {
        $s = self::resolve();

        return filled($s['nip']) && filled($s['private_key']) && filled($s['certificate']);
    }

    private static function endpoint(string $environment): string
    {
        return config('ksef.endpoints.'.$environment, config('ksef.endpoints.demo'));
    }

    private static function pick(array $stored, string $key, mixed $default): mixed
    {
        return array_key_exists($key, $stored) && $stored[$key] !== null && $stored[$key] !== ''
            ? $stored[$key]
            : $default;
    }
}
