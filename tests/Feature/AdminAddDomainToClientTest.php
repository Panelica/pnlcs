<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;
use App\Models\Domain;
use App\Models\Invoice;
use App\Services\InvoiceGenerationService;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;

/*
 * Admin records a domain the client already registered elsewhere as a
 * billing-only entry (TKT-TZQSGKQM). Mirrors the "link existing service" flow:
 * no registrar call, just the billing record, so PNLCS renews it from
 * next_due_date + recurring_amount.
 */

function addDomainAdmin(): Admin
{
    return Admin::factory()->create([
        'role_id' => AdminRole::factory()->fullAdmin()->create()->id,
    ]);
}

it('records an existing domain as a billing-only entry', function () {
    $this->withoutMiddleware(VerifyCsrfToken::class);
    $client = Client::factory()->create();

    $this->actingAs(addDomainAdmin(), 'admin')
        ->post(route('admin.clients.domains.store', $client), [
            'domain' => 'HTTPS://Example.COM/path',
            'registrar' => 'GoDaddy',
            'registration_date' => '2024-01-15',
            'expiry_date' => '2027-01-15',
            'next_due_date' => '2027-01-15',
            'recurring_amount' => '14.99',
            'first_payment_amount' => '9.99',
            'status' => 'active',
        ])
        ->assertRedirect(route('admin.clients.show', ['client' => $client, 'tab' => 'domains']));

    $domain = Domain::where('client_id', $client->id)->first();

    expect($domain)->not->toBeNull()
        ->and($domain->domain)->toBe('example.com')            // normalised
        ->and($domain->registrar)->toBe('GoDaddy')
        ->and((float) $domain->recurring_amount)->toBe(14.99)
        ->and((float) $domain->first_payment_amount)->toBe(9.99)
        ->and($domain->status)->toBe('active')
        ->and($domain->type)->toBe('register')
        ->and($domain->order_id)->toBeNull()                   // no order = no registrar provisioning
        ->and($domain->expiry_date->toDateString())->toBe('2027-01-15');
});

it('shows the added domain on the client domains tab', function () {
    $this->withoutMiddleware(VerifyCsrfToken::class);
    $client = Client::factory()->create();
    Domain::create([
        'client_id' => $client->id, 'type' => 'register', 'domain' => 'billed.example',
        'registrar' => 'Namecheap', 'status' => 'active', 'recurring_amount' => 20,
        'expiry_date' => now()->addYear(), 'next_due_date' => now()->addYear(),
    ]);

    $this->actingAs(addDomainAdmin(), 'admin')
        ->get(route('admin.clients.show', ['client' => $client, 'tab' => 'domains']))
        ->assertOk()
        ->assertSee('billed.example')
        ->assertSee('Namecheap');
});

it('bills a renewal for the migrated domain once it is due', function () {
    $this->withoutMiddleware(VerifyCsrfToken::class);
    $client = Client::factory()->create();

    $this->actingAs(addDomainAdmin(), 'admin')
        ->post(route('admin.clients.domains.store', $client), [
            'domain' => 'renew-me.test',
            'recurring_amount' => '12.00',
            'next_due_date' => now()->subDay()->toDateString(), // already due
            'status' => 'active',
        ])->assertRedirect();

    $domain = Domain::where('client_id', $client->id)->firstOrFail();

    app(InvoiceGenerationService::class)->generateDueInvoices();

    $billed = Invoice::whereHas('items', fn ($q) => $q
        ->where('type', 'Domain')->where('rel_id', $domain->id))
        ->where('client_id', $client->id)
        ->exists();

    expect($billed)->toBeTrue();
});

it('requires a status from the domain status set and a recurring price', function () {
    $this->withoutMiddleware(VerifyCsrfToken::class);
    $client = Client::factory()->create();
    $admin = addDomainAdmin();

    // bad status
    $this->actingAs($admin, 'admin')
        ->post(route('admin.clients.domains.store', $client), [
            'domain' => 'x.test', 'recurring_amount' => '5', 'status' => 'Active', // capital = not in enum
        ])->assertSessionHasErrors('status');

    // missing recurring_amount
    $this->actingAs($admin, 'admin')
        ->post(route('admin.clients.domains.store', $client), [
            'domain' => 'y.test', 'status' => 'active',
        ])->assertSessionHasErrors('recurring_amount');
});
