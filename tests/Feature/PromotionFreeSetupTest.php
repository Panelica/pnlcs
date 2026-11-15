<?php

use App\Models\Client;
use App\Models\Currency;
use App\Models\InvoiceItem;
use App\Models\Pricing;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Promotion;
use App\Models\TaxRule;
use App\Services\CartService;
use Illuminate\Support\Facades\Mail;

/*
 * The promotions form offers a "free setup" type, and nothing read it: the
 * invoice gave no discount for it, while the cart - which treated every type
 * but a percentage as a fixed amount of the code's value - quoted the value
 * as money off. A free-setup code now waives the setup fees of the lines it
 * covers, in the cart and on the invoice alike, and the cart no longer quotes
 * a discount for a type the invoice gives nothing for.
 */

beforeEach(fn () => Mail::fake());

function pfsProduct(float $monthly, float $setup, bool $taxed = false): Product
{
    $product = Product::factory()->create(['group_id' => ProductGroup::factory()->create()->id, 'tax' => $taxed]);
    $currency = Currency::getDefault()?->id ?? Currency::factory()->create(['is_default' => true])->id;
    Pricing::create(['type' => 'product', 'currency_id' => $currency, 'rel_id' => $product->id, 'monthly' => $monthly, 'monthly_setup' => $setup]);

    return $product;
}

/** @param  array<int, Product>  $products */
function pfsOrder(array $products, string $code, array $client = ['tax_exempt' => true]): array
{
    $client = Client::factory()->create($client);
    $carts = app(CartService::class);
    $cart = $carts->getOrCreateCart($client->id);

    foreach ($products as $i => $product) {
        $carts->addProduct($cart, $product, 'monthly', "site{$i}.example.com");
    }

    $carts->applyPromoCode($cart, $code);
    $quoted = $carts->calculateTotal($cart->fresh());
    $order = $carts->checkout($cart->fresh(), $client->id, 'banktransfer');

    return [$quoted, $order->invoice->fresh(), $order->fresh()];
}

function pfsDiscounts($invoice): array
{
    return InvoiceItem::where('invoice_id', $invoice->id)->where('type', 'Discount')->orderBy('id')
        ->pluck('amount')->map(fn ($a) => (float) $a)->all();
}

test('a free-setup code waives the setup fee and leaves the price alone', function () {
    Promotion::create(['code' => 'NOSETUP', 'type' => 'free_setup', 'value' => 0]);

    [$quoted, $invoice] = pfsOrder([pfsProduct(10, 25)], 'NOSETUP');

    expect($quoted['promo_discount'])->toEqual(25.0)
        ->and($quoted['total'])->toEqual(10.0)
        ->and(pfsDiscounts($invoice))->toBe([-25.0])
        ->and((float) $invoice->total)->toEqual(10.0);
});

test('the value of a free-setup code is not money off', function () {
    Promotion::create(['code' => 'NOSETUP5', 'type' => 'free_setup', 'value' => 5]);

    [$quoted, $invoice] = pfsOrder([pfsProduct(10, 25)], 'NOSETUP5');

    expect($quoted['promo_discount'])->toEqual(25.0)
        ->and((float) $invoice->total)->toEqual(10.0);
});

test('every setup fee in the order is waived by an unrestricted code', function () {
    Promotion::create(['code' => 'NOSETUP', 'type' => 'free_setup', 'value' => 0]);

    [$quoted, $invoice] = pfsOrder([pfsProduct(10, 25), pfsProduct(30, 15)], 'NOSETUP');

    expect($quoted['promo_discount'])->toEqual(40.0)
        ->and($quoted['total'])->toEqual(40.0)
        ->and((float) $invoice->total)->toEqual(40.0);
});

test('a code limited to a product waives only that product\'s setup fee', function () {
    $covered = pfsProduct(10, 25);
    $other = pfsProduct(30, 15);
    Promotion::create(['code' => 'ONLYA', 'type' => 'free_setup', 'value' => 0, 'applies_to' => json_encode([$covered->id])]);

    [$quoted, $invoice] = pfsOrder([$covered, $other], 'ONLYA');

    expect($quoted['promo_discount'])->toEqual(25.0)
        ->and($quoted['total'])->toEqual(55.0)
        ->and(pfsDiscounts($invoice))->toBe([-25.0])
        ->and((float) $invoice->total)->toEqual(55.0);
});

test('a code limited to one product per order waives one setup fee, that of the highest-priced product', function () {
    Promotion::create(['code' => 'ONE', 'type' => 'free_setup', 'value' => 0, 'max_products' => 1]);

    [$quoted, $invoice] = pfsOrder([pfsProduct(10, 25), pfsProduct(30, 15)], 'ONE');

    expect($quoted['promo_discount'])->toEqual(15.0)
        ->and(pfsDiscounts($invoice))->toBe([-15.0])
        ->and((float) $invoice->total)->toEqual((float) $quoted['total']);
});

test('the waived fee takes its tax with it', function () {
    TaxRule::create(['name' => 'VAT', 'country' => 'TR', 'state' => '', 'tax_rate' => 20]);
    Promotion::create(['code' => 'NOSETUP', 'type' => 'free_setup', 'value' => 0]);

    [$quoted, $invoice] = pfsOrder([pfsProduct(100, 50, taxed: true)], 'NOSETUP', ['tax_exempt' => false, 'country' => 'TR']);

    expect($quoted['promo_discount'])->toEqual(50.0)
        ->and($quoted['total'])->toEqual(120.0)
        ->and((float) $invoice->total)->toEqual(120.0);
});

test('with no setup fee to waive the code gives nothing and is not spent', function () {
    $promo = Promotion::create(['code' => 'NOSETUP', 'type' => 'free_setup', 'value' => 10]);

    [$quoted, $invoice, $order] = pfsOrder([pfsProduct(10, 0)], 'NOSETUP');

    expect($quoted['promo_discount'])->toEqual(0.0)
        ->and(pfsDiscounts($invoice))->toBe([])
        ->and((float) $invoice->total)->toEqual(10.0)
        ->and($promo->fresh()->uses)->toBe(0);
});

test('the cart quotes nothing for a type the invoice gives nothing for', function (string $type) {
    Promotion::create(['code' => 'OVERRIDE', 'type' => $type, 'value' => 5]);

    [$quoted, $invoice] = pfsOrder([pfsProduct(10, 25)], 'OVERRIDE');

    expect($quoted['promo_discount'])->toEqual(0.0)
        ->and($quoted['total'])->toEqual(35.0)
        ->and((float) $invoice->total)->toEqual(35.0);
})->with(['price_override', 'override_recurring']);

test('percentage and fixed codes are worked out as before', function () {
    Promotion::create(['code' => 'TEN', 'type' => 'percentage', 'value' => 10]);
    Promotion::create(['code' => 'FIVE', 'type' => 'fixed_amount', 'value' => 5]);

    [$percent, $percentInvoice] = pfsOrder([pfsProduct(10, 25)], 'TEN');
    [$fixed, $fixedInvoice] = pfsOrder([pfsProduct(10, 25)], 'FIVE');

    expect($percent['promo_discount'])->toEqual(3.5)
        ->and((float) $percentInvoice->total)->toEqual(31.5)
        ->and($fixed['promo_discount'])->toEqual(5.0)
        ->and((float) $fixedInvoice->total)->toEqual(30.0);
});
