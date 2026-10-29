<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\User;

/**
 * A proforma is not a tax document, so the screens that name an invoice must
 * not call it one. The PDF was fixed to say "Proforma"; the admin and client
 * invoice pages still headed every document "Invoice #X".
 */

function proformaScreenAdmin(): Admin
{
    return Admin::factory()->create([
        'role_id' => AdminRole::factory()->create([
            'name' => 'Billing',
            'permissions' => ['list_invoices', 'view_invoices', 'manage_invoices'],
        ])->id,
    ]);
}

test('the admin invoice page titles a proforma as a proforma', function () {
    $vat = Invoice::factory()->create(['type' => 'vat', 'invoice_num' => 'INV-1', 'client_id' => Client::factory()->create()->id]);
    $proforma = Invoice::factory()->create(['type' => 'proforma', 'invoice_num' => 'PRO-1', 'client_id' => Client::factory()->create()->id]);

    $this->actingAs(proformaScreenAdmin(), 'admin')
        ->get(route('admin.invoices.show', $vat))
        ->assertOk()
        ->assertSee('Invoice #INV-1', false);

    $this->actingAs(proformaScreenAdmin(), 'admin')
        ->get(route('admin.invoices.show', $proforma))
        ->assertOk()
        ->assertSee('Proforma #PRO-1', false);
});

test('the client invoice page titles a proforma as a proforma', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create();
    $user->clients()->attach($client->id);

    $vat = Invoice::factory()->create(['type' => 'vat', 'invoice_num' => 'INV-2', 'client_id' => $client->id]);
    $proforma = Invoice::factory()->create(['type' => 'proforma', 'invoice_num' => 'PRO-2', 'client_id' => $client->id]);

    $this->actingAs($user)
        ->get(route('client.invoices.show', $vat))
        ->assertOk()
        ->assertSee('Invoice #INV-2', false);

    $this->actingAs($user)
        ->get(route('client.invoices.show', $proforma))
        ->assertOk()
        ->assertSee('Proforma #PRO-2', false);
});

test('a proforma in the admin search is titled as a proforma', function () {
    $client = Client::factory()->create();
    Invoice::factory()->create(['type' => 'proforma', 'invoice_num' => 'PRO-9', 'client_id' => $client->id]);

    $this->actingAs(proformaScreenAdmin(), 'admin')
        ->get(route('admin.search', ['q' => 'PRO-9']))
        ->assertOk()
        ->assertSee('Proforma #PRO-9', false);
});
