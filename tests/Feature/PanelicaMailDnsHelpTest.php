<?php

use App\Models\Client;
use App\Models\Product;
use App\Models\Server;
use App\Models\Service;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Modules\Servers\Panelica\PanelicaModule;

/*
 * The service domain's mail certificate, as the panel reports it:
 * - the host name it is for is the one mail clients are told;
 * - when it could not be issued because the domain's DNS is elsewhere, the
 *   customer is shown the records to add and can ask for it again.
 */

function dnsHelpService(array $server = [], string $domain = 'shop.example.com'): array
{
    $srv = Server::create(array_merge([
        'name' => 'Panel', 'hostname' => 's1.example.net', 'ip_address' => '10.0.0.9',
        'type' => 'panelica', 'username' => 'u', 'password' => 'pk', 'access_hash' => 'sk',
        'port' => 8443, 'active' => true,
    ], $server));
    $client = Client::factory()->create();
    $user = User::factory()->create();
    $user->clients()->attach($client->id);
    $product = Product::factory()->create(['server_type' => 'panelica']);
    $service = Service::factory()->create([
        'client_id' => $client->id, 'product_id' => $product->id, 'server_id' => $srv->id, 'domain' => $domain,
        'status' => 'active', 'module_data' => ['panelica_user_id' => 'acct-1', 'panelica_domain_id' => 'dom-1'],
    ]);

    return [$user, $service];
}

/** The panel, with the domain's mail certificate in the given state. */
function fakeCertPanel(?array $cert): void
{
    Http::fake(function (Request $request) use ($cert) {
        $url = $request->url();
        if (str_ends_with($url, '/v1/ssl/domains/dom-1/mail') && $request->method() === 'GET') {
            return $cert === null ? Http::response(['status' => 'error'], 404) : Http::response(['status' => 'success', 'data' => $cert]);
        }
        if (str_ends_with($url, '/v1/ssl/domains/dom-1/mail/issue')) {
            return Http::response(['status' => 'success', 'data' => ['status' => 'pending']], 202);
        }
        if (str_contains($url, '/v1/accounts/acct-1/domains')) {
            return Http::response(['data' => [['id' => 'dom-1', 'domain_name' => 'shop.example.com']]]);
        }
        if (str_contains($url, '/v1/email-accounts') && $request->method() === 'GET') {
            return Http::response(['data' => [['id' => 'm1', 'email' => 'info@shop.example.com', 'domain_id' => 'dom-1', 'quota_mb' => 1024, 'used_quota_mb' => 0, 'status' => 'active']]]);
        }

        return Http::response(['data' => []]);
    });
}

test('mail clients are told the host name the panel gave the certificate', function () {
    fakeCertPanel(['status' => 'active', 'hostname' => 'post.shop.example.com']);
    [, $service] = dnsHelpService();

    expect((new PanelicaModule)->mailHostname($service))->toBe('post.shop.example.com');
});

test('without a host name from the panel it is mail. plus the domain', function (?array $cert) {
    fakeCertPanel($cert);
    [, $service] = dnsHelpService();

    expect((new PanelicaModule)->mailHostname($service))->toBe('mail.shop.example.com');
})->with(['no hostname' => [['status' => 'missing']], 'status unavailable' => [null]]);

test('a failed certificate asks for CNAMEs to the server\'s hostname', function () {
    fakeCertPanel(['status' => 'failed', 'hostname' => 'mail.shop.example.com', 'last_error' => 'NXDOMAIN']);
    [, $service] = dnsHelpService();

    expect((new PanelicaModule)->mailDnsHelp($service))->toBe([
        'host' => 'mail.shop.example.com',
        'records' => [
            ['name' => 'mail.shop.example.com', 'type' => 'CNAME', 'value' => 's1.example.net'],
            ['name' => 'autoconfig.shop.example.com', 'type' => 'CNAME', 'value' => 's1.example.net'],
            ['name' => 'autodiscover.shop.example.com', 'type' => 'CNAME', 'value' => 's1.example.net'],
        ],
    ]);
});

test('a server known only by a public address gets A records, a private one gets nothing', function () {
    fakeCertPanel(['status' => 'failed']);
    [, $public] = dnsHelpService(['hostname' => '', 'ip_address' => '203.0.113.7']);
    [, $private] = dnsHelpService(['hostname' => '', 'ip_address' => '10.0.0.9']);

    expect((new PanelicaModule)->mailDnsHelp($public)['records'][0])->toBe(['name' => 'mail.shop.example.com', 'type' => 'A', 'value' => '203.0.113.7'])
        ->and((new PanelicaModule)->mailDnsHelp($private))->toBeNull();
});

test('nothing is asked while the certificate is fine or on its way', function (string $status) {
    fakeCertPanel(['status' => $status]);
    [, $service] = dnsHelpService();

    expect((new PanelicaModule)->mailDnsHelp($service))->toBeNull();
})->with(['active', 'pending', 'renewing', 'missing']);

test('the email page shows the records when the certificate failed', function () {
    fakeCertPanel(['status' => 'failed']);
    [$user, $service] = dnsHelpService();

    $this->actingAs($user)->get(route('client.services.emails', $service))
        ->assertOk()
        ->assertSee(__('client.hosting.email.dns_title'))
        ->assertSee('autodiscover.shop.example.com')
        ->assertSee('s1.example.net')
        ->assertSee(route('client.services.emails.certificate', $service), false);
});

test('the email page says nothing when the certificate is active', function () {
    fakeCertPanel(['status' => 'active', 'hostname' => 'mail.shop.example.com']);
    [$user, $service] = dnsHelpService();

    $this->actingAs($user)->get(route('client.services.emails', $service))
        ->assertOk()->assertDontSee(__('client.hosting.email.dns_title'));
});

test('the owner can ask for the certificate again, nobody else can', function () {
    fakeCertPanel(['status' => 'failed']);
    [$user, $service] = dnsHelpService();

    $this->actingAs($user)->from(route('client.services.emails', $service))
        ->post(route('client.services.emails.certificate', $service))
        ->assertRedirect(route('client.services.emails', $service))
        ->assertSessionHas('success', __('client.hosting.email.dns_requested'));
    expect(Http::recorded(fn (Request $r) => str_ends_with($r->url(), '/mail/issue'))->isNotEmpty())->toBeTrue();

    $other = User::factory()->create();
    $other->clients()->attach(Client::factory()->create()->id);
    $this->actingAs($other)->post(route('client.services.emails.certificate', $service))->assertForbidden();
});
