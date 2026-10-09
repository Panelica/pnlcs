<?php

use App\Http\Middleware\RedirectToInstaller;
use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;
use App\Models\Invoice;

/*
 * The invoice list opens on Unpaid. "All" on the tabs and "All invoices" in
 * the menu linked to the list with an empty status, which the URL generator
 * drops, so both opened on Unpaid and paid invoices could not be listed
 * together with the rest.
 */

beforeEach(function () {
    $this->withoutMiddleware(RedirectToInstaller::class);
    $this->admin = Admin::factory()->create(['role_id' => AdminRole::factory()->fullAdmin()->create()->id]);
    $client = Client::factory()->create();
    $this->paid = Invoice::factory()->create(['client_id' => $client->id, 'status' => 'paid', 'invoice_num' => 'INV-ALLTAB-PAID']);
    $this->unpaid = Invoice::factory()->create(['client_id' => $client->id, 'status' => 'unpaid', 'invoice_num' => 'INV-ALLTAB-OPEN']);
});

test('the list still opens on unpaid invoices', function () {
    $this->actingAs($this->admin, 'admin')->get(route('admin.invoices.index'))
        ->assertOk()
        ->assertSee('INV-ALLTAB-OPEN')
        ->assertDontSee('INV-ALLTAB-PAID');
});

test('the All tab lists every invoice', function () {
    $this->actingAs($this->admin, 'admin')->get(route('admin.invoices.index', ['status' => 'all']))
        ->assertOk()
        ->assertSee('INV-ALLTAB-OPEN')
        ->assertSee('INV-ALLTAB-PAID');
});

test('the All tab and the All invoices menu entry link to it', function () {
    $html = $this->actingAs($this->admin, 'admin')->get(route('admin.invoices.index'))->assertOk()->getContent();
    $all = e(route('admin.invoices.index', ['status' => 'all']));

    expect(substr_count($html, 'href="'.$all.'"'))->toBeGreaterThanOrEqual(2);
});
