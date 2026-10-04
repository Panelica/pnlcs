<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;
use App\Models\Domain;
use App\Models\DomainPricing;
use App\Models\RegistrarSettings;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/*
 * Staff do at the registrar what the customer can: WHOIS privacy, the WHOIS
 * contact and glue records.
 *
 * The client area could do all three, the admin domain page none of them, so
 * a customer who asked support for help had to be told to do it themselves.
 */

function adrAdmin(array $permissions = ['list_domains', 'manage_domains']): Admin
{
    return Admin::factory()->create(['role_id' => AdminRole::factory()->create(['name' => 'R'.uniqid(), 'permissions' => $permissions])->id]);
}

function adrDomain(string $registrar = 'domainnameapi', array $attrs = []): Domain
{
    RegistrarSettings::updateOrCreate(['registrar' => 'domainnameapi', 'setting' => 'reseller_id'], ['value' => 'reseller-1']);
    RegistrarSettings::updateOrCreate(['registrar' => 'domainnameapi', 'setting' => 'api_key'], ['value' => 'key-1']);

    return Domain::factory()->create(array_merge([
        'client_id' => Client::factory()->create()->id, 'domain' => 'staff-shop.com', 'registrar' => $registrar, 'status' => 'active',
        'id_protection' => false, 'expiry_date' => now()->addYear(), 'next_due_date' => now()->addYear(),
    ], $attrs));
}

it('offers the three actions on the domain page where the registrar can do them', function () {
    Http::fake(['*' => Http::response([], 200)]);
    $domain = adrDomain();

    test()->actingAs(adrAdmin(), 'admin')->get(route('admin.domains.show', $domain))->assertOk()
        ->assertSee(route('admin.domains.privacy', $domain), false)
        ->assertSee(route('admin.domains.contacts', $domain), false)
        ->assertSee(route('admin.domains.glue', $domain), false);

    $manual = adrDomain('manual', ['domain' => 'manual-shop.com']);
    test()->actingAs(adrAdmin(), 'admin')->get(route('admin.domains.show', $manual))->assertOk()
        ->assertDontSee(route('admin.domains.privacy', $manual), false)
        ->assertDontSee(route('admin.domains.glue', $manual), false);
    test()->actingAs(adrAdmin(), 'admin')->post(route('admin.domains.privacy', $manual))->assertSessionHas('error');
});

it('switches privacy at the registrar, records it, and leaves the record when the registrar refuses', function () {
    $domain = adrDomain();

    Http::fake(['*domains/privacy*' => Http::response(['error' => ['message' => 'Not supported for this TLD']], 400), '*' => Http::response([], 200)]);
    test()->actingAs(adrAdmin(), 'admin')->post(route('admin.domains.privacy', $domain))->assertSessionHas('error');
    expect($domain->fresh()->id_protection)->toBeFalse();
});

it('switches privacy on for the customer, even where it is sold', function () {
    DomainPricing::updateOrCreate(['extension' => '.com'], ['register_price' => 10, 'renew_price' => 10, 'transfer_price' => 10, 'privacy_price' => 5]);
    $domain = adrDomain();

    Http::fake(['*domains/privacy*' => Http::response(['success' => true]), '*' => Http::response([], 200)]);
    test()->actingAs(adrAdmin(), 'admin')->post(route('admin.domains.privacy', $domain))->assertSessionHas('success');

    expect($domain->fresh()->id_protection)->toBeTrue();
    Http::assertSent(fn (Request $r) => str_contains($r->url(), 'domains/privacy') && $r['privacyStatus'] === true);
});

it('changes the WHOIS contact at the registrar', function () {
    Http::fake(['*domains/info*' => Http::response(['error' => ['message' => 'down']], 500), '*domains/contacts/update*' => Http::response(['success' => true]), '*' => Http::response([], 200)]);
    $domain = adrDomain();

    test()->actingAs(adrAdmin(), 'admin')->get(route('admin.domains.contacts', $domain))->assertOk()->assertSee(__('admin.domains.contacts_from_profile'));
    test()->actingAs(adrAdmin(), 'admin')->put(route('admin.domains.contacts.update', $domain), [
        'first_name' => 'Ayşe', 'last_name' => 'Yılmaz', 'email' => 'ayse@example.test', 'phone' => '+90 212 555 0101',
        'address1' => 'Bağdat Cd. 1', 'city' => 'İstanbul', 'postcode' => '34000', 'country' => 'TR',
    ])->assertRedirect(route('admin.domains.show', $domain))->assertSessionHas('success');

    Http::assertSent(fn (Request $r) => str_contains($r->url(), 'domains/contacts/update'));
});

it('lists, adds and deletes glue records under the domain only', function () {
    $info = ['*domains/info*' => Http::response(['domainName' => 'staff-shop.com', 'hosts' => [['name' => 'ns1.staff-shop.com', 'ipAddresses' => [['ipAddress' => '203.0.113.10', 'ipVersion' => 'v4']]]]])];
    Http::fake($info + ['*domains/dns/host*' => Http::response(['success' => true]), '*' => Http::response([], 200)]);
    $domain = adrDomain();
    $admin = adrAdmin();

    test()->actingAs($admin, 'admin')->get(route('admin.domains.glue', $domain))->assertOk()->assertSee('ns1.staff-shop.com');
    test()->actingAs($admin, 'admin')->post(route('admin.domains.glue.save', $domain), ['host' => 'ns2', 'ipv4' => '203.0.113.11'])
        ->assertRedirect(route('admin.domains.glue', $domain))->assertSessionHas('success');
    test()->actingAs($admin, 'admin')->post(route('admin.domains.glue.save', $domain), ['host' => 'bad_label', 'ipv4' => '203.0.113.11'])
        ->assertSessionHasErrors('host');
    test()->actingAs($admin, 'admin')->delete(route('admin.domains.glue.delete', $domain), ['host' => 'ns1'])->assertSessionHas('success');

    Http::assertSent(fn (Request $r) => $r->method() === 'POST' && str_contains($r->url(), 'domains/dns/host') && $r['hostName'] === 'ns2.staff-shop.com');
    Http::assertSent(fn (Request $r) => $r->method() === 'DELETE' && str_contains($r->url(), 'hostName=ns1.staff-shop.com'));
});

it('is open only to staff who may manage domains', function () {
    Http::fake(['*' => Http::response([], 200)]);
    $domain = adrDomain();

    test()->actingAs(adrAdmin(['list_domains']), 'admin')->post(route('admin.domains.privacy', $domain))->assertForbidden();
    test()->actingAs(adrAdmin(['list_domains']), 'admin')->get(route('admin.domains.glue', $domain))->assertForbidden();
    Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'domains/privacy'));
});
