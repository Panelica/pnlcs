<?php

use Illuminate\Support\Facades\Route;

/*
 * The browser-side protections every page carries: no framing by other
 * sites, no type sniffing, no full addresses sent to other sites, and HSTS
 * over HTTPS only. A header the web server (or a page) already set is kept.
 */
test('pages carry the security headers', function () {
    $response = $this->get(str_replace('https://', 'http://', route('client.login')))->assertOk();

    $response->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeaderMissing('Strict-Transport-Security');
});

test('HSTS is sent over HTTPS', function () {
    $this->get(str_replace('http://', 'https://', route('admin.login')))
        ->assertHeader('Strict-Transport-Security', 'max-age=15552000');
});

test('a header a page sets itself is kept', function () {
    Route::middleware('web')->get('/_framed-on-purpose', fn () => response('ok')->header('X-Frame-Options', 'DENY'));

    $this->get('/_framed-on-purpose')->assertHeader('X-Frame-Options', 'DENY');
});
