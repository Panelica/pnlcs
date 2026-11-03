<?php

use App\Models\Client;
use App\Models\Currency;
use App\Models\CustomField;
use App\Models\Domain;
use App\Models\DomainPricing;
use App\Models\ModuleLog;
use App\Models\Order;
use App\Models\RegistrarSettings;
use Illuminate\Support\Facades\Http;
use Modules\Registrars\OVH\OvhRegistrar;

// A call this file does not stub must fail here, not reach api.ovh.com: that
// is how the sync test passed with half its endpoints faked.
beforeEach(fn () => Http::preventStrayRequests());

function ovhSettings(): void
{
    RegistrarSettings::updateOrCreate(['registrar' => 'ovh', 'setting' => 'app_key'], ['value' => 'key']);
    RegistrarSettings::updateOrCreate(['registrar' => 'ovh', 'setting' => 'app_secret'], ['value' => 'secret']);
    RegistrarSettings::updateOrCreate(['registrar' => 'ovh', 'setting' => 'consumer_key'], ['value' => 'consumer']);
}

function ovhDomain(array $attrs = []): Domain
{
    return Domain::factory()->create(array_merge([
        'client_id' => Client::factory()->create()->id,
        'domain' => 'shop.example.com',
        'registrar' => 'ovh',
        'status' => 'active',
        'expiry_date' => now()->addYear(),
        'next_due_date' => now()->addYear(),
    ], $attrs));
}

it('reports a working connection', function () {
    ovhSettings();
    Http::fake(['*/me' => Http::response(['nichandle' => 'ab123-ovh'], 200)]);

    $result = app(OvhRegistrar::class)->testConnection();

    expect($result['success'])->toBeTrue()
        ->and($result['message'])->toContain('ab123-ovh');
});

it('reports a failed connection', function () {
    ovhSettings();
    Http::fake(['*/me' => Http::response(['message' => 'Not credited'], 403)]);

    $result = app(OvhRegistrar::class)->testConnection();

    expect($result['success'])->toBeFalse();
});

it('checks availability through the order cart', function () {
    ovhSettings();
    Http::fake([
        '*/order/cart' => Http::response(['cartId' => 'cart-1'], 200),
        '*/order/cart/cart-1/domain*' => Http::response([['pricingMode' => 'create-default', 'offerId' => 'off-1']], 200),
    ]);

    $result = app(OvhRegistrar::class)->checkAvailability('shop.example.com');

    expect($result['available'])->toBeTrue();
});

it('marks a transfer-only domain as unavailable for registration', function () {
    ovhSettings();
    Http::fake([
        '*/order/cart' => Http::response(['cartId' => 'cart-1'], 200),
        '*/order/cart/cart-1/domain*' => Http::response([['pricingMode' => 'transfer-default', 'offerId' => 'off-2']], 200),
    ]);

    $result = app(OvhRegistrar::class)->checkAvailability('shop.example.com');

    expect($result['available'])->toBeFalse();
});

it('reads the expiry and status when syncing a domain', function () {
    ovhSettings();
    // The expiry is on serviceInfos, not on the domain itself: two calls, two
    // stubs. The order matters — the more specific pattern first.
    Http::fake([
        '*/domain/shop.example.com/serviceInfos' => Http::response([
            'expiration' => '2027-06-01T10:00:00+02:00',
        ], 200),
        '*/domain/shop.example.com' => Http::response([
            'status' => 'ok',
            'transferLockStatus' => 'locked',
            'nameServers' => ['ns1.ovh.net', 'ns2.ovh.net'],
        ], 200),
    ]);

    $result = app(OvhRegistrar::class)->syncDomain(ovhDomain());

    expect($result['success'])->toBeTrue()
        ->and($result['expiry_date'])->toBe('2027-06-01')
        ->and($result['status'])->toBe('active')
        ->and($result['locked'])->toBeTrue();
});

it('renews a domain at OVH', function () {
    ovhSettings();
    Http::fake(['*/domain/shop.example.com/renew' => Http::response([], 200)]);

    $result = app(OvhRegistrar::class)->renew(ovhDomain(), 1);

    expect($result['success'])->toBeTrue();
});

it('returns the failure message when OVH refuses a renewal', function () {
    ovhSettings();
    Http::fake(['*/domain/shop.example.com/renew' => Http::response(['message' => 'Insufficient balance'], 400)]);

    $result = app(OvhRegistrar::class)->renew(ovhDomain(), 1);

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('Insufficient balance');
});

it('fetches the auth info code with a GET request', function () {
    ovhSettings();
    Http::fake(['*/domain/shop.example.com/authInfo' => Http::response('"ABC-123"', 200)]);

    $code = app(OvhRegistrar::class)->getEPPCode(ovhDomain());

    expect($code)->toBe('ABC-123');

    Http::assertSent(fn ($request) => $request->method() === 'GET'
        && str_ends_with($request->url(), '/domain/shop.example.com/authInfo'));
});

it('reports an unavailable auth code when OVH returns none', function () {
    ovhSettings();
    Http::fake(['*/domain/shop.example.com/authInfo' => Http::response(null, 200)]);

    expect(app(OvhRegistrar::class)->getEPPCode(ovhDomain()))->toBe('(unavailable)');
});

it('reads the transfer lock status from the domain info', function () {
    ovhSettings();
    Http::fake(['*/domain/shop.example.com' => Http::response(['transferLockStatus' => 'unlocked'], 200)]);

    expect(app(OvhRegistrar::class)->getLockStatus(ovhDomain()))->toBeFalse();
});

it('toggles the transfer lock with a PUT on the domain', function () {
    ovhSettings();
    Http::fake(['*/domain/shop.example.com' => Http::response([], 200)]);

    $result = app(OvhRegistrar::class)->toggleLock(ovhDomain(), false);

    expect($result)->toBeTrue();

    Http::assertSent(fn ($request) => $request->method() === 'PUT'
        && str_ends_with($request->url(), '/domain/shop.example.com')
        && (json_decode($request->body(), true)['transferLockStatus'] ?? null) === 'unlocked');
});

it('updates nameservers through the nameServers/update route', function () {
    ovhSettings();
    Http::fake(['*/domain/shop.example.com/nameServers/update' => Http::response([], 200)]);

    $result = app(OvhRegistrar::class)->saveNameservers(ovhDomain(), ['ns1.ovh.net', 'ns2.ovh.net']);

    expect($result)->toBeTrue();

    Http::assertSent(function ($request) {
        $body = json_decode($request->body(), true);

        return str_ends_with($request->url(), '/domain/shop.example.com/nameServers/update')
            && ($body['nameServers'] ?? null) === [['host' => 'ns1.ovh.net'], ['host' => 'ns2.ovh.net']];
    });
});

it('maps OVH name server objects back to hostnames', function () {
    ovhSettings();
    Http::fake(['*/domain/shop.example.com' => Http::response([
        'nameServers' => [
            ['id' => 1, 'nameServerType' => 'external', 'nameServer' => 'ns2.ovh.net'],
            ['id' => 2, 'nameServerType' => 'external', 'nameServer' => 'ns1.ovh.net'],
        ],
    ], 200)]);

    expect(app(OvhRegistrar::class)->getNameservers(ovhDomain()))
        ->toBe(['ns2.ovh.net', 'ns1.ovh.net']);
});

/*
 * The price sync. OVH quotes in the account's currency and does not quote
 * every price for every TLD, and the panel's price columns are
 * `decimal NOT NULL` — so both have to be handled before a number is written.
 */
function ovhCartFake(array $prices, string $currency = 'PLN'): void
{
    Http::fake([
        '*/order/cart/cart-1/domain*' => Http::sequence()
            // The availability lookup, then the item that carries the prices.
            ->push([['pricingMode' => 'create-default', 'offerId' => 'off-1']], 200)
            ->push(['prices' => array_map(fn (array $p) => [
                'label' => $p[0],
                'price' => ['value' => $p[1], 'currencyCode' => $currency],
            ], $prices)], 200),
        '*/order/cart' => Http::response(['cartId' => 'cart-1'], 200),
    ]);
}

function ovhTld(string $extension, string $registrar = 'ovh', array $prices = []): DomainPricing
{
    return DomainPricing::create(array_merge([
        'extension' => $extension,
        'auto_registrar' => $registrar,
        'register_price' => 1,
        'renew_price' => 1,
        'transfer_price' => 1,
    ], $prices));
}

function ovhShop(string $code): void
{
    Currency::query()->update(['is_default' => false]);
    Currency::updateOrCreate(['code' => $code], ['name' => $code, 'prefix' => '', 'suffix' => ' '.$code, 'rate' => 1.0, 'is_default' => true]);
}

it('writes the prices OVH quotes, with the margin, rounded up', function () {
    ovhSettings();
    ovhShop('PLN');
    $tld = ovhTld('.pl');
    RegistrarSettings::updateOrCreate(['registrar' => 'ovh', 'setting' => 'margin_percent'], ['value' => '20']);
    ovhCartFake([['FEE', 46.0], ['RENEW', 50.0]]);

    $result = app(OvhRegistrar::class)->syncPrices();

    expect($result['success'])->toBeTrue()
        ->and($result['updated'])->toBe(1)
        ->and((float) $tld->fresh()->register_price)->toBe(56.0)   // 46 * 1.2 → 55.2 → 56
        ->and((float) $tld->fresh()->renew_price)->toBe(60.0)
        // OVH quoted no transfer price: the operator's own number stays, and
        // the column (decimal NOT NULL) is never handed a null.
        ->and((float) $tld->fresh()->transfer_price)->toBe(1.0);
});

it('converts foreign-currency prices into the shop currency', function () {
    ovhSettings();
    ovhShop('USD');
    Currency::create(['code' => 'PLN', 'name' => 'PLN', 'prefix' => '', 'suffix' => ' PLN', 'rate' => 2.0, 'is_default' => false]);
    RegistrarSettings::updateOrCreate(['registrar' => 'ovh', 'setting' => 'margin_percent'], ['value' => '20']);
    $tld = ovhTld('.pl');
    ovhCartFake([['FEE', 46.0]], 'PLN');

    $result = app(OvhRegistrar::class)->syncPrices();

    // 46 PLN @ 2 PLN/USD = 23 USD; +20% → 27.6 → 28.
    expect($result['success'])->toBeTrue()
        ->and((float) $tld->fresh()->register_price)->toBe(28.0);
});

it('leaves another registrar\'s TLDs alone', function () {
    ovhSettings();
    ovhShop('PLN');
    $other = ovhTld('.de', 'hrd');
    Http::fake();

    $result = app(OvhRegistrar::class)->syncPrices();

    expect($result)->toMatchArray(['success' => true, 'updated' => 0, 'unchanged' => 0, 'skipped' => 0])
        ->and((float) $other->fresh()->register_price)->toBe(1.0);
});

/*
 * The registration flow. OVH orders asynchronously through the cart, so the
 * interesting part is how the owner contact is created and attached, and what
 * the panel records afterwards.
 */

function ovhIndividualClient(array $attrs = []): Client
{
    return Client::factory()->create(array_merge([
        'company_name' => null,
        'client_type' => 'individual',
        'first_name' => 'Jan',
        'last_name' => 'Kowalski',
        'email' => 'jan@example.com',
        'address1' => 'ul. Testowa 1',
        'city' => 'Warszawa',
        'postcode' => '00-001',
        'country' => 'PL',
        'phone_number' => '600 100 200',
    ], $attrs));
}

function ovhOrderDomain(array $attrs = []): Domain
{
    return Domain::factory()->create(array_merge([
        'client_id' => ovhIndividualClient()->id,
        'domain' => 'shop.example.com',
        'registrar' => 'ovh',
        'status' => 'pending',
    ], $attrs));
}

function ovhRegistrationFake(array $required, string $contactId = '12345', array $pricingModes = ['create-default']): void
{
    Http::fake([
        '*/order/cart' => Http::sequence()
            ->push(['cartId' => 'cart-offer'], 200)
            ->push(['cartId' => 'cart-order'], 200),
        '*/order/cart/cart-offer/domain*' => Http::response(array_map(fn ($m) => ['pricingMode' => $m, 'offerId' => 'off-1'], $pricingModes), 200),
        '*/me/contact' => Http::response(['id' => (int) $contactId], 200),
        '*/order/cart/cart-order/domain' => Http::response(['itemId' => 999], 200),
        '*/order/cart/cart-order/item/999/requiredConfiguration' => Http::response($required, 200),
        '*/order/cart/cart-order/item/999/configuration' => Http::response([], 200),
        '*/order/cart/cart-order/assign' => Http::response([], 200),
        '*/order/cart/cart-order/checkout' => Http::response(['orderId' => 789], 200),
    ]);
}

it('places an order with the client as owner contact and records the order', function () {
    ovhSettings();
    ovhRegistrationFake([
        ['label' => 'OWNER_CONTACT', 'type' => '/me/contact'],
        ['label' => 'OWNER_LEGAL_AGE', 'type' => 'bool'],
        ['label' => 'ADMIN_ACCOUNT', 'type' => 'Nichandle'],
        ['label' => 'TECH_ACCOUNT', 'type' => 'Nichandle'],
    ]);

    $domain = ovhOrderDomain();
    $result = app(OvhRegistrar::class)->register($domain, 1);

    expect($result['success'])->toBeTrue()
        ->and($result['status'])->toBe('pending')
        ->and($domain->fresh()->registrar_contact_id)->toBe('12345')
        ->and($domain->fresh()->status)->toBe('pending');

    // The owner is the client's contact, sent as the full resource path.
    Http::assertSent(fn ($r) => str_ends_with($r->url(), '/configuration')
        && (json_decode($r->body(), true)['label'] ?? null) === 'OWNER_CONTACT'
        && (json_decode($r->body(), true)['value'] ?? null) === '/me/contact/12345');

    // The boolean gate is filled with a boolean, not the contact id.
    Http::assertSent(fn ($r) => str_ends_with($r->url(), '/configuration')
        && (json_decode($r->body(), true)['label'] ?? null) === 'OWNER_LEGAL_AGE'
        && (json_decode($r->body(), true)['value'] ?? null) === 'true');

    // The admin/tech accounts stay the operator's own: never configured.
    Http::assertNotSent(fn ($r) => str_ends_with($r->url(), '/configuration')
        && in_array(json_decode($r->body(), true)['label'] ?? null, ['ADMIN_ACCOUNT', 'TECH_ACCOUNT'], true));
});

it('accepts registry conditions and other boolean gates', function () {
    ovhSettings();
    ovhRegistrationFake([
        ['label' => 'OWNER_CONTACT', 'type' => '/me/contact'],
        ['label' => 'ACCEPT_CONDITIONS', 'type' => 'bool'],
    ]);

    app(OvhRegistrar::class)->register(ovhOrderDomain(), 1);

    Http::assertSent(fn ($r) => str_ends_with($r->url(), '/configuration')
        && (json_decode($r->body(), true)['label'] ?? null) === 'ACCEPT_CONDITIONS'
        && (json_decode($r->body(), true)['value'] ?? null) === 'true');
});

it('fails loudly on a configuration it cannot fill', function () {
    ovhSettings();
    ovhRegistrationFake([
        ['label' => 'OWNER_CONTACT', 'type' => '/me/contact'],
        ['label' => 'SOME_REGISTRY_FIELD', 'type' => 'string'],
    ]);

    $result = app(OvhRegistrar::class)->register(ovhOrderDomain(), 1);

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('SOME_REGISTRY_FIELD');
});

it('reuses the stored OVH contact for the same client', function () {
    ovhSettings();

    $client = ovhIndividualClient();
    $field = CustomField::create([
        'type' => 'client', 'field_name' => 'ovh_contact_id', 'field_type' => 'text',
    ]);
    $field->values()->create(['rel_id' => $client->id, 'value' => '99999']);

    $domain = Domain::factory()->create([
        'client_id' => $client->id,
        'domain' => 'shop.example.com',
        'registrar' => 'ovh',
        'status' => 'pending',
    ]);

    Http::fake([
        '*/order/cart' => Http::sequence()
            ->push(['cartId' => 'cart-offer'], 200)
            ->push(['cartId' => 'cart-order'], 200),
        '*/order/cart/cart-offer/domain*' => Http::response([['pricingMode' => 'create-default', 'offerId' => 'off-1']], 200),
        '*/order/cart/cart-order/domain' => Http::response(['itemId' => 999], 200),
        '*/order/cart/cart-order/item/999/requiredConfiguration' => Http::response([
            ['label' => 'OWNER_CONTACT', 'type' => '/me/contact'],
        ], 200),
        '*/order/cart/cart-order/item/999/configuration' => Http::response([], 200),
        '*/order/cart/cart-order/assign' => Http::response([], 200),
        '*/order/cart/cart-order/checkout' => Http::response(['orderId' => 789], 200),
    ]);

    $result = app(OvhRegistrar::class)->register($domain, 1);

    expect($result['success'])->toBeTrue()
        ->and($domain->fresh()->registrar_contact_id)->toBe('99999');

    // The stored contact was reused: no new contact was created at OVH.
    Http::assertNotSent(fn ($r) => str_ends_with($r->url(), '/me/contact'));

    Http::assertSent(fn ($r) => str_ends_with($r->url(), '/configuration')
        && (json_decode($r->body(), true)['value'] ?? null) === '/me/contact/99999');
});

it('refuses to create a contact from an incomplete client', function () {
    ovhSettings();
    Http::fake([
        '*/order/cart' => Http::response(['cartId' => 'cart-offer'], 200),
        '*/order/cart/cart-offer/domain*' => Http::response([['pricingMode' => 'create-default', 'offerId' => 'off-1']], 200),
    ]);

    $domain = ovhOrderDomain(['client_id' => ovhIndividualClient(['address1' => null])->id]);

    $result = app(OvhRegistrar::class)->register($domain, 1);

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('missing address');

    Http::assertNotSent(fn ($r) => str_ends_with($r->url(), '/me/contact'));
});

it('reads the owner contact back when syncing a domain', function () {
    ovhSettings();
    Http::fake([
        '*/domain/shop.example.com/serviceInfos' => Http::response(['expiration' => '2027-06-01T10:00:00+02:00'], 200),
        '*/domain/shop.example.com' => Http::response([
            'status' => 'ok',
            'transferLockStatus' => 'locked',
            'nameServers' => [],
            'contactOwner' => ['id' => '28729887'],
        ], 200),
    ]);

    $result = app(OvhRegistrar::class)->syncDomain(ovhOrderDomain());

    expect($result['success'])->toBeTrue()
        ->and($result['registrar_contact_id'])->toBe('28729887');
});

/*
 * domains.order_id is the PNLCS order the domain was bought on — OrderService
 * finds an order's domains through it, and the completion step that activates
 * them reads exactly that. OVH's own order number is not that, and writing it
 * there detached the domain from the order it came from.
 */
it('keeps the domain attached to the PNLCS order it was bought on', function () {
    ovhSettings();
    ovhRegistrationFake([
        ['label' => 'OWNER_CONTACT', 'type' => '/me/contact'],
        ['label' => 'OWNER_LEGAL_AGE', 'type' => 'bool'],
    ]);

    $client = ovhIndividualClient();
    $order = Order::factory()->create(['client_id' => $client->id]);
    $domain = ovhOrderDomain(['client_id' => $client->id, 'order_id' => $order->id]);

    $result = app(OvhRegistrar::class)->register($domain, 1);

    expect($result['success'])->toBeTrue()
        ->and($domain->fresh()->order_id)->toBe($order->id)
        // Which is what the order's own queries need.
        ->and(Domain::where('order_id', $order->id)->pluck('id')->all())->toBe([$domain->id]);

    // OVH's order number is still recorded, in the module log and the message.
    expect($result['message'])->toContain('789')
        ->and(ModuleLog::where('action', 'register')->latest('id')->value('request'))->toContain('789');
});

it('keeps it attached on a transfer too', function () {
    ovhSettings();
    ovhRegistrationFake([
        ['label' => 'OWNER_CONTACT', 'type' => '/me/contact'],
    ], '12345', ['transfer-default']);

    $client = ovhIndividualClient();
    $order = Order::factory()->create(['client_id' => $client->id]);
    $domain = ovhOrderDomain(['client_id' => $client->id, 'order_id' => $order->id]);

    $result = app(OvhRegistrar::class)->transfer($domain, 'EPP-CODE');

    expect($result['success'])->toBeTrue()
        ->and($domain->fresh()->order_id)->toBe($order->id)
        ->and($domain->fresh()->registrar_contact_id)->toBe('12345');
});

it('imports the OVH TLDs without duplicating existing extensions', function () {
    ovhSettings();
    Http::fake(['*/domain/extensions' => Http::response(['com', 'net', 'pl'], 200)]);

    DomainPricing::create(['extension' => '.com', 'auto_registrar' => 'hrd']);

    $result = app(OvhRegistrar::class)->importTlds();

    expect($result)->toMatchArray(['success' => true, 'added' => 2, 'skipped' => 1]);

    $net = DomainPricing::where('extension', '.net')->first();
    expect($net->auto_registrar)->toBe('ovh')
        ->and($net->min_years)->toBe(1)
        ->and($net->max_years)->toBe(10)
        ->and($net->grace_period)->toBe(5)
        ->and($net->redemption_grace_period)->toBe(30)
        ->and($net->category)->toBe('generic')
        // Off until it is priced, as for HRD.
        ->and((bool) $net->enabled)->toBeFalse();

    // .com already existed on another registrar: left alone.
    expect(DomainPricing::where('extension', '.com')->value('auto_registrar'))->toBe('hrd');
});
