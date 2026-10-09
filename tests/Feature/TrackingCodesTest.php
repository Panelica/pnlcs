<?php

use App\Http\Middleware\RedirectToInstaller;
use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Setting;
use App\Support\Tracking;

/*
 * Tracking codes (Setup > General > Tracking and cookie consent): a Tag
 * Manager container, the operator's own code, and a consent bar with Google
 * Consent Mode v2. On client and public pages, never in the admin area.
 */

beforeEach(function () {
    $this->withoutMiddleware(RedirectToInstaller::class);
});

test('nothing is added until something is set', function () {
    $this->get(route('client.login'))
        ->assertOk()
        ->assertDontSee('googletagmanager', false)
        ->assertDontSee('pnlcs-consent', false);
});

test('Tag Manager loads after consent defaults that deny ads and analytics, with the bar', function () {
    Setting::set('TrackingGtmId', 'gtm-abc1234', 'general');

    $html = $this->get(route('client.login'))->assertOk()->getContent();

    expect($html)->toContain("'GTM-ABC1234'")
        ->and($html)->toContain("gtag('consent', 'default', {ad_storage: 'denied', ad_user_data: 'denied', ad_personalization: 'denied', analytics_storage: 'denied'")
        ->and(strpos($html, "gtag('consent', 'default'"))->toBeLessThan(strpos($html, 'googletagmanager.com/gtm.js'))
        ->and($html)->toContain('id="pnlcs-consent"')
        ->and($html)->toContain('data-v="all"')->and($html)->toContain('data-v="necessary"')
        ->and($html)->toContain(e(__('client.consent.reject')));
});

test('the bar is in the page language and links to the privacy policy', function () {
    Setting::set('TrackingGtmId', 'GTM-ABC1234', 'general');

    app()->setLocale('tr');
    $footer = Tracking::footer();

    expect($footer)->toContain('Yalnız gerekli çerezler')->and($footer)->toContain('Kabul et')
        ->and($footer)->toContain('href="'.route('legal.show', 'privacy').'"');
});

test('with the bar switched off, everything is granted and no bar is shown', function () {
    Setting::set('TrackingGtmId', 'GTM-ABC1234', 'general');
    Setting::set('TrackingConsent', '0', 'general');

    expect(Tracking::head())->toContain("ad_storage: 'granted'")
        ->and(Tracking::footer())->not->toContain('pnlcs-consent');
});

test('an id that is not a Tag Manager id puts nothing on the page', function () {
    Setting::set('TrackingGtmId', "GTM-1'); alert(1); ('", 'general');

    expect(Tracking::gtmId())->toBeNull()
        ->and(Tracking::head())->toBe('')
        ->and(Tracking::footer())->toBe('');
});

test('the operator\'s own code is added as written', function () {
    Setting::set('TrackingHeadCode', '<meta name="x-test" content="1">', 'general');
    Setting::set('TrackingFooterCode', '<script>window.ok=1</script>', 'general');

    $this->get(route('client.login'))
        ->assertSee('<meta name="x-test" content="1">', false)
        ->assertSee('<script>window.ok=1</script>', false);
});

test('the admin area is never tracked', function () {
    Setting::set('TrackingGtmId', 'GTM-ABC1234', 'general');
    Setting::set('TrackingHeadCode', '<meta name="x-test">', 'general');
    $admin = Admin::factory()->create(['role_id' => AdminRole::factory()->fullAdmin()->create()->id]);

    $this->get(route('admin.login'))->assertDontSee('googletagmanager', false)->assertDontSee('x-test', false);
    $this->actingAs($admin, 'admin')->get(route('admin.dashboard'))->assertDontSee('googletagmanager', false);
});

test('staff set it on the general settings', function () {
    $admin = Admin::factory()->create(['role_id' => AdminRole::factory()->fullAdmin()->create()->id]);

    $this->actingAs($admin, 'admin')->get(route('admin.settings.general'))
        ->assertOk()->assertSee('name="TrackingGtmId"', false)->assertSee('name="TrackingConsent"', false);

    $this->actingAs($admin, 'admin')->post(route('admin.settings.general.update'), [
        'TrackingGtmId' => 'GTM-XYZ9876', 'TrackingConsent' => '1', 'TrackingHeadCode' => '<meta name="v" content="1">',
    ]);

    expect(Setting::get('TrackingGtmId'))->toBe('GTM-XYZ9876')
        ->and(Setting::get('TrackingHeadCode'))->toBe('<meta name="v" content="1">');
});
