<?php

use App\Models\Affiliate;
use App\Models\Client;
use App\Models\ClientGroup;
use App\Models\Setting;
use App\Models\TaxRule;
use App\Services\InvoiceService;
use App\Services\PaymentService;
use Illuminate\Support\Facades\Mail;

/**
 * With prices that include tax, the invoice lines carry the tax, so a
 * commission worked out on the lines was a share of the tax too: at 20% VAT a
 * 10% affiliate earned 12 on a 120 sale that leaves the shop 100.
 */
beforeEach(fn () => Mail::fake());

function inclusiveCommission(array $lines, string $inclusive = '1', float $groupDiscount = 0, bool $exempt = false, string $taxOn = '1'): float
{
    Setting::set('TaxEnabled', $taxOn);
    Setting::set('TaxInclusivePrices', $inclusive);

    $partner = Client::factory()->create(['email' => 'partner@example.test']);
    $affiliate = Affiliate::create([
        'client_id' => $partner->id, 'visitors' => 0, 'pay_type' => 'percentage',
        'pay_amount' => 10, 'onetime' => false, 'balance' => 0, 'withdrawn' => 0,
    ]);

    $client = Client::factory()->create(['affiliate_id' => $affiliate->id, 'tax_exempt' => $exempt] + ($groupDiscount > 0
        ? ['group_id' => ClientGroup::create(['name' => 'Resellers', 'discount_percent' => $groupDiscount])->id]
        : []));
    TaxRule::create(['name' => 'VAT', 'country' => $client->country, 'state' => '', 'tax_rate' => 20]);

    $invoice = app(InvoiceService::class)->createInvoice($client->fresh(), $lines);
    app(PaymentService::class)->applyPayment($invoice->fresh(), 'stripe', 'TXN-'.uniqid(), (float) $invoice->fresh()->total);

    return (float) $affiliate->fresh()->balance;
}

it('takes the tax out of a taxed line', function () {
    expect(inclusiveCommission([['type' => 'Hosting', 'description' => 'Plan', 'amount' => 120, 'taxed' => true]]))
        ->toBe(10.0);
});

it('takes it out of the taxed line only', function () {
    expect(inclusiveCommission([
        ['type' => 'Hosting', 'description' => 'Plan', 'amount' => 120, 'taxed' => true],
        ['type' => 'Other', 'description' => 'Setup', 'amount' => 100, 'taxed' => false],
    ]))->toBe(20.0);
});

it('works from the lines after the group discount', function () {
    expect(inclusiveCommission([['type' => 'Hosting', 'description' => 'Plan', 'amount' => 120, 'taxed' => true]], groupDiscount: 10))
        ->toBe(9.0);
});

it('counts every unit of a line', function () {
    expect(inclusiveCommission([['type' => 'Hosting', 'description' => 'Plan', 'amount' => 60, 'qty' => 2, 'taxed' => true]]))
        ->toBe(10.0);
});

it('takes out each line its own rate', function () {
    expect(inclusiveCommission([
        ['type' => 'Other', 'description' => 'Standard', 'amount' => 120, 'tax_rate' => 20],
        ['type' => 'Other', 'description' => 'Reduced', 'amount' => 108, 'tax_rate' => 8],
    ]))->toBe(20.0);
});

it('is unchanged with tax added on top', function () {
    expect(inclusiveCommission([['type' => 'Hosting', 'description' => 'Plan', 'amount' => 100, 'taxed' => true]], inclusive: '0'))
        ->toBe(10.0);
});

it('is unchanged with tax off', function () {
    expect(inclusiveCommission([['type' => 'Hosting', 'description' => 'Plan', 'amount' => 120, 'taxed' => true]], taxOn: '0'))
        ->toBe(12.0);
});

it('takes nothing out for a buyer who pays no tax', function () {
    expect(inclusiveCommission([['type' => 'Hosting', 'description' => 'Plan', 'amount' => 120, 'taxed' => true]], exempt: true))
        ->toBe(12.0);
});
