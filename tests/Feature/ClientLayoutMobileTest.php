<?php

use App\Models\ProductGroup;

/*
 * The client area on a phone (GitHub discussion #3). Measured at 375px on the
 * live demo: the menu button sat past the right edge of the screen, so a
 * visitor could not open the menu at all, and on a store with a dozen product
 * groups the desktop bar was 2,400px wide. These tests hold the structure the
 * fix depends on; the widths themselves were measured in a browser.
 */

it('lists product groups inside the Services menu, not one bar item each', function () {
    foreach (['Shared Hosting', 'VPS Servers', 'Dedicated Servers'] as $i => $name) {
        ProductGroup::create(['name' => $name, 'slug' => Str::slug($name), 'hidden' => 0, 'sort_order' => $i]);
    }

    $html = $this->get(route('client.contact'))->assertOk()->getContent();

    $bar = substr($html, strpos($html, '<div class="pn-nav">'), strpos($html, '<div class="pn-nav-right">') - strpos($html, '<div class="pn-nav">'));
    preg_match_all('#<div class="pn-dropdown">(.*?)</div>\s*</div>#s', $bar, $dropdowns);
    $services = $dropdowns[1][0] ?? '';

    foreach (['shared-hosting', 'vps-servers', 'dedicated-servers'] as $slug) {
        expect($services)->toContain('kategori='.$slug);
    }
    // Not repeated as items of the bar itself.
    expect(substr_count($bar, 'kategori=shared-hosting'))->toBe(1);
});

it('offers a visitor the login and sign-up links inside the phone menu', function () {
    $html = $this->get(route('client.contact'))->assertOk()->getContent();
    $menu = substr($html, strpos($html, 'id="pnMobileMenu"'));

    expect($menu)->toContain(route('client.login'))->toContain(route('client.register'));
    // The bar buttons carry the class the phone layout hides, and the rule has
    // to win over app.css, which sets every .btn to inline-flex !important -
    // without that the buttons stayed and squeezed the brand to "PA...".
    expect($html)->toContain('pn-guest-btn')
        ->toContain('.pn-nav-right .pn-guest-btn{display:none !important}');
});

it('lets the contact page fall to one column instead of a fixed side column', function () {
    $html = $this->get(route('client.contact'))->assertOk()->getContent();

    expect($html)->toContain('class="pn-aside-grid"')
        ->not->toContain('grid-template-columns:1fr 380px');
});

it('keeps the side column layout under a theme that brings its own client layout', function () {
    // flavor (and custom themes, such as a hosting company's own) replace the
    // client layout. The side-column pages carried their grid in that layout
    // once, so under such a theme they fell apart into one column on desktop.
    $finder = app('view')->getFinder();
    app('view')->prependLocation(base_path('themes/flavor/views'));
    $finder->flush();

    $html = $this->get(route('client.contact'))->assertOk()->getContent();

    expect($html)->toContain('class="pn-aside-grid"')
        ->toContain('.pn-aside-grid{display:grid;grid-template-columns:1fr var(--aside,360px)');
});
