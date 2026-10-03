<?php

use App\Models\Client;
use App\Models\Domain;
use App\Models\RegistrarSettings;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/*
 * The customer registers the domain's own nameservers (glue records).
 *
 * Nothing in the panel could: a customer running ns1.theirdomain.com had to
 * ask support to register it at the registry.
 */

function glFixture(string $registrar = 'domainnameapi', array $attrs = []): array
{
    RegistrarSettings::updateOrCreate(['registrar' => 'domainnameapi', 'setting' => 'reseller_id'], ['value' => 'reseller-1']);
    RegistrarSettings::updateOrCreate(['registrar' => 'domainnameapi', 'setting' => 'api_key'], ['value' => 'key-1']);
    $user = User::factory()->create();
    $client = Client::factory()->create();
    $user->clients()->attach($client->id, ['owner' => true]);
    $domain = Domain::factory()->create(array_merge([
        'client_id' => $client->id, 'domain' => 'glue-shop.com', 'registrar' => $registrar, 'status' => 'active',
        'expiry_date' => now()->addYear(), 'next_due_date' => now()->addYear(),
    ], $attrs));

    return [$user, $domain];
}

function glInfo(array $hosts = []): array
{
    return ['*domains/info*' => Http::response(['domainName' => 'glue-shop.com', 'hosts' => $hosts])];
}

it('lists the glue records the registry holds', function () {
    Http::fake(glInfo([['name' => 'ns1.glue-shop.com', 'ipAddresses' => [['ipAddress' => '203.0.113.10', 'ipVersion' => 'v4']]]]) + ['*' => Http::response([], 200)]);
    [$user, $domain] = glFixture();

    test()->actingAs($user)->get(route('client.domains.show', $domain))->assertSee(route('client.domains.glue', $domain), false);
    test()->actingAs($user)->get(route('client.domains.glue', $domain))->assertOk()->assertSee('ns1.glue-shop.com')->assertSee('203.0.113.10');
});

it('adds a new record with both addresses, as the registry expects them', function () {
    Http::fake(glInfo() + ['*domains/dns/host*' => Http::response(['success' => true]), '*' => Http::response([], 200)]);
    [$user, $domain] = glFixture();

    test()->actingAs($user)->post(route('client.domains.glue.save', $domain), ['host' => 'NS1', 'ipv4' => '203.0.113.10', 'ipv6' => '2001:db8::10'])
        ->assertRedirect(route('client.domains.glue', $domain))->assertSessionHas('success');

    Http::assertSent(fn (Request $r) => $r->method() === 'POST' && str_contains($r->url(), 'domains/dns/host')
        && $r['hostName'] === 'ns1.glue-shop.com'
        && $r['ipAddresses'] === [['ipAddress' => '203.0.113.10', 'ipVersion' => 'v4'], ['ipAddress' => '2001:db8::10', 'ipVersion' => 'v6']]);
});

it('changes the addresses of a record that exists', function () {
    Http::fake(glInfo([['name' => 'ns1.glue-shop.com', 'ipAddresses' => [['ipAddress' => '203.0.113.10', 'ipVersion' => 'v4']]]]) + ['*domains/dns/host*' => Http::response(['success' => true]), '*' => Http::response([], 200)]);
    [$user, $domain] = glFixture();

    test()->actingAs($user)->post(route('client.domains.glue.save', $domain), ['host' => 'ns1.glue-shop.com', 'ipv4' => '203.0.113.20'])->assertSessionHas('success');

    Http::assertSent(fn (Request $r) => $r->method() === 'PUT' && $r['hostName'] === 'ns1.glue-shop.com' && $r['newHostName'] === 'ns1.glue-shop.com');
});

it('deletes a record with the arguments in the query string', function () {
    Http::fake(['*domains/dns/host*' => Http::response(null, 204), '*' => Http::response([], 200)]);
    [$user, $domain] = glFixture();

    test()->actingAs($user)->delete(route('client.domains.glue.delete', $domain), ['host' => 'ns1'])->assertSessionHas('success');

    Http::assertSent(fn (Request $r) => $r->method() === 'DELETE' && str_contains($r->url(), 'domainName=glue-shop.com') && str_contains($r->url(), 'hostName=ns1.glue-shop.com'));
});

it('reads any name as one under the domain, and refuses a bad label or address before calling the registry', function () {
    Http::fake(glInfo() + ['*domains/dns/host*' => Http::response(['success' => true]), '*' => Http::response([], 200)]);
    [$user, $domain] = glFixture();

    // A registry only takes glue under the domain itself.
    test()->actingAs($user)->post(route('client.domains.glue.save', $domain), ['host' => 'ns1.other.com.', 'ipv4' => '203.0.113.10'])->assertSessionHas('success');
    Http::assertSent(fn (Request $r) => $r->method() === 'POST' && $r['hostName'] === 'ns1.other.com.glue-shop.com');

    test()->actingAs($user)->post(route('client.domains.glue.save', $domain), ['host' => '-bad', 'ipv4' => '203.0.113.10'])->assertSessionHasErrors('host');
    test()->actingAs($user)->post(route('client.domains.glue.save', $domain), ['host' => 'ns2', 'ipv4' => '999.1.1.1'])->assertSessionHasErrors('ipv4');
    Http::assertNotSent(fn (Request $r) => $r->method() === 'POST' && str_contains($r->url(), 'dns/host') && in_array($r['hostName'], ['-bad.glue-shop.com', 'ns2.glue-shop.com'], true));
});

it('says so when the registry refuses, and offers nothing without the capability', function () {
    Http::fake(glInfo() + ['*domains/dns/host*' => Http::response(['error' => ['message' => 'IP not reachable']], 400), '*' => Http::response([], 200)]);
    [$user, $domain] = glFixture();
    test()->actingAs($user)->post(route('client.domains.glue.save', $domain), ['host' => 'ns1', 'ipv4' => '203.0.113.10'])->assertSessionHas('error');

    [$user2, $manual] = glFixture('manual', ['domain' => 'manual-shop.com']);
    test()->actingAs($user2)->get(route('client.domains.show', $manual))->assertDontSee(route('client.domains.glue', $manual), false);
    test()->actingAs($user2)->get(route('client.domains.glue', $manual))->assertRedirect(route('client.domains.show', $manual));
    test()->actingAs($user2)->post(route('client.domains.glue.save', $domain), ['host' => 'ns1', 'ipv4' => '203.0.113.10'])->assertForbidden();
});
