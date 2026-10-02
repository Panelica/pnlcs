<?php

use App\Models\ActivityLog;
use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;
use App\Models\Domain;
use App\Models\Invoice;
use App\Models\InvoiceItem;

/*
 * Staff move a domain to another client account.
 *
 * Neither staff nor the customer could: a domain sold to someone else, or kept
 * under a second account, stayed where it was ordered.
 */

function dmAdmin(array $permissions = ['list_domains', 'manage_domains']): Admin
{
    return Admin::factory()->create(['role_id' => AdminRole::factory()->create(['name' => 'R'.uniqid(), 'permissions' => $permissions])->id]);
}

function dmDomain(): Domain
{
    return Domain::factory()->create(['client_id' => Client::factory()->create()->id, 'domain' => 'moving-shop.com', 'status' => 'active']);
}

it('moves the domain by client ID or email and logs it on both accounts', function () {
    $domain = dmDomain();
    $from = $domain->client_id;
    $to = Client::factory()->create(['email' => 'new-owner@example.test']);
    $admin = dmAdmin();

    test()->actingAs($admin, 'admin')->get(route('admin.domains.show', $domain))->assertOk()->assertSee(route('admin.domains.move', $domain), false);
    test()->actingAs($admin, 'admin')->post(route('admin.domains.move', $domain), ['client' => 'new-owner@example.test'])->assertSessionHas('success');

    expect($domain->fresh()->client_id)->toBe($to->id)
        ->and(ActivityLog::where('client_id', $from)->where('description', 'like', '%moved to client #'.$to->id.'%')->exists())->toBeTrue()
        ->and(ActivityLog::where('client_id', $to->id)->where('description', 'like', '%moved here from client #'.$from.'%')->exists())->toBeTrue();

    $back = Client::find($from);
    test()->actingAs($admin, 'admin')->post(route('admin.domains.move', $domain), ['client' => (string) $back->id])->assertSessionHas('success');
    expect($domain->fresh()->client_id)->toBe($from);
});

it('refuses an unknown client and the same client', function () {
    $domain = dmDomain();

    test()->actingAs(dmAdmin(), 'admin')->post(route('admin.domains.move', $domain), ['client' => 'nobody@example.test'])->assertSessionHasErrors('client');
    test()->actingAs(dmAdmin(), 'admin')->post(route('admin.domains.move', $domain), ['client' => (string) $domain->client_id])->assertSessionHasErrors('client');
});

it('refuses while an unpaid invoice carries the domain', function () {
    $domain = dmDomain();
    $to = Client::factory()->create();
    $invoice = Invoice::factory()->create(['client_id' => $domain->client_id, 'status' => 'unpaid', 'subtotal' => 10, 'total' => 10, 'due_date' => now()->addDays(7)]);
    InvoiceItem::create(['invoice_id' => $invoice->id, 'client_id' => $domain->client_id, 'type' => 'Domain', 'rel_id' => $domain->id, 'description' => 'Renewal', 'amount' => 10, 'taxed' => false]);

    test()->actingAs(dmAdmin(), 'admin')->post(route('admin.domains.move', $domain), ['client' => (string) $to->id])->assertSessionHas('error');
    expect($domain->fresh()->client_id)->not->toBe($to->id);
});

it('is kept to staff who manage domains', function () {
    $domain = dmDomain();
    $to = Client::factory()->create();

    test()->actingAs(dmAdmin(['list_domains']), 'admin')->post(route('admin.domains.move', $domain), ['client' => (string) $to->id])->assertForbidden();
    expect($domain->fresh()->client_id)->not->toBe($to->id);
});
