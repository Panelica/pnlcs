<?php

namespace App\Support;

use App\Models\Setting;
use Throwable;

/**
 * Tracking codes for the client area and the public pages: a Google Tag
 * Manager container, the operator's own code for the head and the end of the
 * page, and an optional cookie consent bar with Google Consent Mode v2.
 *
 * Set under Setup > General > Tracking and cookie consent. Pixels and
 * analytics are then added in Tag Manager, not in pnlcs. With the bar on (the
 * default), ad and analytics storage stay "denied" until the visitor accepts.
 * The choice is kept for 180 days in the pnlcs_consent cookie, and Tag Manager
 * receives a pnlcs_consent event.
 *
 * Printed through the ClientAreaHeadOutput and ClientAreaFooterOutput hook
 * points, so every client layout and theme that calls them carries it. The
 * admin area never does.
 */
class Tracking
{
    public const KEYS = ['TrackingGtmId', 'TrackingConsent', 'TrackingPolicyUrl', 'TrackingHeadCode', 'TrackingFooterCode'];

    public const COOKIE = 'pnlcs_consent';

    /** The container id when it is one, else null: nothing else reaches the page. */
    public static function gtmId(): ?string
    {
        $id = strtoupper(trim(self::setting('TrackingGtmId')));

        return preg_match('/^GTM-[A-Z0-9]{4,12}$/', $id) ? $id : null;
    }

    public static function consentOn(): bool
    {
        return self::setting('TrackingConsent', '1') !== '0';
    }

    public static function head(): string
    {
        if (self::adminArea()) {
            return '';
        }

        $out = '';
        if ($gtm = self::gtmId()) {
            $default = self::consentOn() ? 'denied' : 'granted';
            $cookie = self::COOKIE;
            $out .= <<<HTML
<script>
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag('consent', 'default', {ad_storage: '{$default}', ad_user_data: '{$default}', ad_personalization: '{$default}', analytics_storage: '{$default}', functionality_storage: 'granted', security_storage: 'granted', wait_for_update: 500});
(function () { var m = document.cookie.match(/(?:^|; ){$cookie}=(all|necessary)/); if (m && m[1] === 'all') gtag('consent', 'update', {ad_storage: 'granted', ad_user_data: 'granted', ad_personalization: 'granted', analytics_storage: 'granted'}); })();
</script>
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','{$gtm}');</script>

HTML;
        }

        return $out.self::setting('TrackingHeadCode');
    }

    public static function footer(): string
    {
        if (self::adminArea()) {
            return '';
        }

        $bar = (self::gtmId() !== null && self::consentOn()) ? view('partials.consent-bar', [
            'policy' => self::setting('TrackingPolicyUrl') ?: (\Illuminate\Support\Facades\Route::has('legal.show') ? route('legal.show', 'privacy') : ''),
            'cookie' => self::COOKIE,
        ])->render() : '';

        return $bar.self::setting('TrackingFooterCode');
    }

    /** The admin sign-in is drawn on client layouts by some themes: the address decides, not the hook. */
    private static function adminArea(): bool
    {
        return request()->is('admin', 'admin/*');
    }

    private static function setting(string $key, string $default = ''): string
    {
        try {
            return trim((string) Setting::get($key, $default));
        } catch (Throwable) {
            return $default;
        }
    }
}
