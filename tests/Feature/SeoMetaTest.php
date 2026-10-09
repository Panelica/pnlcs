<?php

use App\Http\Middleware\RedirectToInstaller;
use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Announcement;
use App\Models\KbArticle;
use App\Models\KbCategory;
use App\Models\Setting;

/*
 * What search engines and link previews read about a page: a description, and
 * the Open Graph and X (Twitter) card tags a shared link is drawn from.
 */

beforeEach(function () {
    $this->withoutMiddleware(RedirectToInstaller::class);
});

test('a page carries a description and the tags a shared link is drawn from', function () {
    Setting::set('SeoDescription', 'Hosting, domains and apps in one place.', 'general');
    Setting::set('SeoShareImage', 'https://cdn.example.com/share.png', 'general');
    Setting::set('SeoTwitter', '@example_host', 'general');

    $this->get(route('client.login'))
        ->assertOk()
        ->assertSee('<meta name="description" content="Hosting, domains and apps in one place.">', false)
        ->assertSee('<meta property="og:title" content="'.__('client.auth.login_title').' - '.company_name().'">', false)
        ->assertSee('<meta property="og:description" content="Hosting, domains and apps in one place.">', false)
        ->assertSee('<meta property="og:image" content="https://cdn.example.com/share.png">', false)
        ->assertSee('<meta property="og:url" content="'.route('client.login').'">', false)
        ->assertSee('<meta name="twitter:card" content="summary_large_image">', false)
        ->assertSee('<meta name="twitter:site" content="@example_host">', false);
});

test('without an image for sharing, the logo is used; without either, a plain card', function () {
    Setting::set('custom_logo_path', '/branding/logo.png', 'appearance');

    $this->get(route('client.login'))
        ->assertSee('<meta property="og:image" content="'.url('/branding/logo.png').'">', false);

    Setting::set('custom_logo_path', '', 'appearance');

    $this->get(route('client.login'))
        ->assertDontSee('og:image', false)
        ->assertSee('<meta name="twitter:card" content="summary">', false);
});

test('a knowledge base article and an announcement describe themselves', function () {
    Setting::set('SeoDescription', 'Shop text', 'general');
    $category = KbCategory::create(['name' => 'Help']);
    $article = KbArticle::create(['category_id' => $category->id, 'title' => 'Point a domain', 'article' => '<p>Change the <b>name servers</b> at your registrar.</p>', 'private' => false]);
    $news = Announcement::create(['title' => 'Maintenance', 'announcement' => '<p>The mail server restarts on Sunday.</p>', 'published' => true]);

    $this->get(route('client.kb.show', $article))
        ->assertOk()
        ->assertSee('<meta name="description" content="Change the name servers at your registrar.">', false)
        ->assertSee('<meta property="og:type" content="article">', false)
        ->assertDontSee('content="Shop text"', false);

    $this->get(route('client.announcements.show', $news))
        ->assertOk()
        ->assertSee('<meta name="description" content="The mail server restarts on Sunday.">', false);
});

test('the home page and the legal pages keep their own description, once', function () {
    $home = $this->get('/')->assertOk()->getContent();
    expect(substr_count($home, '<meta name="description"'))->toBe(1);

    $legal = $this->get(route('legal.show', 'terms'))->assertOk()->getContent();
    expect(substr_count($legal, '<meta name="description"'))->toBe(1)
        ->and($legal)->toContain('<meta property="og:title"');
});

test('a long description is cut, markup is dropped, and a bad X account is left out', function () {
    Setting::set('SeoDescription', '<b>'.str_repeat('word ', 60).'</b>', 'general');
    Setting::set('SeoTwitter', 'not a handle!', 'general');

    $html = $this->get(route('client.login'))->getContent();
    preg_match('/<meta name="description" content="([^"]*)">/', $html, $m);

    expect(mb_strlen(html_entity_decode($m[1])))->toBeLessThanOrEqual(161)
        ->and($m[1])->not->toContain('<b>')
        ->and($html)->not->toContain('twitter:site');
});

test('staff set the description, the image and the account on the general settings', function () {
    $admin = Admin::factory()->create(['role_id' => AdminRole::factory()->fullAdmin()->create()->id]);

    $this->actingAs($admin, 'admin')->get(route('admin.settings.general'))
        ->assertOk()->assertSee('name="SeoDescription"', false)->assertSee('name="SeoShareImage"', false);

    $this->actingAs($admin, 'admin')->post(route('admin.settings.general.update'), [
        'SeoDescription' => 'Our shop', 'SeoShareImage' => 'https://cdn.example.com/s.png', 'SeoTwitter' => '@shop',
    ]);

    expect(Setting::get('SeoDescription'))->toBe('Our shop')
        ->and(Setting::get('SeoShareImage'))->toBe('https://cdn.example.com/s.png')
        ->and(Setting::get('SeoTwitter'))->toBe('@shop');
});

test('a theme that brings its own home page carries the sharing tags too', function () {
    app('view')->prependLocation(base_path('themes/flavor/views'));
    app('view')->getFinder()->flush();

    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->toContain('<meta property="og:title"')
        ->and(substr_count($html, '<meta name="description"'))->toBe(1);
});
