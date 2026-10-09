<?php

use App\Http\Middleware\RedirectToInstaller;
use App\Models\Announcement;
use App\Models\KbArticle;
use App\Models\KbCategory;
use App\Models\Setting;

/*
 * /sitemap.xml lists the pages a visitor can open without an account;
 * /robots.txt is the operator's text with the sitemap's address added.
 */

beforeEach(function () {
    $this->withoutMiddleware(RedirectToInstaller::class);
});

test('the sitemap lists the public pages and only the public content', function () {
    $open = KbCategory::create(['name' => 'Help']);
    $hidden = KbCategory::create(['name' => 'Internal', 'hidden' => true]);
    $article = KbArticle::create(['category_id' => $open->id, 'title' => 'Public', 'article' => 'x', 'private' => false]);
    $private = KbArticle::create(['category_id' => $open->id, 'title' => 'Private', 'article' => 'x', 'private' => true]);
    $inHidden = KbArticle::create(['category_id' => $hidden->id, 'title' => 'Hidden', 'article' => 'x', 'private' => false]);
    $news = Announcement::create(['title' => 'News', 'announcement' => 'x', 'published' => true]);
    $draft = Announcement::create(['title' => 'Draft', 'announcement' => 'x', 'published' => false]);

    $xml = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8')->getContent();

    expect(simplexml_load_string($xml))->not->toBeFalse()
        ->and($xml)->toContain('<loc>'.route('home').'</loc>')
        ->and($xml)->toContain('<loc>'.route('client.domain.pricing').'</loc>')
        ->and($xml)->toContain('<loc>'.route('legal.show', 'terms').'</loc>')
        ->and($xml)->toContain('<loc>'.route('client.kb.show', $article).'</loc>')
        ->and($xml)->toContain('<loc>'.route('client.announcements.show', $news).'</loc>')
        ->and($xml)->not->toContain(route('client.kb.show', $private).'<')
        ->and($xml)->not->toContain(route('client.kb.show', $inHidden).'<')
        ->and($xml)->not->toContain(route('client.announcements.show', $draft).'<')
        ->and($xml)->not->toContain('/admin');
});

test('with the knowledge base switched off, it is not in the sitemap', function () {
    Setting::set('KnowledgeBaseEnabled', '0', 'general');

    expect($this->get('/sitemap.xml')->getContent())->not->toContain(route('client.kb.index').'<');
});

test('an addon adds its own pages through the SitemapUrls hook', function () {
    add_hook('SitemapUrls', 1, fn () => ['https://example.com/promo', ['loc' => 'https://example.com/blog/hello', 'lastmod' => '2026-10-01'], 'not a url']);

    $xml = $this->get('/sitemap.xml')->getContent();

    expect($xml)->toContain('<loc>https://example.com/promo</loc>')
        ->and($xml)->toContain('<loc>https://example.com/blog/hello</loc><lastmod>2026-10-01</lastmod>')
        ->and($xml)->not->toContain('not a url');
});

test('robots.txt allows everything by default and names the sitemap', function () {
    $this->get('/robots.txt')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertSee("User-agent: *\nDisallow:", false)
        ->assertSee('Sitemap: '.route('sitemap'), false);
});

test('robots.txt is the operator\'s text, with the sitemap added', function () {
    Setting::set('RobotsTxt', "User-agent: *\nDisallow: /client/cart", 'general');

    $this->get('/robots.txt')
        ->assertSee("Disallow: /client/cart\n\nSitemap: ".route('sitemap'), false);
});

test('the shipped public/robots.txt stays, so an update never trips over an edited one', function () {
    // Operators edit public/robots.txt; a release that dropped it would stop
    // their next update on a "removed in the new version" conflict. The web
    // server keeps answering with the file, and the settings say so.
    expect(is_file(public_path('robots.txt')))->toBeTrue();

    $admin = \App\Models\Admin::factory()->create(['role_id' => \App\Models\AdminRole::factory()->fullAdmin()->create()->id]);
    $this->actingAs($admin, 'admin')->get(route('admin.settings.general'))
        ->assertOk()
        ->assertSee(__('admin.settings.robots_txt_static', ['path' => 'public/robots.txt']), false);
});
