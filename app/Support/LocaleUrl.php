<?php

namespace App\Support;

use App\Models\Language;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Throwable;

/**
 * The language in the address, for the client area and the public pages.
 *
 * Off unless the operator switches it on (Setup > Languages, setting
 * LocaleUrls). Then the default language keeps today's addresses and every
 * other active language is served under its code: /en/client/store,
 * /pl/legal/terms. Each address shows one language, so a search engine can
 * index every language and a shared link opens in the language it was copied
 * in.
 *
 * - LocaleUrlPrefix (a global middleware) takes the code off before routing, so
 *   routes, is('client/*') and every check on the path stay as they are.
 * - URL generation puts it back: route() and url() on a page in another
 *   language carry the prefix (formatPath, registered in AppServiceProvider).
 * - SetLocale sends a visitor who chose a language to that language's address,
 *   which also repairs links written as plain "/client/..." strings and stored
 *   return addresses.
 *
 * The admin area, the API, payment gateway callbacks, the installer, health
 * checks, assets and signed links never get a prefix.
 */
class LocaleUrl
{
    public const SETTING = 'LocaleUrls';

    /** First path segments that are never prefixed: not pages a visitor reads in a language. */
    public const EXCLUDED = ['admin', 'api', 'gateway', 'install', 'up', 'build', 'storage', 'themes', 'img', 'images', 'branding', 'vendor', 'livewire', 'sitemap.xml', 'robots.txt'];

    private const MEMO = 'pnlcs.locale_url';

    public static function enabled(): bool
    {
        return self::memo()['enabled'];
    }

    public static function defaultLocale(): string
    {
        return self::memo()['default'];
    }

    /** @return array<int, string> the active languages that are served under a prefix */
    public static function prefixed(): array
    {
        return self::memo()['prefixed'];
    }

    /** @return array<int, string> every active language, the default first */
    public static function locales(): array
    {
        return array_values(array_unique(array_merge([self::defaultLocale()], self::prefixed())));
    }

    /** Forget what was read for this request (the admin screen changes it, tests switch it). */
    public static function forget(): void
    {
        app()->forgetInstance(self::MEMO);
    }

    /**
     * Pages whose address is fixed from outside: social sign-in, whose
     * callback is registered with Google and GitHub as it is, and the e-mail
     * confirmation link, whose signature is checked against the address
     * without a prefix. They keep the address they were made with.
     */
    public const FIXED = ['client/auth', 'client/email/verify'];

    public static function excluded(string $path): bool
    {
        $path = strtolower(trim($path, '/'));

        if (in_array(explode('/', $path, 2)[0], self::EXCLUDED, true)) {
            return true;
        }

        foreach (self::FIXED as $fixed) {
            if ($path === $fixed || str_starts_with($path, $fixed.'/')) {
                return true;
            }
        }

        return false;
    }

    /** The language a prefixed path starts with, if it is one of ours. */
    public static function prefixOf(string $path): ?string
    {
        $first = strtolower(explode('/', ltrim($path, '/'), 2)[0]);

        return in_array($first, self::prefixed(), true) ? $first : null;
    }

    /** The path generated for a link, in the language the page (or e-mail) is rendered in. */
    public static function formatPath(string $path, ?Route $route = null): string
    {
        if (! self::enabled()) {
            return $path;
        }

        $locale = strtolower((string) app()->getLocale());

        if (! in_array($locale, self::prefixed(), true) || self::excluded($path) || self::prefixOf($path) !== null) {
            return $path;
        }

        // A signed link is checked against the address it arrives at; it
        // stays as it was made so the signature holds.
        if ($route && collect($route->gatherMiddleware())->contains(fn ($m) => is_string($m) && str_starts_with($m, 'signed'))) {
            return $path;
        }

        return '/'.$locale.($path === '/' ? '' : $path);
    }

    /**
     * The address of the page being shown, in another language: the
     * hreflang links, the canonical link and the language switchers.
     */
    public static function current(string $locale, ?Request $request = null): string
    {
        $request ??= request();
        $path = '/'.ltrim($request->path(), '/');
        $query = $request->query();
        unset($query['lang']);

        $locale = strtolower($locale);
        $prefixed = $locale !== self::defaultLocale() && in_array($locale, self::prefixed(), true);

        // the root links are made with (a configured domain wins over the request's)
        $root = rtrim(url()->formatRoot(url()->formatScheme()), '/');
        $url = $root.($prefixed ? '/'.$locale.($path === '/' ? '' : $path) : $path);

        return $query ? $url.'?'.http_build_query($query) : $url;
    }

    /**
     * A language switcher's link: with the setting on, the page at that
     * language's address; ?lang makes it the visitor's choice (SetLocale then
     * drops it from the address). With the setting off, today's ?lang link.
     */
    public static function switchTo(string $locale, ?Request $request = null): string
    {
        $request ??= request();

        if (! self::enabled()) {
            return $request->fullUrlWithQuery(['lang' => $locale]);
        }

        $url = self::current($locale, $request);

        return $url.(str_contains($url, '?') ? '&' : '?').'lang='.rawurlencode($locale);
    }

    private static function memo(): array
    {
        if (app()->bound(self::MEMO)) {
            return app(self::MEMO);
        }

        try {
            $default = strtolower((string) Setting::get('DefaultLanguage', config('app.locale', 'en')));
            $enabled = (string) Setting::get(self::SETTING, '0') === '1';
            $active = $enabled
                ? Language::where('is_active', true)->pluck('code')->map(fn ($c) => strtolower((string) $c))->all()
                : [];
        } catch (Throwable) {
            $default = strtolower((string) config('app.locale', 'en'));
            $enabled = false;
            $active = [];
        }

        $memo = [
            'enabled' => $enabled,
            'default' => $default,
            'prefixed' => array_values(array_filter($active, fn ($c) => $c !== $default)),
        ];
        app()->instance(self::MEMO, $memo);

        return $memo;
    }
}
