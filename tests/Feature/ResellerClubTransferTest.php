<?php

use App\Models\Client;
use App\Models\Domain;
use App\Services\Module\ModuleRegistry;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/*
 * domains/transfer.json requires the customer, the four contact ids and
 * auto-renew; the module sent only the name, the code and placeholder
 * nameservers, so a ResellerClub transfer could not be placed. The default
 * contacts were read with a GET from a key the API does not answer with,
 * falling back to the customer id, which is never a contact id.
 * (ResellerClub KB answers 752 register, 758 transfer, 794 default contacts.)
 */

function rcClient(): Client
{
    return Client::factory()->create([
        'first_name' => 'Arshan', 'last_name' => 'Test', 'email' => 'arshan@example.com',
        'phone_number' => '+44 20 7946 0000', 'address1' => '1 High Street', 'city' => 'Leeds',
        'state' => 'West Yorkshire', 'postcode' => 'LS1 1AA', 'country' => 'GB',
    ]);
}

function rcFake(): void
{
    $contact = fn (string $id) => ['entity.entityid' => $id, 'contact.name' => 'Arshan Test'];

    Http::fake([
        '*customers/details.json*' => Http::response(['status' => 'ERROR', 'message' => 'Customer not found'], 200),
        '*customers/signup.json*' => Http::response('5555', 200),
        '*contacts/default.json*' => function (Request $request) use ($contact) {
            $type = $request->data()['type'] ?? 'Contact';

            return Http::response([$type => [
                'registrantContactDetails' => $contact('111'),
                'adminContactDetails' => $contact('112'),
                'techContactDetails' => $contact('113'),
                'billingContactDetails' => $contact('114'),
            ]], 200);
        },
        '*' => Http::response(['status' => 'Success', 'entityid' => '999'], 200),
    ]);
}

function rcSent(string $endpoint): array
{
    $found = Http::recorded(fn (Request $r) => str_contains($r->url(), $endpoint));

    return $found->isEmpty() ? [] : $found->last()[0]->data();
}

function rc()
{
    return app(ModuleRegistry::class)->getRegistrarModule('ResellerClub');
}

test('a transfer sends the customer, the four contacts and auto-renew', function () {
    rcFake();
    $domain = Domain::factory()->create(['client_id' => rcClient()->id, 'domain' => 'moving.com', 'registrar' => 'ResellerClub', 'nameservers' => null]);

    $result = rc()->transfer($domain, 'AbC-123');

    $sent = rcSent('domains/transfer.json');
    expect($result['success'])->toBeTrue()
        ->and($sent['customer-id'])->toBe('5555')
        ->and([$sent['reg-contact-id'], $sent['admin-contact-id'], $sent['tech-contact-id'], $sent['billing-contact-id']])->toBe(['111', '112', '113', '114'])
        ->and($sent['auto-renew'])->toBe('false')
        ->and($sent['auth-code'])->toBe('AbC-123')
        ->and($sent)->not->toHaveKey('ns');

    $signup = rcSent('customers/signup.json');
    expect($signup['phone-cc'])->toBe('44')
        ->and($signup['phone'])->toBe('2079460000')
        ->and(rcSent('contacts/default.json')['customer-id'])->toBe('5555');
    Http::assertSent(fn (Request $r) => str_contains($r->url(), 'contacts/default.json') && $r->method() === 'POST');
});

test('a .uk transfer has no admin, tech or billing contact and may go without a code', function () {
    rcFake();
    $domain = Domain::factory()->create(['client_id' => rcClient()->id, 'domain' => 'moving.co.uk', 'registrar' => 'ResellerClub']);

    rc()->transfer($domain, '');

    $sent = rcSent('domains/transfer.json');
    expect($sent['reg-contact-id'])->toBe('111')
        ->and([$sent['admin-contact-id'], $sent['tech-contact-id'], $sent['billing-contact-id']])->toBe(['-1', '-1', '-1'])
        ->and($sent)->not->toHaveKey('auth-code')
        ->and(rcSent('contacts/default.json')['type'])->toBe('UkContact');
});

test('a registration sends real nameservers, the contacts and auto-renew', function () {
    rcFake();
    $client = rcClient();
    $domain = Domain::factory()->create(['client_id' => $client->id, 'domain' => 'new-name.com', 'registrar' => 'ResellerClub']);

    $result = rc()->register($domain, 1, [
        'firstname' => 'Arshan', 'lastname' => 'Test', 'email' => 'arshan@example.com', 'phone' => '+44 20 7946 0000',
        'address' => '1 High Street', 'city' => 'Leeds', 'country' => 'GB',
        'nameservers' => ['ns1.host.example', 'ns2.host.example'],
    ]);

    $sent = rcSent('domains/register.json');
    expect($result['success'])->toBeTrue()
        ->and($sent['ns'])->toBe(['ns1.host.example', 'ns2.host.example'])
        ->and($sent['reg-contact-id'])->toBe('111')
        ->and($sent['auto-renew'])->toBe('false');
});

test('a registration without nameservers is refused, not pointed at a placeholder', function () {
    rcFake();
    $params = [
        'firstname' => 'Arshan', 'lastname' => 'Test', 'email' => 'arshan@example.com', 'phone' => '+44 20 7946 0000',
        'address' => '1 High Street', 'city' => 'Leeds', 'country' => 'GB',
    ];
    $domain = Domain::factory()->create(['client_id' => rcClient()->id, 'domain' => 'no-ns.com', 'registrar' => 'ResellerClub']);

    expect(rc()->register($domain, 1, $params)['success'])->toBeFalse();
    Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'domains/register.json'));
});

test('a customer with no default contact is refused instead of using the customer id', function () {
    Http::fake([
        '*customers/details.json*' => Http::response(['customerid' => '5555'], 200),
        '*contacts/default.json*' => Http::response(['Contact' => []], 200),
        '*' => Http::response(['status' => 'Success'], 200),
    ]);
    $domain = Domain::factory()->create(['client_id' => rcClient()->id, 'domain' => 'no-contact.com', 'registrar' => 'ResellerClub']);

    $result = rc()->register($domain, 1, [
        'firstname' => 'Arshan', 'lastname' => 'Test', 'email' => 'arshan@example.com', 'phone' => '+44 20 7946 0000',
        'address' => '1 High Street', 'city' => 'Leeds', 'country' => 'GB', 'nameservers' => ['ns1.host.example'],
    ]);

    expect($result['success'])->toBeFalse()->and($result['message'])->toContain('no default contact');
    Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'domains/register.json'));
});
