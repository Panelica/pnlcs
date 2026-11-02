<?php

use App\Models\Client;
use App\Models\ClientGroup;
use App\Models\TaxRule;
use App\Services\InvoiceService;
use Illuminate\Support\Facades\Mail;

/**
 * The discount a customer group promises.
 *
 * Client groups carry a discount percentage. The admin screen asks for it, the
 * list shows it, the controller stores it — and nothing ever took it off an
 * invoice. Three customers are in a group marked 15% on this installation and
 * two more in a group marked 10%; all five were being charged the full price.
 */
function groupedClient(float $percent, bool $exempt = true): Client
{
    return Client::factory()->create([
        'tax_exempt' => $exempt,
        'group_id' => ClientGroup::create([
            'name' => 'VIP Clients',
            'color' => '#eab308',
            'discount_percent' => $percent,
        ])->id,
    ]);
}

function hostingInvoice(Client $client, float $amount = 100, bool $taxed = true)
{
    return app(InvoiceService::class)->createInvoice($client, [
        ['type' => 'Hosting', 'rel_id' => 0, 'description' => 'Hosting', 'amount' => $amount, 'taxed' => $taxed],
    ]);
}

test('a discounted group pays less', function () {
    Mail::fake();

    $invoice = hostingInvoice(groupedClient(15), 100);

    expect((float) $invoice->total)->toEqual(85.0);
});

test('the discount is on the invoice, not hidden in the total', function () {
    Mail::fake();

    $invoice = hostingInvoice(groupedClient(15), 100);

    $discount = $invoice->items->firstWhere('type', 'Discount');

    expect($discount)->not->toBeNull()
        ->and((float) $discount->amount)->toEqual(-15.0)
        ->and($discount->description)->toContain('VIP Clients');
});

test('tax is worked out on what the customer actually pays', function () {
    Mail::fake();

    TaxRule::create(['name' => 'VAT', 'country' => 'GB', 'state' => '', 'tax_rate' => 20]);

    $client = groupedClient(15, false);
    $client->update(['country' => 'GB', 'state' => '']);

    $invoice = hostingInvoice($client->fresh(), 100);

    // 100 less 15 = 85, plus 20% of 85.
    expect((float) $invoice->tax)->toEqual(17.0)
        ->and((float) $invoice->total)->toEqual(102.0);
});

test('a customer in no group pays the same as before', function () {
    Mail::fake();

    $invoice = hostingInvoice(Client::factory()->create(['tax_exempt' => true]), 100);

    expect((float) $invoice->total)->toEqual(100.0)
        ->and($invoice->items->firstWhere('type', 'Discount'))->toBeNull();
});

test('a group with no discount changes nothing', function () {
    Mail::fake();

    $invoice = hostingInvoice(groupedClient(0), 100);

    expect((float) $invoice->total)->toEqual(100.0)
        ->and($invoice->items->firstWhere('type', 'Discount'))->toBeNull();
});

test('the discount is worked out on the line total, quantity included', function () {
    Mail::fake();

    $invoice = app(InvoiceService::class)->createInvoice(groupedClient(10), [
        ['type' => 'Other', 'description' => 'Seats', 'qty' => 3, 'amount' => 100, 'taxed' => true],
    ]);

    expect((float) $invoice->items->firstWhere('type', 'Discount')->amount)->toEqual(-30.0)
        ->and((float) $invoice->total)->toEqual(270.0);
});

test('two VAT rates get a discount line each and the VAT table adds up', function () {
    Mail::fake();

    $invoice = app(InvoiceService::class)->createInvoice(groupedClient(10, false), [
        ['type' => 'Other', 'description' => 'Standard', 'amount' => 100, 'tax_rate' => 20, 'tax_label' => 'VAT 20'],
        ['type' => 'Other', 'description' => 'Reduced', 'amount' => 100, 'tax_rate' => 10, 'tax_label' => 'VAT 10'],
    ]);

    $discounts = $invoice->items->where('type', 'Discount');
    $table = $invoice->vatBreakdown();

    expect((float) $invoice->tax)->toEqual(27.0)
        ->and((float) $invoice->total)->toEqual(207.0)
        ->and($discounts)->toHaveCount(2)
        ->and(round($table['VAT 20']['amount'], 2))->toEqual(18.0)
        ->and(round($table['VAT 20']['net'], 2))->toEqual(90.0)
        ->and(round($table['VAT 10']['amount'], 2))->toEqual(9.0)
        ->and(round($table['VAT 10']['net'], 2))->toEqual(90.0);
});

test('one VAT rate keeps a single discount line, inside that rate', function () {
    Mail::fake();

    $invoice = app(InvoiceService::class)->createInvoice(groupedClient(10, false), [
        ['type' => 'Other', 'description' => 'One', 'amount' => 100, 'tax_rate' => 20, 'tax_label' => 'VAT 20'],
        ['type' => 'Other', 'description' => 'Two', 'amount' => 100, 'tax_rate' => 20, 'tax_label' => 'VAT 20'],
    ]);

    expect($invoice->items->where('type', 'Discount'))->toHaveCount(1)
        ->and((float) $invoice->tax)->toEqual(36.0)
        ->and(array_keys($invoice->vatBreakdown()))->toBe(['VAT 20']);
});

test('topping up a balance is not discounted', function () {
    Mail::fake();

    $client = groupedClient(15);

    $invoice = app(InvoiceService::class)->createInvoice($client, [
        ['type' => 'AddFunds', 'rel_id' => 0, 'description' => 'Add Funds', 'amount' => 100, 'taxed' => false],
    ]);

    expect((float) $invoice->total)->toEqual(100.0);
});
