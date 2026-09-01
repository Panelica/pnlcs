<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;
use App\Models\TaxRule;
use App\Services\InvoiceService;
use Illuminate\Support\Facades\Mail;

/**
 * The default tax rate.
 *
 * Tax rules are matched on a customer's country and state; the default rule is
 * what applies when nothing more specific matches. The old "Tax 2" level is
 * gone: a customer carries a single rate, and the default is the fallback.
 */
function clientWithCountry(string $country, bool $exempt = false): Client
{
    return Client::factory()->create(['country' => $country, 'state' => '', 'tax_exempt' => $exempt]);
}

test('a customer matches their country rule', function () {
    TaxRule::create(['name' => 'VAT', 'country' => 'GB', 'state' => '', 'tax_rate' => 20]);

    $tax = app(InvoiceService::class)->calculateTax(100, clientWithCountry('GB')->id);

    expect($tax['tax_rate'])->toEqual(20.0)
        ->and($tax['tax'])->toEqual(20.0);
});

test('a customer with no matching rule gets the default rate', function () {
    TaxRule::create(['name' => 'Standard', 'country' => '', 'state' => '', 'tax_rate' => 23, 'is_default' => true]);

    $tax = app(InvoiceService::class)->calculateTax(100, clientWithCountry('PL')->id);

    expect($tax['tax_rate'])->toEqual(23.0)
        ->and($tax['tax'])->toEqual(23.0);
});

test('a customer with no matching rule and no default pays no tax', function () {
    TaxRule::create(['name' => 'VAT', 'country' => 'DE', 'state' => '', 'tax_rate' => 19]);

    $tax = app(InvoiceService::class)->calculateTax(100, clientWithCountry('PL')->id);

    expect($tax['tax_rate'])->toEqual(0.0)
        ->and($tax['tax'])->toEqual(0.0);
});

test('a tax exempt customer pays nothing', function () {
    Mail::fake();
    TaxRule::create(['name' => 'Standard', 'country' => '', 'state' => '', 'tax_rate' => 23, 'is_default' => true]);

    $client = clientWithCountry('PL', true);

    $invoice = app(InvoiceService::class)->createInvoice($client, [
        ['type' => 'Hosting', 'rel_id' => 0, 'description' => 'Hosting', 'amount' => 100, 'taxed' => true],
    ]);

    expect((float) $invoice->tax)->toEqual(0.0)
        ->and((float) $invoice->total)->toEqual(100.0);
});

test('an invoice charges the default rate when nothing matches', function () {
    Mail::fake();
    TaxRule::create(['name' => 'Standard', 'country' => '', 'state' => '', 'tax_rate' => 23, 'is_default' => true]);
    $client = clientWithCountry('PL');

    $invoice = app(InvoiceService::class)->createInvoice($client, [
        ['type' => 'Hosting', 'rel_id' => 0, 'description' => 'Hosting', 'amount' => 100, 'taxed' => true],
    ]);

    expect((float) $invoice->tax)->toEqual(23.0)
        ->and((float) $invoice->total)->toEqual(123.0);
});

test('within a country the default rate wins over its other rates', function () {
    TaxRule::create(['name' => 'VAT 8%', 'country' => 'PL', 'state' => '', 'tax_rate' => 8]);
    TaxRule::create(['name' => 'VAT 23%', 'country' => 'PL', 'state' => '', 'tax_rate' => 23, 'is_default' => true]);

    $tax = app(InvoiceService::class)->calculateTax(100, clientWithCountry('PL')->id);

    expect($tax['tax_rate'])->toEqual(23.0);
});

test('a state-specific rate wins over the country default', function () {
    TaxRule::create(['name' => 'TX Sales Tax', 'country' => 'US', 'state' => 'TX', 'tax_rate' => 8.25]);
    TaxRule::create(['name' => 'US Default', 'country' => 'US', 'state' => '', 'tax_rate' => 7, 'is_default' => true]);

    $client = Client::factory()->create(['country' => 'US', 'state' => 'TX', 'tax_exempt' => false]);
    $tax = app(InvoiceService::class)->calculateTax(100, $client->id);

    expect($tax['tax_rate'])->toEqual(8.25);
});

test('the tax screen shows the rate that was entered', function () {
    TaxRule::create(['name' => 'VAT', 'country' => 'GB', 'state' => '', 'tax_rate' => 17.5]);

    $admin = Admin::factory()->create([
        'role_id' => AdminRole::factory()->create(['name' => 'Billing', 'permissions' => ['manage_tax']])->id,
    ]);

    $this->actingAs($admin, 'admin')->get(route('admin.config.tax'))
        ->assertOk()
        ->assertSee('17.5');
});
