<?php

use App\Models\Client;
use App\Models\ClientGroup;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Promotion;
use App\Models\Service;
use App\Services\InvoiceGenerationService;
use App\Services\InvoiceService;
use Illuminate\Support\Facades\Mail;

/**
 * A promo code takes its discount off in one line per VAT rate, the way the
 * group discount does. One line without a rate took no tax off an invoice
 * whose lines carry two rates, and printed a VAT row of its own next to a
 * single rate.
 */
function vatRateInvoice(array $lines): Invoice
{
    return app(InvoiceService::class)->createInvoice(Client::factory()->create(['tax_exempt' => false]), $lines);
}

function vatRatePromo(Invoice $invoice, string $type, float $value, ?array $appliesTo = null): Invoice
{
    Promotion::create(['code' => 'VATPROMO', 'type' => $type, 'value' => $value, 'applies_to' => $appliesTo ? json_encode($appliesTo) : null]);

    expect(app(InvoiceGenerationService::class)->applyPromotion($invoice->fresh(), 'VATPROMO'))->toBeTrue();

    return $invoice->fresh();
}

test('two VAT rates get a promo line each and the VAT table adds up', function () {
    Mail::fake();

    $invoice = vatRatePromo(vatRateInvoice([
        ['type' => 'Other', 'description' => 'Standard', 'amount' => 100, 'tax_rate' => 20, 'tax_label' => 'VAT 20'],
        ['type' => 'Other', 'description' => 'Reduced', 'amount' => 100, 'tax_rate' => 10, 'tax_label' => 'VAT 10'],
    ]), 'percentage', 10);

    $table = $invoice->vatBreakdown();

    expect((float) $invoice->tax)->toEqual(27.0)
        ->and((float) $invoice->total)->toEqual(207.0)
        ->and($invoice->items->where('type', 'Discount'))->toHaveCount(2)
        ->and(round($table['VAT 20']['amount'], 2))->toEqual(18.0)
        ->and(round($table['VAT 20']['net'], 2))->toEqual(90.0)
        ->and(round($table['VAT 10']['amount'], 2))->toEqual(9.0)
        ->and(round($table['VAT 10']['net'], 2))->toEqual(90.0);
});

test('one VAT rate keeps a single promo line, inside that rate', function () {
    Mail::fake();

    $invoice = vatRatePromo(vatRateInvoice([
        ['type' => 'Other', 'description' => 'One', 'amount' => 100, 'tax_rate' => 20, 'tax_label' => 'VAT 20'],
        ['type' => 'Other', 'description' => 'Two', 'amount' => 100, 'tax_rate' => 20, 'tax_label' => 'VAT 20'],
    ]), 'percentage', 10);

    expect($invoice->items->where('type', 'Discount'))->toHaveCount(1)
        ->and((float) $invoice->tax)->toEqual(36.0)
        ->and(array_keys($invoice->vatBreakdown()))->toBe(['VAT 20']);
});

test('a fixed amount is shared by net, the leftover cent on the first rate of a tie', function () {
    Mail::fake();

    $invoice = vatRatePromo(vatRateInvoice([
        ['type' => 'Other', 'description' => 'Standard', 'amount' => 100, 'tax_rate' => 20, 'tax_label' => 'VAT 20'],
        ['type' => 'Other', 'description' => 'Reduced', 'amount' => 100, 'tax_rate' => 10, 'tax_label' => 'VAT 10'],
        ['type' => 'Other', 'description' => 'Low', 'amount' => 100, 'tax_rate' => 5, 'tax_label' => 'VAT 5'],
    ]), 'fixed_amount', 10);

    $discounts = $invoice->items->where('type', 'Discount');

    expect($discounts->map(fn ($item) => [$item->tax_label, (float) $item->amount])->values()->all())
        ->toBe([['VAT 20', -3.34], ['VAT 10', -3.33], ['VAT 5', -3.33]])
        ->and(round((float) $discounts->sum('amount'), 2))->toEqual(-10.0)
        ->and((float) $invoice->subtotal)->toEqual(290.0)
        ->and((float) $invoice->tax)->toEqual(33.83)
        ->and((float) $invoice->total)->toEqual(323.83);
});

test('a fixed amount of a few cents is shared without a negative line', function () {
    Mail::fake();

    $invoice = vatRatePromo(vatRateInvoice([
        ['type' => 'Other', 'description' => 'A', 'amount' => 100, 'tax_rate' => 20, 'tax_label' => 'VAT 20'],
        ['type' => 'Other', 'description' => 'B', 'amount' => 100, 'tax_rate' => 10, 'tax_label' => 'VAT 10'],
        ['type' => 'Other', 'description' => 'C', 'amount' => 100, 'tax_rate' => 5, 'tax_label' => 'VAT 5'],
        ['type' => 'Other', 'description' => 'D', 'amount' => 100, 'tax_rate' => 8, 'tax_label' => 'VAT 8'],
    ]), 'fixed_amount', 0.02);

    $discounts = $invoice->items->where('type', 'Discount');

    expect($discounts->map(fn ($item) => [$item->tax_label, (float) $item->amount])->values()->all())
        ->toBe([['VAT 20', -0.01], ['VAT 10', -0.01]])
        ->and((float) $invoice->subtotal)->toEqual(399.98);
});

test('next to a rate, untaxed lines without one take no tax off', function () {
    Mail::fake();

    $invoice = vatRatePromo(vatRateInvoice([
        ['type' => 'Other', 'description' => 'Standard', 'amount' => 100, 'tax_rate' => 20, 'tax_label' => 'VAT 20', 'taxed' => true],
        ['type' => 'Other', 'description' => 'Untaxed', 'amount' => 50, 'taxed' => false],
    ]), 'percentage', 10);

    $discounts = $invoice->items->where('type', 'Discount');

    expect($discounts->map(fn ($item) => [$item->tax_label, (bool) $item->taxed, (float) $item->amount])->values()->all())
        ->toBe([['VAT 20', true, -10.0], [null, false, -5.0]])
        ->and((float) $invoice->tax)->toEqual(18.0)
        ->and((float) $invoice->total)->toEqual(153.0);
});

test('a code limited to a product discounts only that product\'s rate', function () {
    Mail::fake();

    $client = Client::factory()->create(['tax_exempt' => false]);
    $product = Product::factory()->create(['group_id' => ProductGroup::factory()->create()->id]);
    $service = Service::factory()->create(['client_id' => $client->id, 'product_id' => $product->id, 'status' => 'active']);

    $invoice = vatRatePromo(app(InvoiceService::class)->createInvoice($client, [
        ['type' => 'Hosting', 'rel_id' => $service->id, 'description' => 'Plan', 'amount' => 100, 'tax_rate' => 20, 'tax_label' => 'VAT 20'],
        ['type' => 'Other', 'description' => 'Setup', 'amount' => 100, 'tax_rate' => 10, 'tax_label' => 'VAT 10'],
    ]), 'percentage', 10, [$product->id]);

    $discounts = $invoice->items->where('type', 'Discount');

    expect($discounts)->toHaveCount(1)
        ->and($discounts->first()->tax_label)->toBe('VAT 20')
        ->and((float) $discounts->first()->amount)->toEqual(-10.0)
        ->and((float) $invoice->tax)->toEqual(28.0);
});

test('after a group discount the code takes its share of what is left, per rate', function () {
    Mail::fake();

    $client = Client::factory()->create([
        'tax_exempt' => false,
        'group_id' => ClientGroup::create(['name' => 'Partners', 'color' => '#eab308', 'discount_percent' => 10])->id,
    ]);

    $invoice = vatRatePromo(app(InvoiceService::class)->createInvoice($client, [
        ['type' => 'Other', 'description' => 'Standard', 'amount' => 100, 'tax_rate' => 20, 'tax_label' => 'VAT 20'],
        ['type' => 'Other', 'description' => 'Reduced', 'amount' => 100, 'tax_rate' => 10, 'tax_label' => 'VAT 10'],
    ]), 'percentage', 10);

    $promo = $invoice->items->where('type', 'Discount')->filter(fn ($item) => str_starts_with($item->description, 'Promo code'));
    $table = $invoice->vatBreakdown();

    expect($promo->map(fn ($item) => [$item->tax_label, (float) $item->amount])->values()->all())
        ->toBe([['VAT 20', -9.0], ['VAT 10', -9.0]])
        ->and(round($table['VAT 20']['net'], 2))->toEqual(81.0)
        ->and(round($table['VAT 10']['net'], 2))->toEqual(81.0)
        ->and((float) $invoice->tax)->toEqual(24.3)
        ->and((float) $invoice->total)->toEqual(186.3);
});

test('lines without a rate get a taxed and an untaxed promo line, still without a rate', function () {
    Mail::fake();

    $invoice = vatRatePromo(vatRateInvoice([
        ['type' => 'Hosting', 'rel_id' => 0, 'description' => 'Hosting', 'amount' => 100, 'taxed' => true],
        ['type' => 'Other', 'description' => 'Untaxed', 'amount' => 50, 'taxed' => false],
    ]), 'percentage', 10);

    $discounts = $invoice->items->where('type', 'Discount')->values();

    expect($discounts)->toHaveCount(2)
        ->and($discounts->map(fn ($line) => [(float) $line->amount, (bool) $line->taxed])->all())->toBe([[-10.0, true], [-5.0, false]])
        ->and($discounts->whereNotNull('tax_rate'))->toHaveCount(0)
        ->and($discounts->whereNotNull('tax_label'))->toHaveCount(0)
        ->and($discounts->whereNotNull('tax_rule_id'))->toHaveCount(0);
});

test('without a promo code the invoice has no discount line', function () {
    Mail::fake();

    $invoice = vatRateInvoice([
        ['type' => 'Other', 'description' => 'Standard', 'amount' => 100, 'tax_rate' => 20, 'tax_label' => 'VAT 20'],
        ['type' => 'Other', 'description' => 'Reduced', 'amount' => 100, 'tax_rate' => 10, 'tax_label' => 'VAT 10'],
    ]);

    expect($invoice->items()->where('type', 'Discount')->count())->toBe(0)
        ->and((float) $invoice->tax)->toEqual(30.0)
        ->and((float) $invoice->total)->toEqual(230.0);
});

test('a renewal with a recurring code keeps one promo line, its lines carrying no rate', function () {
    Mail::fake();

    $client = Client::factory()->create();
    Promotion::create(['code' => 'RENEW10', 'type' => 'percentage', 'value' => 10, 'recurring' => true, 'cycles' => 3, 'uses' => 0, 'max_uses' => 0]);
    $order = Order::factory()->create(['client_id' => $client->id, 'promo_code' => 'RENEW10']);
    $group = ProductGroup::factory()->create();

    foreach ([100, 50] as $n => $amount) {
        Service::factory()->create([
            'client_id' => $client->id,
            'order_id' => $order->id,
            'product_id' => Product::factory()->create(['group_id' => $group->id])->id,
            'status' => 'active',
            'amount' => $amount,
            'billing_cycle' => 'Monthly',
            'next_due_date' => now()->addDays(3),
            'auto_renew' => true,
            'domain' => "renewal-{$n}.test",
        ]);
    }

    app(InvoiceGenerationService::class)->generateDueInvoices();

    $invoice = Invoice::where('client_id', $client->id)->latest('id')->first();
    $discounts = $invoice->items->where('type', 'Discount');

    expect($invoice->items->where('type', '!=', 'Discount')->whereNotNull('tax_rate'))->toHaveCount(0)
        ->and($invoice->items->where('type', '!=', 'Discount'))->toHaveCount(2)
        ->and($discounts)->toHaveCount(1)
        ->and((float) $discounts->first()->amount)->toEqual(-15.0)
        ->and($discounts->first()->tax_rate)->toBeNull();
});
