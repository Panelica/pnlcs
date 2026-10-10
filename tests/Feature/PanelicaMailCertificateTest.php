<?php

use App\Models\Product;
use App\Models\Server;
use App\Models\Service;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Modules\Servers\Panelica\PanelicaModule;

/*
 * Mail clients are told mail.<the customer's domain>, and the panel is asked
 * for that domain's mail certificate when it gets a mailbox.
 *
 * The panel serves each domain's mail certificate by SNI but does not issue
 * it by itself, and every issue request takes a new certificate from the
 * certificate authority's weekly allowance. So it is requested only when the
 * domain has none, and never at the cost of the mailbox.
 */

function mailCertServer(): Server
{
    return Server::create([
        'name' => 'Panel', 'hostname' => 's1.example.net', 'ip_address' => '10.0.0.9',
        'type' => 'panelica', 'username' => 'u', 'password' => 'pk', 'access_hash' => 'sk',
        'port' => 8443, 'active' => true,
    ]);
}

function mailCertService(?string $domain = 'shop.example.com'): Service
{
    $product = Product::factory()->create(['server_type' => 'panelica']);

    return Service::factory()->create([
        'product_id' => $product->id, 'server_id' => mailCertServer()->id, 'domain' => $domain,
        'status' => 'active', 'module_data' => ['panelica_user_id' => 'acct-1'],
    ]);
}

/** The panel API, with the domain's mail certificate in the given state. */
function fakeMailCertPanel(string $certState, int $statusCode = 200, int $mailboxCode = 201): void
{
    Http::fake(function (Request $request) use ($certState, $statusCode, $mailboxCode) {
        $url = $request->url();

        if (str_contains($url, '/v1/accounts/acct-1/domains')) {
            return Http::response(['data' => [['id' => 'dom-1', 'domain_name' => 'shop.example.com']]]);
        }
        if (str_ends_with($url, '/v1/email-accounts') && $request->method() === 'POST') {
            return Http::response(['status' => 'success', 'data' => ['id' => 'm1']], $mailboxCode);
        }
        if (str_ends_with($url, '/v1/ssl/domains/dom-1/mail') && $request->method() === 'GET') {
            return Http::response(['status' => 'success', 'data' => ['status' => $certState, 'days_left' => 0]], $statusCode);
        }
        if (str_ends_with($url, '/v1/ssl/domains/dom-1/mail/issue')) {
            return Http::response(['status' => 'success', 'data' => ['status' => 'pending']], 202);
        }

        return Http::response(['data' => []]);
    });
}

function issueWasRequested(): bool
{
    return Http::recorded(fn (Request $r) => str_ends_with($r->url(), '/mail/issue') && $r->method() === 'POST')->isNotEmpty();
}

test('mail clients are told mail. plus the customer\'s own domain', function () {
    expect((new PanelicaModule)->mailHostname(mailCertService('Shop.Example.com')))->toBe('mail.shop.example.com')
        ->and((new PanelicaModule)->mailHostname(mailCertService('www.shop.example.com')))->toBe('mail.shop.example.com');
});

test('a service without a domain still gets the host guessed from the server', function () {
    expect((new PanelicaModule)->mailHostname(mailCertService('')))->toBe('mail.example.net');
});

test('the first mailbox on a domain asks the panel for its mail certificate', function () {
    fakeMailCertPanel('missing');

    $result = (new PanelicaModule)->createEmail(mailCertService(), 'dom-1', 'info', 'password123');

    expect($result['success'])->toBeTrue()->and(issueWasRequested())->toBeTrue();
});

test('a certificate that failed before is asked for again', function () {
    fakeMailCertPanel('failed');

    (new PanelicaModule)->createEmail(mailCertService(), 'dom-1', 'info', 'password123');

    expect(issueWasRequested())->toBeTrue();
});

test('a domain that has its certificate is not given another', function (string $state) {
    fakeMailCertPanel($state);

    $result = (new PanelicaModule)->createEmail(mailCertService(), 'dom-1', 'sales', 'password123');

    expect($result['success'])->toBeTrue()->and(issueWasRequested())->toBeFalse();
})->with(['active', 'pending', 'renewing']);

test('a status the panel will not give means no request, and the mailbox stands', function () {
    fakeMailCertPanel('missing', 403);

    $result = (new PanelicaModule)->createEmail(mailCertService(), 'dom-1', 'info', 'password123');

    expect($result['success'])->toBeTrue()->and(issueWasRequested())->toBeFalse();
});

test('no certificate is asked for when the mailbox was not created', function () {
    fakeMailCertPanel('missing', 200, 500);

    $result = (new PanelicaModule)->createEmail(mailCertService(), 'dom-1', 'info', 'password123');

    expect($result['success'])->toBeFalse()->and(issueWasRequested())->toBeFalse();
});
