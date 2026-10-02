<?php

use App\Models\Client;
use App\Models\Domain;
use App\Models\RegistrarSettings;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/*
 * The customer switches a domain's WHOIS privacy on and off from its page.
 *
 * The page showed the setting, read back from the registrar, but nothing
 * could change it.
 */

function wpFixture(string $registrar = 'domainnameapi', array $attrs = []): array
{
    RegistrarSettings::updateOrCreate(['registrar' => 'domainnameapi', 'setting' => 'reseller_id'], ['value' => 'reseller-1']);
    RegistrarSettings::updateOrCreate(['registrar' => 'domainnameapi', 'setting' => 'api_key'], ['value' => 'key-1']);
    $user = User::factory()->create();
    $client = Client::factory()->create();
    $user->clients()->attach($client->id, ['owner' => true]);
    $domain = Domain::factory()->create(array_merge([
        'client_id' => $client->id, 'domain' => 'quiet-shop.com', 'registrar' => $registrar, 'status' => 'active', 'id_protection' => false,
        'expiry_date' => now()->addYear(), 'next_due_date' => now()->addYear(),
    ], $attrs));

    return [$user, $domain];
}

it('switches privacy on at the registrar, then records it', function () {
    Http::fake(['*domains/privacy*' => Http::response(['success' => true]), '*' => Http::response([], 200)]);
    [$user, $domain] = wpFixture();

    test()->actingAs($user)->get(route('client.domains.show', $domain))->assertOk()->assertSee(route('client.domains.privacy', $domain), false);
    test()->actingAs($user)->post(route('client.domains.privacy', $domain))->assertSessionHas('success');

    expect($domain->fresh()->id_protection)->toBeTrue();
    Http::assertSent(fn (Request $r) => str_contains($r->url(), 'domains/privacy') && $r['domainName'] === 'quiet-shop.com' && $r['privacyStatus'] === true);
});

it('switches it off again', function () {
    Http::fake(['*domains/privacy*' => Http::response(['success' => true]), '*' => Http::response([], 200)]);
    [$user, $domain] = wpFixture(attrs: ['id_protection' => true]);

    test()->actingAs($user)->post(route('client.domains.privacy', $domain))->assertSessionHas('success');

    expect($domain->fresh()->id_protection)->toBeFalse();
    Http::assertSent(fn (Request $r) => str_contains($r->url(), 'domains/privacy') && $r['privacyStatus'] === false);
});

it('changes nothing when the registrar refuses', function () {
    Http::fake(['*domains/privacy*' => Http::response(['error' => ['message' => 'Not supported for this TLD']], 400), '*' => Http::response([], 200)]);
    [$user, $domain] = wpFixture();

    test()->actingAs($user)->post(route('client.domains.privacy', $domain))->assertSessionHas('error');

    expect($domain->fresh()->id_protection)->toBeFalse();
});

it('offers no switch on a registrar that cannot do it, or a domain that is not active', function () {
    Http::fake(['*' => Http::response([], 200)]);
    [$user, $manual] = wpFixture('manual');
    test()->actingAs($user)->get(route('client.domains.show', $manual))->assertOk()->assertDontSee(route('client.domains.privacy', $manual), false);
    test()->actingAs($user)->post(route('client.domains.privacy', $manual))->assertSessionHas('error');

    [$user2, $expired] = wpFixture(attrs: ['domain' => 'old-shop.com', 'status' => 'expired']);
    test()->actingAs($user2)->post(route('client.domains.privacy', $expired))->assertSessionHas('error');
    Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'domains/privacy'));
});

it('refuses another customer\'s domain', function () {
    [, $domain] = wpFixture();
    [$stranger] = wpFixture(attrs: ['domain' => 'other-shop.com']);

    test()->actingAs($stranger)->post(route('client.domains.privacy', $domain))->assertForbidden();
});
