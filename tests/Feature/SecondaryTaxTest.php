<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;
use App\Models\TaxRule;
use App\Services\InvoiceService;
use Illuminate\Support\Facades\Mail;

/**
 * The second tax an operator can configure and was never charged.
 *
 * The tax screen offers a level — Tax 1 or Tax 2 — and the invoice has
 * somewhere to print both, which it does whenever tax2 is set. Nothing ever
 * set it: the calculation only ever looked for level 1 rules. An operator
 * billing in a place with two taxes, a federal one and a provincial one, was
 * quietly collecting one of them.
 */
function twoLevelClient(): Client
{
    TaxRule::create(['level' => 1, 'name' => 'GST', 'country' => 'CA', 'state' => '', 'tax_rate' => 5]);
    TaxRule::create(['level' => 2, 'name' => 'PST', 'country' => 'CA', 'state' => '', 'tax_rate' => 7]);

    return Client::factory()->create(['country' => 'CA', 'state' => '', 'tax_exempt' => false]);
}

test('both taxes are worked out', function () {
    $client = twoLevelClient();

    $tax = app(InvoiceService::class)->calculateTax(100, $client->id);

    expect($tax['tax_rate'])->toEqual(5.0)
        ->and($tax['tax'])->toEqual(5.0)
        ->and($tax['tax_rate2'])->toEqual(7.0)
        ->and($tax['tax2'])->toEqual(7.0);
});

test('an invoice charges both and says so', function () {
    Mail::fake();
    $client = twoLevelClient();

    $invoice = app(InvoiceService::class)->createInvoice($client, [
        ['type' => 'Hosting', 'rel_id' => 0, 'description' => 'Hosting', 'amount' => 100, 'taxed' => true],
    ]);

    expect((float) $invoice->tax)->toEqual(5.0)
        ->and((float) $invoice->tax2)->toEqual(7.0)
        ->and((float) $invoice->tax_rate2)->toEqual(7.0)
        ->and((float) $invoice->total)->toEqual(112.0);
});

test('a tax exempt customer pays neither', function () {
    Mail::fake();
    twoLevelClient();
    $client = Client::factory()->create(['country' => 'CA', 'state' => '', 'tax_exempt' => true]);

    $invoice = app(InvoiceService::class)->createInvoice($client, [
        ['type' => 'Hosting', 'rel_id' => 0, 'description' => 'Hosting', 'amount' => 100, 'taxed' => true],
    ]);

    expect((float) $invoice->tax)->toEqual(0.0)
        ->and((float) $invoice->tax2)->toEqual(0.0)
        ->and((float) $invoice->total)->toEqual(100.0);
});

test('one tax on its own still behaves', function () {
    Mail::fake();
    TaxRule::create(['level' => 1, 'name' => 'VAT', 'country' => 'GB', 'state' => '', 'tax_rate' => 20]);
    $client = Client::factory()->create(['country' => 'GB', 'state' => '', 'tax_exempt' => false]);

    $invoice = app(InvoiceService::class)->createInvoice($client, [
        ['type' => 'Hosting', 'rel_id' => 0, 'description' => 'Hosting', 'amount' => 50, 'taxed' => true],
    ]);

    expect((float) $invoice->tax)->toEqual(10.0)
        ->and((float) $invoice->tax2)->toEqual(0.0)
        ->and((float) $invoice->total)->toEqual(60.0);
});

test('the tax screen shows the rate that was entered', function () {
    TaxRule::create(['level' => 1, 'name' => 'VAT', 'country' => 'GB', 'state' => '', 'tax_rate' => 17.5]);

    $admin = Admin::factory()->create([
        'role_id' => AdminRole::factory()->create(['name' => 'Billing', 'permissions' => ['manage_tax']])->id,
    ]);

    $this->actingAs($admin, 'admin')->get(route('admin.config.tax'))
        ->assertOk()
        ->assertSee('17.5');
});
