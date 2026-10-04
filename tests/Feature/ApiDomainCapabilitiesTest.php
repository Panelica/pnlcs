<?php

use App\Models\ApiCredential;
use App\Models\Client;
use App\Models\Domain;
use App\Models\RegistrarSettings;
use Database\Factories\ApiCredentialFactory;
use Illuminate\Support\Facades\Http;

/*
 * The API kept its old answers after the registrar learnt new things:
 * DomainToggleIdProtect only flipped the record (the WHOIS stayed as it was),
 * and DomainUpdateWhoisInfo said no registrar can change a contact, though
 * ManagesWhoisPrivacy and ManagesDomainContacts now exist and the client
 * area uses them.
 */

function apiCapHeaders(): array
{
    $credential = ApiCredential::factory()->create();

    return ['X-API-Key' => $credential->identifier, 'X-API-Secret' => ApiCredentialFactory::PLAINTEXT_SECRET, 'Accept' => 'application/json'];
}

function apiCapDomain(string $registrar = 'domainnameapi'): Domain
{
    RegistrarSettings::updateOrCreate(['registrar' => 'domainnameapi', 'setting' => 'reseller_id'], ['value' => 'reseller-1']);
    RegistrarSettings::updateOrCreate(['registrar' => 'domainnameapi', 'setting' => 'api_key'], ['value' => 'key-1']);

    return Domain::factory()->create(['client_id' => Client::factory()->create()->id, 'domain' => 'quiet-shop.com', 'registrar' => $registrar,
        'status' => 'active', 'id_protection' => false, 'expiry_date' => now()->addYear(), 'next_due_date' => now()->addYear()]);
}

test('switching privacy through the API asks the registrar first, and a refusal changes nothing', function () {
    $domain = apiCapDomain();

    Http::fake(['*domains/privacy*' => Http::response(['error' => ['message' => 'Not supported for this TLD']], 400), '*' => Http::response([], 200)]);
    $this->withHeaders(apiCapHeaders())->post('/api/v1/domaintoggleidprotect', ['domainid' => $domain->id, 'idprotect' => 1])->assertStatus(502);
    expect($domain->fresh()->id_protection)->toBeFalse();
});

test('switching privacy through the API records it once the registrar has done it', function () {
    $domain = apiCapDomain();

    Http::fake(['*domains/privacy*' => Http::response(['success' => true]), '*' => Http::response([], 200)]);
    $this->withHeaders(apiCapHeaders())->post('/api/v1/domaintoggleidprotect', ['domainid' => $domain->id, 'idprotect' => 1])->assertOk();
    expect($domain->fresh()->id_protection)->toBeTrue();
    Http::assertSent(fn ($r) => str_contains($r->url(), 'domains/privacy'));
});

test('a registrar that cannot switch privacy still has its record set, as before', function () {
    $domain = apiCapDomain('manual');

    $this->withHeaders(apiCapHeaders())->post('/api/v1/domaintoggleidprotect', ['domainid' => $domain->id, 'idprotect' => 1])->assertOk();
    expect($domain->fresh()->id_protection)->toBeTrue();
});

test('the WHOIS contact is changed at a registrar that can, and refused plainly where it cannot', function () {
    $contact = ['first_name' => 'Ayşe', 'last_name' => 'Yılmaz', 'email' => 'ayse@example.test', 'phone' => '+90 212 555 0101',
        'address1' => 'Bağdat Cd. 1', 'city' => 'İstanbul', 'postcode' => '34000', 'country' => 'TR'];

    $domain = apiCapDomain();
    Http::fake(['*domains/contacts/update*' => Http::response(['success' => true]), '*' => Http::response([], 200)]);
    $this->withHeaders(apiCapHeaders())->post('/api/v1/domainupdatewhoisinfo', ['domainid' => $domain->id] + $contact)->assertOk();
    Http::assertSent(fn ($r) => str_contains($r->url(), 'domains/contacts/update'));

    $this->withHeaders(apiCapHeaders())->post('/api/v1/domainupdatewhoisinfo', ['domainid' => $domain->id, 'first_name' => 'X'])->assertStatus(422);

    $manual = apiCapDomain('manual');
    $manual->update(['domain' => 'other-shop.com']);
    $this->withHeaders(apiCapHeaders())->post('/api/v1/domainupdatewhoisinfo', ['domainid' => $manual->id] + $contact)->assertStatus(501);
});

test('turning paid privacy off through the API stops charging for it at renewal, as the admin page does', function () {
    $domain = apiCapDomain();
    \App\Models\DomainPricing::updateOrCreate(['extension' => '.com'], ['register_price' => 10, 'renew_price' => 12, 'transfer_price' => 10, 'privacy_price' => 3]);
    $domain->update(['id_protection' => true, 'recurring_amount' => 15, 'registration_period' => 1]);

    Http::fake(['*domains/privacy*' => Http::response(['success' => true]), '*' => Http::response([], 200)]);
    $this->withHeaders(apiCapHeaders())->post('/api/v1/domaintoggleidprotect', ['domainid' => $domain->id, 'idprotect' => 0])
        ->assertOk()->assertJsonPath('idprotection', false);

    expect((float) $domain->fresh()->recurring_amount)->toBe(12.0);
});

test('privacy is not recorded as changed for a domain that is not active at a registrar that could change it', function () {
    $domain = apiCapDomain();
    $domain->update(['status' => 'expired']);
    Http::fake();

    $this->withHeaders(apiCapHeaders())->post('/api/v1/domaintoggleidprotect', ['domainid' => $domain->id, 'idprotect' => 1])->assertStatus(422);
    expect($domain->fresh()->id_protection)->toBeFalse();
    Http::assertNothingSent();
});

test('the WHOIS contact is read from the registrar, and from the profile only when it cannot be', function () {
    $domain = apiCapDomain();
    Http::fake(['*domains/info*' => Http::response(['info' => ['contacts' => [[
        'contactType' => 'Registrant', 'firstName' => 'Mehmet', 'lastName' => 'Kaya', 'eMail' => 'mehmet@example.test',
        'phoneCountryCode' => '90', 'phone' => '2125550101', 'address' => 'Moda Cd. 5', 'city' => 'İstanbul', 'postalCode' => '34710', 'country' => 'tr',
    ]]]]), '*' => Http::response([], 200)]);

    $this->withHeaders(apiCapHeaders())->get('/api/v1/domaingetwhoisinfo?domainid='.$domain->id)->assertOk()
        ->assertJsonPath('source', 'registrar')
        ->assertJsonPath('whois.Registrant.Name', 'Mehmet Kaya')
        ->assertJsonPath('whois.Registrant.Email Address', 'mehmet@example.test')
        ->assertJsonPath('whois.Registrant.Country', 'TR');

    $manual = apiCapDomain('manual');
    $manual->update(['domain' => 'third-shop.com']);
    $this->withHeaders(apiCapHeaders())->get('/api/v1/domaingetwhoisinfo?domainid='.$manual->id)->assertOk()
        ->assertJsonPath('source', 'profile')
        ->assertJsonPath('whois.Registrant.Email Address', $manual->client->email);
});
