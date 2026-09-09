<?php

namespace App\Http\Middleware;

use App\Models\Language;
use App\Models\Setting;
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

        // Persist to session/cookie
        if ($request->has('lang')) {
            session(['locale' => $locale]);
            cookie()->queue('pnlcs_locale', $locale, 43200); // 30 days
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
