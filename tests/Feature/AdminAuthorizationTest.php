<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Affiliate;
use App\Models\Client;
use App\Models\Quote;

/**
 * Broken-access-control guards for state-changing admin routes that used to sit
 * inside admin.auth but outside any admin.permission group — meaning any
 * authenticated staff member (even a support-only role) could issue affiliate
 * payouts, convert quotes to invoices, create billable items, etc. Each is now
 * wrapped in the matching permission group; full admins still bypass.
 */

function limitedAdmin(array $permissions = ['list_tickets']): Admin
{
    $role = AdminRole::factory()->create(['is_full_admin' => false, 'permissions' => $permissions]);
    return Admin::factory()->create(['role_id' => $role->id]);
}

function superAdmin(): Admin
{
    $role = AdminRole::factory()->fullAdmin()->create();
    return Admin::factory()->create(['role_id' => $role->id]);
}

function makeAffiliate(Client $client): Affiliate
{
    return Affiliate::create([
        'client_id' => $client->id, 'visitors' => 0, 'pay_type' => 'percentage',
        'pay_amount' => 10, 'onetime' => false, 'balance' => 100, 'withdrawn' => 0,
    ]);
}

function makeQuote(Client $client): Quote
{
    return Quote::create([
        'client_id' => $client->id, 'subject' => 'Test quote', 'date' => now(),
        'valid_until' => now()->addDays(30), 'subtotal' => 100, 'tax' => 0,
        'total' => 100, 'status' => 'Draft',
    ]);
}

it('forbids a limited admin from issuing an affiliate payout', function () {
    $client    = Client::factory()->create();
    $affiliate = makeAffiliate($client);

    $this->actingAs(limitedAdmin(), 'admin')
        ->post("/admin/affiliates/{$affiliate->id}/payout", ['amount' => 50])
        ->assertForbidden();
});

it('lets a full admin reach the affiliate payout route (not blocked by permissions)', function () {
    $client    = Client::factory()->create();
    $affiliate = makeAffiliate($client);

    $status = $this->actingAs(superAdmin(), 'admin')
        ->post("/admin/affiliates/{$affiliate->id}/payout", ['amount' => 50])
        ->status();

    expect($status)->not->toBe(403);
});

it('forbids a limited admin from converting a quote to an invoice', function () {
    $client = Client::factory()->create();
    $quote  = makeQuote($client);

    $this->actingAs(limitedAdmin(), 'admin')
        ->post("/admin/quotes/{$quote->id}/convert")
        ->assertForbidden();
});

it('forbids a limited admin from creating a billable item', function () {
    $this->actingAs(limitedAdmin(), 'admin')
        ->post('/admin/config/billable-items', ['client_id' => 1, 'description' => 'x', 'amount' => 10])
        ->assertForbidden();
});

it('forbids a limited admin from viewing system phpinfo diagnostics', function () {
    $this->actingAs(limitedAdmin(), 'admin')
        ->get('/admin/config/system-phpinfo')
        ->assertForbidden();
});

it('allows a manage_affiliates admin through the affiliate payout gate', function () {
    $client    = Client::factory()->create();
    $affiliate = makeAffiliate($client);

    $status = $this->actingAs(limitedAdmin(['manage_affiliates']), 'admin')
        ->post("/admin/affiliates/{$affiliate->id}/payout", ['amount' => 50])
        ->status();

    expect($status)->not->toBe(403);
});
