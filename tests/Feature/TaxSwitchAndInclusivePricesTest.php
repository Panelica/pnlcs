<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\Pricing;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Quote;
use App\Models\Setting;
use App\Models\TaxRule;
use App\Services\CartService;
use App\Services\QuoteService;
use Illuminate\Support\Facades\Mail;

/*
 * #305, the second half. Tax could only be stopped by deleting every rule,
 * and prices could only be entered without tax, so a shop advertising "€6 a
 * month" charged €7.20 at checkout. Two switches on the tax page: charge tax
 * at all (on by default), and prices that already include it (off by
 * default). With both at their defaults nothing changes.
 */

beforeEach(fn () => Mail::fake());

function tsiOrder(float $price = 6.0): array
{
    $currency = Currency::where('is_default', true)->first()
        ?? Currency::create(['code' => 'EUR', 'prefix' => '€', 'suffix' => '', 'rate' => 1, 'is_default' => true]);
    $client = Client::factory()->create(['tax_exempt' => false]);
    TaxRule::create(['name' => 'VAT', 'country' => $client->country, 'state' => '', 'tax_rate' => 20, 'is_default' => true]);
    $product = Product::factory()->create(['group_id' => ProductGroup::factory()->create()->id, 'tax' => true, 'auto_setup' => 'payment', 'server_type' => null]);
    Pricing::updateOrCreate(['type' => 'product', 'rel_id' => $product->id, 'currency_id' => $currency->id], ['monthly' => $price]);

    $carts = app(CartService::class);
    $cart = $carts->getOrCreateCart($client->id);
    $carts->addProduct($cart, $product, 'monthly', 'site.example.com');
    $quoted = $carts->calculateTotal($cart->fresh());
    $invoice = Invoice::find($carts->checkout($cart->fresh(), $client->id, 'banktransfer')->invoice_id);

    return [$quoted, $invoice, $client];
}

test('by default tax is added on top, as before', function () {
    [$quoted, $invoice] = tsiOrder();

    expect((float) $quoted['total'])->toBe(7.2)
        ->and((float) $invoice->tax)->toBe(1.2)
        ->and((float) $invoice->total)->toBe(7.2);
});

test('with tax switched off nothing is added, though the rules stay', function () {
    Setting::set('TaxEnabled', '0');
    [$quoted, $invoice, $client] = tsiOrder();

    expect((float) $quoted['tax'])->toBe(0.0)->and((float) $quoted['total'])->toBe(6.0)
        ->and((float) $invoice->tax)->toBe(0.0)->and((float) $invoice->total)->toBe(6.0)
        ->and(TaxRule::count())->toBe(1)
        ->and(TaxRule::defaultRate())->toBe(0.0);

    $quote = Quote::create(['client_id' => $client->id, 'subject' => 'Q', 'date' => now(), 'valid_until' => now()->addWeek(), 'stage' => 'draft']);
    $quote->items()->create(['description' => 'Work', 'quantity' => 1, 'unit_price' => 100, 'discount' => 0, 'taxable' => true]);
    expect((float) app(QuoteService::class)->recalculateTotals($quote)->total)->toBe(100.0);
});

test('with prices that include tax the customer pays the advertised price, and the tax is the part it contains', function () {
    Setting::set('TaxInclusivePrices', '1');
    [$quoted, $invoice, $client] = tsiOrder();

    expect((float) $quoted['total'])->toBe(6.0)
        ->and((float) $quoted['tax'])->toBe(1.0)
        ->and((float) $invoice->total)->toBe(6.0)
        ->and((float) $invoice->tax)->toBe(1.0)
        ->and((float) $invoice->subtotal)->toBe(5.0);

    $quote = Quote::create(['client_id' => $client->id, 'subject' => 'Q', 'date' => now(), 'valid_until' => now()->addWeek(), 'stage' => 'draft']);
    $quote->items()->create(['description' => 'Work', 'quantity' => 1, 'unit_price' => 120, 'discount' => 0, 'taxable' => true]);
    $quote = app(QuoteService::class)->recalculateTotals($quote);
    expect((float) $quote->total)->toBe(120.0)->and((float) $quote->tax)->toBe(20.0)->and((float) $quote->subtotal)->toBe(100.0);
});

test('the tax page saves both switches', function () {
    $admin = Admin::factory()->create(['role_id' => AdminRole::factory()->fullAdmin()->create()->id]);

    $this->actingAs($admin, 'admin')->get(route('admin.config.tax'))->assertOk()
        ->assertSee('name="TaxEnabled"', false)->assertSee('name="TaxInclusivePrices"', false)->assertSee(__('admin.tax.prices_include_tax'));

    $this->actingAs($admin, 'admin')->post(route('admin.settings.general.update'), ['TaxEnabled' => '0', 'TaxInclusivePrices' => '1'])->assertSessionHasNoErrors();
    expect(TaxRule::enabled())->toBeFalse()->and(TaxRule::pricesIncludeTax())->toBeTrue();
});
