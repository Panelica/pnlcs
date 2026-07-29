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

    TaxRule::create(['level' => 1, 'name' => 'VAT', 'country' => 'GB', 'state' => '', 'tax_rate' => 20]);

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

test('topping up a balance is not discounted', function () {
    Mail::fake();

    $client = groupedClient(15);

    $invoice = app(InvoiceService::class)->createInvoice($client, [
        ['type' => 'AddFunds', 'rel_id' => 0, 'description' => 'Add Funds', 'amount' => 100, 'taxed' => false],
    ]);

    expect((float) $invoice->total)->toEqual(100.0);
});
