<?php

namespace App\Http\Middleware;

use App\Models\Language;
use App\Models\Setting;
use App\Support\LocaleUrl;
use Closure;
use Illuminate\Http\Request;

class SetLocale
{
    public function handle(Request $request, Closure $next)
    {
        $locale = $this->resolveLocale($request);

        // Validate locale is active
        try {
            $activeLocales = Language::where('is_active', true)->pluck('code')->toArray();
            if (!in_array($locale, $activeLocales)) {
                $locale = $this->getDefaultLocale();
            }
        } catch (\Throwable $e) {
            $locale = config('app.locale', 'en');
        }

        app()->setLocale($locale);

        // Persist to session/cookie: a language picked with ?lang, or read
        // from the address (LocaleUrl), is the visitor's choice from now on.
        if ($request->has('lang') || $request->attributes->has('url_locale')) {
            session(['locale' => $locale]);
            cookie()->queue('pnlcs_locale', $locale, 43200); // 30 days
        }

        // Language in the address: a page asked for at the address of another
        // language goes to its own. This is how a visitor who picked English
        // and follows a plain "/client/..." link, or comes back from a login,
        // lands on /en/... again.
        if ($redirect = $this->toLocaleAddress($request, $locale)) {
            return $redirect;
        }

        // Share with all views
        $direction = 'ltr';
        try {
            $language = Language::where('code', $locale)->first();
            if ($language) {
                $direction = $language->direction;
            }
        } catch (\Throwable $e) {}

        view()->share('currentLocale', $locale);
        view()->share('textDirection', $direction);

        return $next($request);
    }

    protected function resolveLocale(Request $request): string
    {
        // 1. Query param ?lang=xx
        if ($request->has('lang') && strlen($request->query('lang')) >= 2) {
            return $request->query('lang');
        }

        // 1b. The language in the address (LocaleUrlPrefix)
        if ($request->attributes->has('url_locale')) {
            return (string) $request->attributes->get('url_locale');
        }

        // 2. Session
        if ($locale = session('locale')) {
            return $locale;
        }

        // 3. Authenticated user/client language
        try {
            if ($user = auth()->user()) {
                if (method_exists($user, "clients")) {
                    // A login can belong to more than one account: follow the
                    // one being looked at, the same as every page does.
                    $selected = session('active_client_id');
                    $client = $selected ? $user->clients()->whereKey($selected)->first() : null;
                    $client ??= $user->clients()->first();

                    if ($client && !empty($client->language)) {
                        return $client->language;
                    }
                }
            }
        } catch (\Throwable $e) {}









        // 4. Admin language
        if ($admin = auth('admin')->user()) {
            if (!empty($admin->language)) {
                return $admin->language;
            }
        }

        // 5. Cookie
        if ($locale = $request->cookie('pnlcs_locale')) {
            return $locale;
        }

        // With the language in the address, an address without a prefix is
        // the default language unless the visitor chose otherwise (the steps
        // above). Guessing from the browser here would send search engines
        // away from every default-language page.
        if (LocaleUrl::enabled()) {
            return $this->getDefaultLocale();
        }

        // 6. Ziyaretcinin ulkesi ve tarayici dili.
        //
        // Eskiden burada yalniz Accept-Language okunuyordu ve donen deger
        // etkin diller arasinda olmasa bile kabul ediliyordu; 'fr' isteyen bir
        // tarayici sistemi olmayan bir dile dusuruyordu. GeoLocale once
        // tarayicinin istedigi dile, o tutmazsa ulkeye bakiyor ve yalnizca
        // gercekten yayinda olan bir dil donduruyor.
        $activeLocales = $this->activeLocales();
        $geo = \App\Support\GeoLocale::locale($request, $activeLocales);

        if ($geo !== null) {
            return $geo;
        }

        // 7. Default from settings
        return $this->getDefaultLocale();
    }

    /**
     * The address of this page in the language being shown, when the visitor
     * is at another one. Only for a page a visitor reads: GET, not an API or
     * JSON call, not the admin area or a gateway, not a signed link (its
     * signature is checked against the address it was sent to).
     */
    protected function toLocaleAddress(Request $request, string $locale)
    {
        if (! LocaleUrl::enabled() || ! $request->isMethod('GET') || $request->expectsJson() || $request->ajax()) {
            return null;
        }
        if (LocaleUrl::excluded($request->path())) {
            return null;
        }
        $route = $request->route();
        if ($route && collect($route->gatherMiddleware())->contains(fn ($m) => is_string($m) && str_starts_with($m, 'signed'))) {
            return null;
        }

        $locale = strtolower($locale);
        $want = in_array($locale, LocaleUrl::prefixed(), true) ? $locale : null;
        $have = $request->attributes->get('url_locale');

        if ($want === $have && ! $request->has('lang')) {
            return null;
        }

        $target = LocaleUrl::current($locale, $request);

        return $target === $request->fullUrl() ? null : redirect()->to($target, 302);
    }

    /**
     * The languages actually switched on, lowercase.
     *
     * @return array<int, string>
     */
    protected function activeLocales(): array
    {
        try {
            return \App\Models\Language::where('is_active', 1)
                ->pluck('code')
                ->map(fn ($c) => strtolower((string) $c))
                ->all();
        } catch (\Throwable) {
            return [config('app.locale', 'en')];
        }
    }

    protected function getDefaultLocale(): string
    {
        try {
            return Setting::get('DefaultLanguage', config('app.locale', 'en'));
        } catch (\Throwable $e) {
            return config('app.locale', 'en');
        }
    }
}
