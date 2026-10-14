<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * The language the install wizard speaks.
 *
 * SetLocale reads the language from the database, and while the wizard runs
 * there is none yet: every step came out in the configured default, English.
 * The person installing picks one at the top of the wizard (?lang=), or gets
 * their browser's when it is one of these; the choice is kept for the rest of
 * the wizard and offered as the default language on the last step.
 */
class InstallLocale
{
    /** The languages the wizard is written in, with the name each gives itself. */
    public const LANGUAGES = ['en' => 'English', 'tr' => 'Türkçe', 'de' => 'Deutsch', 'pl' => 'Polski', 'zh' => '简体中文'];

    public function handle(Request $request, Closure $next)
    {
        $asked = strtolower((string) $request->query('lang', ''));
        if (isset(self::LANGUAGES[$asked])) {
            $request->session()->put('install.locale', $asked);
        }

        $locale = $request->session()->get('install.locale')
            ?? $request->getPreferredLanguage(array_keys(self::LANGUAGES))
            ?? 'en';

        app()->setLocale(isset(self::LANGUAGES[$locale]) ? $locale : 'en');

        return $next($request);
    }
}
