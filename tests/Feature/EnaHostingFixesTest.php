<?php

use App\Models\Client;
use App\Models\Domain;
use App\Models\Order;
use App\Models\Product;
use App\Models\RegistrarSettings;
use App\Models\Server;
use App\Models\Service;
use App\Models\Setting;
use App\Models\User;
use App\Services\Module\ModuleRegistry;
use App\Services\NotificationService;
use App\Services\OrderService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Modules\Registrars\DomainNameApi\DomainNameApiRegistrar;

/*
 * Fixes ENA Hosting found running PNLCS in production and made on their own
 * install on 2026-09-24, taken into PNLCS so every install has them:
 *
 *  1. A paid domain was marked active without being registered: the
 *     DomainNameAPI module was never registered, and a registrar that cannot
 *     be loaded was treated as no registrar at all.
 *  2. Customers could not change their nameservers: no form posted to the
 *     route that does it.
 *  3. DomainNameAPI refused every nameserver change (POST instead of PUT),
 *     and read its empty success answer as a failure.
 *  4. A seller in Turkey could not take an order: checkout required a phone
 *     number it never asked for.
 *  5. Turkish error messages named fields in English.
 *  6. "Set up on my hosting", generalised from their Panelica-only button.
 */

function enaDnaSettings(): void
{
    RegistrarSettings::updateOrCreate(['registrar' => 'domainnameapi', 'setting' => 'reseller_id'], ['value' => 'reseller-1']);
    RegistrarSettings::updateOrCreate(['registrar' => 'domainnameapi', 'setting' => 'api_key'], ['value' => 'key-1']);
}

function enaPendingOrderDomain(string $registrar): Domain
{
    $order = Order::factory()->create(['status' => 'Pending']);

    return Domain::create([
        'client_id' => $order->client_id, 'order_id' => $order->id,
        'domain' => 'paid-example.com', 'type' => 'Register', 'registrar' => $registrar,
        'status' => 'pending', 'registration_period' => 1,
        'first_payment_amount' => 10, 'recurring_amount' => 10,
    ]);
}

// 1 ─────────────────────────────────────────────────────────────────────────

test('the DomainNameAPI registrar can be loaded', function () {
    expect(app(ModuleRegistry::class)->getRegistrarModule('domainnameapi'))
        ->toBeInstanceOf(DomainNameApiRegistrar::class);
});

test('a paid domain whose registrar cannot be loaded waits, and the operator is told', function () {
    $notify = $this->spy(NotificationService::class);
    $domain = enaPendingOrderDomain('nosuchregistrar');

    app(OrderService::class)->acceptOrder($domain->order, true);

    expect($domain->fresh()->status)->toBe('pending');
    $notify->shouldHaveReceived('dispatch')->withArgs(fn ($event, $data) => $event === 'domain.registration_failed'
        && str_contains($data['message'], 'paid-example.com') && str_contains($data['message'], 'nosuchregistrar'));
});

test('a domain with no registrar at all is still recorded as active', function () {
    $domain = enaPendingOrderDomain('');

    app(OrderService::class)->acceptOrder($domain->order, true);

    expect($domain->fresh()->status)->toBe('active');
});

test('the daily sync raises an active domain its registrar does not know, once a week', function () {
    enaDnaSettings();
    Http::fake(['*domains/info*' => Http::response(['message' => 'Domain could not be found'], 404)]);
    $notify = $this->spy(NotificationService::class);

    $domain = Domain::create([
        'client_id' => Client::factory()->create()->id, 'domain' => 'ghost-example.com', 'type' => 'Register',
        'registrar' => 'domainnameapi', 'status' => 'active', 'registration_period' => 1,
        'first_payment_amount' => 10, 'recurring_amount' => 10,
    ]);

    $this->artisan('pnlcs:domain-sync')->assertExitCode(0);
    $this->artisan('pnlcs:domain-sync')->assertExitCode(0);

    $notify->shouldHaveReceived('dispatch')->once()->withArgs(fn ($event, $data) => $event === 'domain.registration_failed'
        && str_contains($data['message'], 'ghost-example.com') && str_contains($data['message'], 'could not be found'));
    expect($domain->fresh()->status)->toBe('active');
});

// 2 and 3 ───────────────────────────────────────────────────────────────────

function enaClientWithDomain(string $registrar = ''): array
{
    $client = Client::factory()->create();
    $user = User::factory()->create();
    $user->clients()->attach($client->id, ['owner' => true]);
    $domain = Domain::create([
        'client_id' => $client->id, 'domain' => 'mine-example.com', 'type' => 'Register',
        'registrar' => $registrar, 'status' => 'active', 'registration_period' => 1,
        'first_payment_amount' => 10, 'recurring_amount' => 10,
        'nameservers' => json_encode(['ns1.old-example.com', 'ns2.old-example.com']),
    ]);

    return [$user, $client, $domain];
}

test('the domain page lets the customer change the nameservers', function () {
    [$user, , $domain] = enaClientWithDomain();

    $this->actingAs($user)->get(route('client.domains.show', $domain))
        ->assertOk()
        ->assertSee(route('client.domains.nameservers', $domain), false)
        ->assertSee('value="ns1.old-example.com"', false);

    $this->actingAs($user)->put(route('client.domains.nameservers', $domain), ['ns1' => 'ns1.new-example.com', 'ns2' => 'ns2.new-example.com'])
        ->assertRedirect(route('client.domains.show', $domain));

    expect($domain->fresh()->nameserverList())->toBe(['ns1.new-example.com', 'ns2.new-example.com']);
});

test('DomainNameAPI changes nameservers with PUT and takes an empty answer as success', function () {
    enaDnaSettings();
    Http::fake(['*domains/dns/name-server*' => Http::response('', 204)]);
    [, , $domain] = enaClientWithDomain('domainnameapi');

    expect((new DomainNameApiRegistrar)->saveNameservers($domain, ['ns1.x-example.com', 'ns2.x-example.com']))->toBeTrue();

    Http::assertSent(fn ($request) => $request->method() === 'PUT' && str_contains($request->url(), 'domains/dns/name-server'));
});

// 4 and 5 ───────────────────────────────────────────────────────────────────

test('checkout asks a buyer in Turkey for the phone number it requires', function () {
    Setting::set('Country', 'TR', 'general');
    $html = view('client.partials.billing-address-fields', ['countries' => ['TR' => 'Turkey'], 'errors' => new Illuminate\Support\ViewErrorBag])->render();
    expect(substr_count($html, 'name="phone_number"'))->toBe(1);

    // The registration page has a phone field of its own: not a second one.
    $register = $this->get(route('client.register'))->assertOk()->getContent();
    expect(substr_count($register, 'name="phone_number"'))->toBe(1);

    Setting::set('Country', 'DE', 'general');
    $html = view('client.partials.billing-address-fields', ['countries' => ['DE' => 'Germany'], 'errors' => new Illuminate\Support\ViewErrorBag])->render();
    expect($html)->not->toContain('name="phone_number"');
});

test('Turkish error messages name the field in Turkish', function () {
    app()->setLocale('tr');

    $message = Validator::make([], ['phone_number' => 'required'])->errors()->first('phone_number');

    expect($message)->toContain('telefon numarası')->not->toContain('phone number');
});

// 6 ─────────────────────────────────────────────────────────────────────────

function enaHosting(Client $client, array $nameservers = ['ns1.host-example.com', 'ns2.host-example.com']): Service
{
    $server = Server::create([
        'name' => 'Panel', 'hostname' => 'panel.test', 'ip_address' => '10.0.0.4', 'type' => 'panelica',
        'username' => 'u', 'password' => 'pk', 'access_hash' => 'sk', 'port' => 8443, 'active' => true,
        'nameserver1' => $nameservers[0] ?? null, 'nameserver2' => $nameservers[1] ?? null,
    ]);

    return Service::factory()->create([
        'client_id' => $client->id, 'server_id' => $server->id, 'status' => 'active',
        'product_id' => Product::factory()->create(['server_type' => 'panelica'])->id,
        'module_data' => ['panelica_user_id' => 'acct-1'],
    ]);
}

function enaFakePanel(array $onAccount = []): void
{
    Http::fake(function ($request) use ($onAccount) {
        if (str_contains($request->url(), '/v1/accounts/acct-1/domains')) {
            return Http::response(['data' => array_map(fn ($n) => ['id' => 'd-'.$n, 'domain_name' => $n, 'php_version' => '8.2', 'web_server' => 'apache'], $onAccount)], 200);
        }
        if ($request->method() === 'POST' && str_ends_with(parse_url($request->url(), PHP_URL_PATH), '/v1/domains')) {
            return Http::response(['data' => ['id' => 'd-new']], 201);
        }

        return Http::response([], 404);
    });
}

test('setting a domain up on the hosting adds it to the account and points it there', function () {
    [$user, $client, $domain] = enaClientWithDomain();
    $service = enaHosting($client);
    enaFakePanel(['existing-example.com']);

    $this->actingAs($user)->get(route('client.domains.show', $domain))->assertOk()
        ->assertSee(route('client.domains.attach-hosting', $domain), false)
        ->assertSee('ns1.host-example.com, ns2.host-example.com');

    $this->actingAs($user)->post(route('client.domains.attach-hosting', $domain), ['service_id' => $service->id])
        ->assertSessionHas('success', __('client.domains.attach_success'));

    Http::assertSent(fn ($r) => $r->method() === 'POST' && str_ends_with(parse_url($r->url(), PHP_URL_PATH), '/v1/domains')
        && $r['name'] === 'mine-example.com' && $r['user_id'] === 'acct-1'
        && $r['php_version'] === '8.2' && $r['web_server'] === 'apache');
    expect($domain->fresh()->nameserverList())->toBe(['ns1.host-example.com', 'ns2.host-example.com']);
});

test('a domain already on the account is not added twice, and the page says it is set up', function () {
    [$user, $client, $domain] = enaClientWithDomain();
    $service = enaHosting($client);
    $domain->update(['nameservers' => json_encode(['ns1.host-example.com', 'ns2.host-example.com'])]);
    enaFakePanel(['mine-example.com']);

    $this->actingAs($user)->get(route('client.domains.show', $domain))->assertOk()
        ->assertSee(__('client.domains.attach_already'));

    $this->actingAs($user)->post(route('client.domains.attach-hosting', $domain), ['service_id' => $service->id])
        ->assertSessionHas('success');

    Http::assertNotSent(fn ($r) => $r->method() === 'POST');
});

test('another customer\'s hosting account cannot be named', function () {
    [$user, , $domain] = enaClientWithDomain();
    $theirs = enaHosting(Client::factory()->create());
    enaFakePanel();

    $this->actingAs($user)->post(route('client.domains.attach-hosting', $domain), ['service_id' => $theirs->id])
        ->assertSessionHas('error', __('client.domains.attach_no_hosting'));

    Http::assertNotSent(fn ($r) => $r->method() === 'POST');
});

test('a customer without hosting sees no setup button', function () {
    [$user, , $domain] = enaClientWithDomain();

    $this->actingAs($user)->get(route('client.domains.show', $domain))->assertOk()
        ->assertDontSee(route('client.domains.attach-hosting', $domain), false);
});

test('an install that does not use DomainNameAPI is not warned about its balance', function () {
    // The balance watch defaults to DomainNameAPI. Now that the module loads,
    // an unconfigured one must not mail "balance unreadable" every day.
    $notify = $this->spy(NotificationService::class);
    Http::fake();

    $this->artisan('pnlcs:registrar-balance')->assertExitCode(0);

    Http::assertNothingSent();
    $notify->shouldNotHaveReceived('dispatch');
});
