<?php

use App\Models\Setting;
use Illuminate\Support\Facades\URL;

/*
 * Where a link in customer mail points.
 *
 * In a web request Laravel builds links from the request. From the queue or a
 * cron there is no request, so it falls back to the configured app URL - and in
 * a container that comes from an environment variable that overrides .env. On
 * our install it resolved to the address inside the docker network, so the
 * "view your invoice" link in customer mail was
 * http://<internal-ip>:8090/client/invoices/2, which no customer can open.
 *
 * Tests run in console context, which is exactly the context being fixed.
 */

it('builds console links from the address the operator configured', function () {
    Setting::set('Domain', 'billing.example.com', 'general');
    app()->forgetInstance('url');
    (new App\Providers\AppServiceProvider(app()))->boot();

    expect(url('/client/invoices/2'))->toBe('https://billing.example.com/client/invoices/2');
});

it('keeps a scheme the operator typed', function () {
    Setting::set('Domain', 'http://billing.example.com/', 'general');
    app()->forgetInstance('url');
    (new App\Providers\AppServiceProvider(app()))->boot();

    // Trailing slash trimmed, and http respected rather than forced to https.
    expect(url('/x'))->toBe('http://billing.example.com/x');
});

it('changes nothing when no address is configured', function () {
    Setting::where('setting', 'Domain')->delete();
    $before = url('/client/invoices/2');

    (new App\Providers\AppServiceProvider(app()))->boot();

    expect(url('/client/invoices/2'))->toBe($before);
});

it('ignores an unusable value rather than producing broken links', function () {
    Setting::set('Domain', 'not a url', 'general');
    $before = url('/x');

    (new App\Providers\AppServiceProvider(app()))->boot();

    expect(url('/x'))->toBe($before);
});

afterEach(function () {
    URL::forceRootUrl(null);
});
