<?php

namespace Modules\Servers\PanelicaLicense;

use App\Models\Setting;
use Illuminate\Contracts\Encryption\DecryptException;
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
    }

    public static function forget(): void
    {
        Setting::set(self::KEY, '', self::GROUP);
    }

    /** The partner's own pages at Central. */
    public static function portalUrl(string $path = 'customer/reseller/dashboard'): string
    {
        return self::centralUrl().'/'.ltrim($path, '/');
    }
}
