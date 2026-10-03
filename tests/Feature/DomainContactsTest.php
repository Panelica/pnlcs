<?php

use App\Models\Client;
use App\Models\Domain;
use App\Models\RegistrarSettings;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/*
 * The customer changes a domain's WHOIS contact from the panel.
 *
 * The contact was sent once, at registration, from the profile; nothing in
 * the panel could read or change it afterwards.
 */

function dcFixture(string $registrar = 'domainnameapi', array $attrs = []): array
{
    RegistrarSettings::updateOrCreate(['registrar' => 'domainnameapi', 'setting' => 'reseller_id'], ['value' => 'reseller-1']);
    RegistrarSettings::updateOrCreate(['registrar' => 'domainnameapi', 'setting' => 'api_key'], ['value' => 'key-1']);
    $user = User::factory()->create();
    $client = Client::factory()->create(['first_name' => 'Ayşe', 'last_name' => 'Yılmaz', 'country' => 'TR', 'city' => 'İzmir']);
    $user->clients()->attach($client->id, ['owner' => true]);
    $domain = Domain::factory()->create(array_merge([
        'client_id' => $client->id, 'domain' => 'contact-shop.com', 'registrar' => $registrar, 'status' => 'active',
        'expiry_date' => now()->addYear(), 'next_due_date' => now()->addYear(),
    ], $attrs));

    return [$user, $domain];
}

function dcForm(array $over = []): array
{
    return array_merge([
        'first_name' => 'Mehmet', 'last_name' => 'Demir', 'company_name' => 'Demir Ltd', 'email' => 'mehmet@example.test',
        'phone' => '+90 532 111 22 33', 'address1' => 'Atatürk Cd. 1', 'city' => 'Ankara', 'state' => 'Ankara', 'postcode' => '06100', 'country' => 'TR',
    ], $over);
}

it('fills the form with the contact the registry holds', function () {
    Http::fake([
        '*domains/info*' => Http::response(['domainName' => 'contact-shop.com', 'contacts' => [
            ['contactType' => 'Registrant', 'firstName' => 'Kayıtlı', 'lastName' => 'Sahip', 'eMail' => 'owner@example.test', 'phoneCountryCode' => '90', 'phone' => '5321112233', 'address' => 'Cd. 5', 'city' => 'Bursa', 'postalCode' => '16000', 'country' => 'TR'],
        ]]),
        '*' => Http::response([], 200),
    ]);
    [$user, $domain] = dcFixture();

    test()->actingAs($user)->get(route('client.domains.show', $domain))->assertSee(route('client.domains.contacts', $domain), false);
    test()->actingAs($user)->get(route('client.domains.contacts', $domain))->assertOk()
        ->assertSee('value="Kayıtlı"', false)->assertSee('value="owner@example.test"', false)->assertSee('value="+90 5321112233"', false);
});

it('falls back to the profile when the registry cannot be read', function () {
    Http::fake(['*domains/info*' => Http::response(['error' => ['message' => 'down']], 500), '*' => Http::response([], 200)]);
    [$user, $domain] = dcFixture();

    test()->actingAs($user)->get(route('client.domains.contacts', $domain))->assertOk()
        ->assertSee('value="Ayşe"', false)->assertSee(__('client.domains.contacts_from_profile'));
});

it('sends the contact for every role and says so', function () {
    Http::fake(['*domains/contacts/update*' => Http::response(['success' => true]), '*' => Http::response([], 200)]);
    [$user, $domain] = dcFixture();

    test()->actingAs($user)->put(route('client.domains.contacts.update', $domain), dcForm())
        ->assertRedirect(route('client.domains.show', $domain))->assertSessionHas('success');

    Http::assertSent(function (Request $r) {
        if (! str_contains($r->url(), 'domains/contacts/update') || $r->method() !== 'PUT') {
            return false;
        }
        $roles = collect($r['contacts'])->pluck('contactType')->all();
        $first = $r['contacts'][0];

        return $r['domainName'] === 'contact-shop.com'
            && $roles === ['Registrant', 'Administrative', 'Technical', 'Billing']
            && $first['FirstName'] === 'Mehmet' && $first['companyName'] === 'Demir Ltd'
            && $first['PhoneCountryCode'] === '90' && $first['Phone'] === '5321112233' && $first['City'] === 'Ankara';
    });
});

it('keeps what was typed and says so when the registrar refuses', function () {
    Http::fake(['*domains/contacts/update*' => Http::response(['error' => ['message' => 'Invalid postal code']], 400), '*' => Http::response([], 200)]);
    [$user, $domain] = dcFixture();

    test()->actingAs($user)->from(route('client.domains.contacts', $domain))->put(route('client.domains.contacts.update', $domain), dcForm())
        ->assertRedirect(route('client.domains.contacts', $domain))->assertSessionHas('error')->assertSessionHasInput('first_name', 'Mehmet');
});

it('refuses incomplete details before calling the registrar', function () {
    Http::fake(['*' => Http::response([], 200)]);
    [$user, $domain] = dcFixture();

    test()->actingAs($user)->put(route('client.domains.contacts.update', $domain), dcForm(['email' => 'not-an-email', 'country' => 'XX', 'phone' => 'call me']))
        ->assertSessionHasErrors(['email', 'country', 'phone']);
    Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'contacts/update'));
});

it('offers nothing on a registrar without the capability, and refuses another customer', function () {
    Http::fake(['*' => Http::response([], 200)]);
    [$user, $manual] = dcFixture('manual');
    test()->actingAs($user)->get(route('client.domains.show', $manual))->assertDontSee(route('client.domains.contacts', $manual), false);
    test()->actingAs($user)->get(route('client.domains.contacts', $manual))->assertRedirect(route('client.domains.show', $manual));

    [, $domain] = dcFixture(attrs: ['domain' => 'theirs-shop.com']);
    test()->actingAs($user)->put(route('client.domains.contacts.update', $domain), dcForm())->assertForbidden();
});
