<?php

use App\Models\Client;
use App\Models\ClientGroup;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\Pricing;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Promotion;
use App\Models\Setting;
use App\Models\TaxRule;
use App\Services\CartService;
use App\Services\InvoiceService;
use Illuminate\Support\Facades\Mail;

/**
 * With prices that include tax, a percentage late fee is a percentage of the
 * invoice the customer was sent. The command worked it out on the subtotal,
 * which in that mode has the tax taken out: 5% on a 120 invoice came to 5,
 * not 6.
 */
beforeEach(fn () => Mail::fake());

function inclusiveLateFeeInvoice(float $taxed, float $untaxed = 0, string $feeType = 'percent', string $fee = '5', float $groupDiscount = 0, float $promo = 0, string $inclusive = '1', float $credit = 0): Invoice
{
    Setting::set('TaxInclusivePrices', $inclusive);
    Setting::set('LateFeeType', $feeType, 'general');
    Setting::set('LateFeeAmount', $fee, 'general');
    Setting::set('LateFeeMinDays', '3', 'general');

    $currency = Currency::where('is_default', true)->first()
        ?? Currency::create(['code' => 'EUR', 'prefix' => '€', 'suffix' => '', 'rate' => 1, 'is_default' => true]);

    $client = Client::factory()->create(['tax_exempt' => false, 'credit' => $credit] + ($groupDiscount > 0
        ? ['group_id' => ClientGroup::create(['name' => 'Resellers', 'discount_percent' => $groupDiscount])->id]
        : []));
    TaxRule::create(['name' => 'VAT', 'country' => $client->country, 'state' => '', 'tax_rate' => 20]);

    $group = ProductGroup::factory()->create();
    $cart = app(CartService::class);
    $c = $cart->getOrCreateCart($client->id);

    foreach (['taxed' => [$taxed, true], 'untaxed' => [$untaxed, false]] as $key => [$price, $tax]) {
        if ($price <= 0) {
            continue;
        }

        $product = Product::factory()->create(['group_id' => $group->id, 'tax' => $tax]);
        Pricing::updateOrCreate(
            ['type' => 'product', 'rel_id' => $product->id, 'currency_id' => $currency->id],
            ['monthly' => $price]
        );
        $cart->addProduct($c, $product, 'monthly', "late-{$key}.test");
    }

    if ($promo > 0) {
        Promotion::create([
            'code' => 'LATE', 'type' => 'percentage', 'value' => $promo, 'applies_to' => null,
            'cycles' => null, 'max_uses' => 0, 'uses' => 0,
            'start_date' => now()->subDay(), 'expiration_date' => now()->addYear(),
        ]);
        $cart->applyPromoCode($c, 'LATE');
    }

    $invoice = Invoice::findOrFail($cart->checkout($c, $client->id, 'banktransfer')->invoice_id);
    $invoice->update(['status' => 'overdue', 'due_date' => now()->subDays(10)]);

    test()->artisan('pnlcs:apply-late-fees')->assertSuccessful();

    return $invoice->fresh();
}

function lateFeeCharged(Invoice $invoice): float
{
    return (float) $invoice->items()->where('type', 'LateFee')->sum('amount');
}

test('with prices that include tax a percentage late fee is a percentage of the invoice', function () {
    $invoice = inclusiveLateFeeInvoice(120);

    expect(lateFeeCharged($invoice))->toEqual(6.0)
        ->and((float) $invoice->total)->toEqual(126.0)
        ->and((float) $invoice->tax)->toEqual(20.0);
});

test('with prices that include tax a percentage late fee follows the group discount and the code', function () {
    $invoice = inclusiveLateFeeInvoice(120, groupDiscount: 10, promo: 10);

    expect(lateFeeCharged($invoice))->toEqual(4.86)
        ->and((float) $invoice->total)->toEqual(102.06)
        ->and((float) $invoice->tax)->toEqual(16.2);
});

test('with prices that include tax a percentage late fee counts taxed and untaxed work alike', function () {
    $invoice = inclusiveLateFeeInvoice(120, 100);

    expect(lateFeeCharged($invoice))->toEqual(11.0)
        ->and((float) $invoice->total)->toEqual(231.0)
        ->and((float) $invoice->tax)->toEqual(20.0);
});

test('with prices that include tax a percentage late fee is worked out before credit', function () {
    $invoice = inclusiveLateFeeInvoice(120, credit: 20);

    expect((float) $invoice->credit)->toEqual(20.0)
        ->and(lateFeeCharged($invoice))->toEqual(6.0)
        ->and((float) $invoice->total)->toEqual(106.0)
        ->and((float) $invoice->tax)->toEqual(20.0);
});

test('with prices that include tax a percentage late fee is charged on an invoice with a negative line', function () {
    Setting::set('TaxInclusivePrices', '1');
    Setting::set('LateFeeType', 'percent', 'general');
    Setting::set('LateFeeAmount', '5', 'general');
    Setting::set('LateFeeMinDays', '3', 'general');

    $client = Client::factory()->create(['tax_exempt' => false]);
    TaxRule::create(['name' => 'VAT', 'country' => $client->country, 'state' => '', 'tax_rate' => 20]);

    $invoice = Invoice::factory()->create(['client_id' => $client->id, 'tax_rate' => 0, 'tax_mode' => Invoice::TAX_INCLUSIVE]);
    $service = app(InvoiceService::class);
    $service->addLineItem($invoice, ['description' => 'Hosting', 'amount' => 120, 'qty' => 1, 'tax_rate' => 20]);
    $service->addLineItem($invoice, ['description' => 'Goodwill', 'amount' => -110, 'qty' => 1, 'tax_rate' => 0]);

    $invoice->refresh()->update(['status' => 'overdue', 'due_date' => now()->subDays(10)]);
    expect((float) $invoice->total)->toEqual(10.0);

    $this->artisan('pnlcs:apply-late-fees')->assertSuccessful();
    $invoice = $invoice->fresh();

    expect(lateFeeCharged($invoice))->toEqual(0.5)
        ->and((float) $invoice->total)->toEqual(10.5)
        ->and((float) $invoice->tax)->toEqual(20.0);
});

test('with prices that include tax a flat late fee is unchanged', function () {
    $invoice = inclusiveLateFeeInvoice(120, feeType: 'flat', fee: '10');

    expect(lateFeeCharged($invoice))->toEqual(10.0)
        ->and((float) $invoice->total)->toEqual(130.0)
        ->and((float) $invoice->tax)->toEqual(20.0);
});

test('with tax added on top a percentage late fee is unchanged', function () {
    $invoice = inclusiveLateFeeInvoice(120, inclusive: '0');

    expect(lateFeeCharged($invoice))->toEqual(6.0)
        ->and((float) $invoice->total)->toEqual(150.0)
        ->and((float) $invoice->tax)->toEqual(24.0);
});
