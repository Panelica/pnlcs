<?php

namespace Modules\Servers\PanelicaLicense;

use App\Models\Setting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

/**
 * The Panelica partner account this site sells licenses with: its API key
 * (rk_..., kept encrypted) and Panelica Central's address. One account per
 * site, set on the Panelica Licenses page; it is not a server record, since
 * nothing is installed on a machine of the partner's.
 */
final class PartnerAccount
{
    public const DEFAULT_CENTRAL = 'https://www.panelica.com';

    /** What a partner API key starts with, for the form's example. */
    public const KEY_EXAMPLE = 'rk_...';

    private const KEY = 'panelica_partner_api_key';

    private const CENTRAL = 'panelica_partner_central';

    private const GROUP = 'panelica_licenses';

    public static function apiKey(): ?string
    {
        $stored = (string) Setting::get(self::KEY, '');
        if ($stored === '') {
            return null;
        }

        try {
            return Crypt::decryptString($stored);
        } catch (DecryptException) {
            // A key encrypted with another APP_KEY cannot be used; ask again.
            return null;
        }
    }

    public static function centralUrl(): string
    {
        $url = rtrim(trim((string) Setting::get(self::CENTRAL, '')), '/');

        return $url !== '' ? $url : self::DEFAULT_CENTRAL;
    }

    public static function connected(): bool
    {
        return self::apiKey() !== null;
    }

    public static function save(string $apiKey, ?string $centralUrl = null): void
    {
        Setting::set(self::KEY, Crypt::encryptString(trim($apiKey)), self::GROUP);
        Setting::set(self::CENTRAL, rtrim(trim((string) $centralUrl), '/'), self::GROUP);
        // Another account has another install link.
        self::forgetInstallCode();
    }

    public static function forget(): void
    {
        Setting::set(self::KEY, '', self::GROUP);
        self::forgetInstallCode();
    }

    /**
     * The partner's install link code at Central. Kept for ten minutes so a
     * run of installs does not ask Central each time; a failure is not kept,
     * and changing the code on the Panelica Licenses page forgets it.
     */
    public static function installCode(CentralClient $client): ?string
    {
        $key = self::codeCacheKey();

        if ($cached = Cache::get($key)) {
            return (string) $cached;
        }

        $link = $client->get('install-link');
        $code = $link->ok ? (string) ($link->data['code'] ?? '') : '';
        if ($code === '') {
            return null;
        }

        Cache::put($key, $code, now()->addMinutes(10));

        return $code;
    }

    public static function forgetInstallCode(): void
    {
        Cache::forget(self::codeCacheKey());
    }

    private static function codeCacheKey(): string
    {
        return 'panelica_license:install_code:'.md5(self::centralUrl());
    }

    /** The partner's own pages at Central. */
    public static function portalUrl(string $path = 'customer/reseller/dashboard'): string
    {
        return self::centralUrl().'/'.ltrim($path, '/');
    }
}
