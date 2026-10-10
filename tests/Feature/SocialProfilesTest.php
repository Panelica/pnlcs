<?php

use App\Http\Middleware\RedirectToInstaller;
use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Setting;

/*
 * The shop's social media profiles (Setup > General > Search engines and
 * sharing): themes show them, and the home page names them to search engines.
 */

beforeEach(function () {
    $this->withoutMiddleware(RedirectToInstaller::class);
});

test('nothing is shown or named until a profile is set', function () {
    expect(social_profiles())->toBe([]);

    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->not->toContain('class="footer__social"')->and($html)->not->toContain('"sameAs"')
        ->and($html)->not->toContain('"@type":"Organization"');
});

test('only web addresses count, in the networks\' order', function () {
    Setting::set('SocialYoutube', 'https://www.youtube.com/@shop', 'general');
    Setting::set('SocialX', 'https://x.com/shop', 'general');
    Setting::set('SocialInstagram', 'not an address', 'general');
    Setting::set('SocialTiktok', 'javascript:alert(1)', 'general');

    expect(social_profiles())->toBe(['x' => 'https://x.com/shop', 'youtube' => 'https://www.youtube.com/@shop']);
});

test('the home page links to them and names them to search engines', function () {
    Setting::set('SocialX', 'https://x.com/shop', 'general');
    Setting::set('SocialLinkedin', 'https://www.linkedin.com/company/shop/', 'general');

    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->toContain('href="https://x.com/shop"')
        ->and($html)->toContain('aria-label="LinkedIn"')
        ->and($html)->toContain('"@type":"Organization"')
        ->and($html)->toContain('"sameAs":["https://x.com/shop","https://www.linkedin.com/company/shop/"]');
});

test('the organization is named on the home page only', function () {
    Setting::set('SocialX', 'https://x.com/shop', 'general');

    $this->get(route('client.login'))->assertOk()->assertDontSee('"@type":"Organization"', false);
});

test('staff set them on the general settings', function () {
    $admin = Admin::factory()->create(['role_id' => AdminRole::factory()->fullAdmin()->create()->id]);

    $this->actingAs($admin, 'admin')->get(route('admin.settings.general'))
        ->assertOk()->assertSee('name="SocialInstagram"', false)->assertSee(__('admin.settings.social_profiles'));

    $this->actingAs($admin, 'admin')->post(route('admin.settings.general.update'), [
        'SocialInstagram' => 'https://www.instagram.com/shop/', 'SocialMedium' => 'https://shop.medium.com/',
    ]);

    expect(social_profiles())->toBe(['instagram' => 'https://www.instagram.com/shop/', 'medium' => 'https://shop.medium.com/']);
});
