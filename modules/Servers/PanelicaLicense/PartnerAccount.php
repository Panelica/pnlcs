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

    /**
     * Whether the connected Central sells a key for a term of months on the
     * convert call, and not only on one of its two cycles.
     *
     * It is asked of Central's own published schema rather than assumed: a
     * Central that takes only monthly or yearly would charge one month for a
     * three-month order and write one month of licence - silent mis-billing.
     * Unknown means no, so a site talking to an older Central keeps refusing
     * those periods at order time, before any money moves.
     */
    private const TERMS = 'panelica_partner_convert_terms';

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

    /**
     * Does this Central sell a term of months when a trial is converted?
     *
     * Read from Central's own published schema and remembered for a few hours:
     * an order must not wait for a second round trip, and a Central that gains
     * the ability is noticed without anyone retyping the key. Unreachable or
     * unreadable counts as no, so the period is refused at order time rather
     * than sold for the wrong money.
     */
    public static function sellsTerms(): bool
    {
        return (bool) Cache::remember(self::TERMS, now()->addHours(6),
            fn () => CentralClient::partner()?->convertTakesTerm() ?? false);
    }

    /** Ask Central again now (the Panelica Licenses page's sync). */
    public static function forgetTermSupport(): void
    {
        Cache::forget(self::TERMS);
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
