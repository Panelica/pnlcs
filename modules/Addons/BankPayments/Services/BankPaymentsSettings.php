<?php

namespace Modules\Addons\BankPayments\Services;

use App\Models\AddonSetting;

/**
 * Resolves the BankPayments addon's effective settings.
 *
 * The addon settings store is encrypted, so passwords and future API values
 * never need a separate storage mechanism. Values are kept as strings by the
 * generic addon controller; this class normalises the numeric and boolean
 * ones for the rest of the addon.
 */
final class BankPaymentsSettings
{
    public static function fields(): array
    {
        $defaults = config('bank-payments', []);

        return [
            [
                'name' => 'mode',
                'label' => __('bank-payments::settings.mode'),
                'type' => 'select',
                'options' => [
                    'test' => __('bank-payments::settings.mode_test'),
                    'semi_auto' => __('bank-payments::settings.mode_semi_auto'),
                    'auto' => __('bank-payments::settings.mode_auto'),
                ],
                'default' => $defaults['mode'] ?? 'test',
                'hint' => __('bank-payments::settings.mode_hint'),
            ],
            [
                'name' => 'notification_email',
                'label' => __('bank-payments::settings.notification_email'),
                'type' => 'text',
                'hint' => __('bank-payments::settings.notification_email_hint'),
            ],
            [
                'name' => 'notify_on_review',
                'label' => __('bank-payments::settings.notify_on_review'),
                'type' => 'checkbox',
                'default' => '1',
                'hint' => __('bank-payments::settings.notify_on_review_hint'),
            ],
            [
                'name' => 'initial_sync_days',
                'label' => __('bank-payments::settings.initial_sync_days'),
                'type' => 'text',
                'default' => $defaults['initial_sync_days'] ?? 7,
                'hint' => __('bank-payments::settings.initial_sync_days_hint'),
            ],
            [
                'name' => 'sync_limit',
                'label' => __('bank-payments::settings.sync_limit'),
                'type' => 'text',
                'default' => $defaults['sync_limit'] ?? 100,
                'hint' => __('bank-payments::settings.sync_limit_hint'),
            ],
            [
                'name' => 'mark_seen',
                'label' => __('bank-payments::settings.mark_seen'),
                'type' => 'checkbox',
                'default' => ($defaults['mark_seen'] ?? true) ? '1' : '0',
                'hint' => __('bank-payments::settings.mark_seen_hint'),
            ],
            [
                'name' => 'retention_days',
                'label' => __('bank-payments::settings.retention_days'),
                'type' => 'text',
                'default' => $defaults['retention_days'] ?? 180,
                'hint' => __('bank-payments::settings.retention_days_hint'),
            ],
        ];
    }

    public static function resolve(): array
    {
        $stored = AddonSetting::getForAddon('bank_payments');

        $mode = self::pick($stored, 'mode', config('bank-payments.mode', 'test'));

        return [
            'mode' => (string) $mode,
            'notification_email' => (string) self::pick($stored, 'notification_email', config('bank-payments.notification_email', '')),
            'notify_on_review' => self::bool($stored, 'notify_on_review', (bool) config('bank-payments.notify_on_review', true)),
            'initial_sync_days' => self::boundedInt($stored, 'initial_sync_days', (int) config('bank-payments.initial_sync_days', 7), 1, 365),
            'sync_limit' => self::boundedInt($stored, 'sync_limit', (int) config('bank-payments.sync_limit', 100), 1, 500),
            'mark_seen' => self::bool($stored, 'mark_seen', (bool) config('bank-payments.mark_seen', true)),
            'retention_days' => self::boundedInt($stored, 'retention_days', (int) config('bank-payments.retention_days', 180), 1, 3650),
        ];
    }

    /** @return array<string, array<string, mixed>> */
    public static function banks(): array
    {
        $banks = config('bank-payments.banks', []);

        return is_array($banks) ? $banks : [];
    }

    public static function parserForBank(string $bank): ?string
    {
        $parser = self::banks()[$bank]['parser'] ?? null;

        return is_string($parser) && $parser !== '' ? $parser : null;
    }

    public static function mode(): string
    {
        return self::resolve()['mode'];
    }

    public static function initialSyncDays(): int
    {
        return self::resolve()['initial_sync_days'];
    }

    public static function syncLimit(): int
    {
        return self::resolve()['sync_limit'];
    }

    public static function markSeen(): bool
    {
        return self::resolve()['mark_seen'];
    }

    private static function pick(array $stored, string $key, mixed $default): mixed
    {
        return array_key_exists($key, $stored) && $stored[$key] !== null && $stored[$key] !== ''
            ? $stored[$key]
            : $default;
    }

    private static function boundedInt(array $stored, string $key, int $default, int $min, int $max): int
    {
        $value = filter_var(self::pick($stored, $key, $default), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => $min, 'max_range' => $max],
        ]);

        return $value === false ? $default : (int) $value;
    }

    private static function bool(array $stored, string $key, bool $default): bool
    {
        $value = self::pick($stored, $key, $default ? '1' : '0');

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
    }
}
