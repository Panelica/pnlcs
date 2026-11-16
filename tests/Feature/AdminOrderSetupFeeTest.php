<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;
use App\Models\Currency;
use App\Models\GatewaySettings;
use App\Models\InvoiceItem;
use App\Models\Order;
use App\Models\Pricing;
use App\Models\Product;
use App\Models\ProductGroup;
use Illuminate\Support\Facades\Mail;

/*
 * An order placed by staff left the product's setup fee out: a plan sold at
 * "10 a month plus 25 setup" was billed 10 when staff raised it and 35 when
 * the customer ordered it themselves. The new order form now charges the fee
 * as the shop does, with a box staff can clear when the sale waives it.
 */

function aosAdmin(): Admin
{
    return Admin::factory()->create(['role_id' => AdminRole::factory()->create(['name' => 'R'.uniqid(), 'permissions' => ['list_orders', 'view_orders', 'manage_orders']])->id]);
}

function aosProduct(float $monthly, float $setup): Product
{
    $product = Product::factory()->create(['group_id' => ProductGroup::factory()->create()->id, 'type' => 'other', 'server_type' => null, 'hidden' => false, 'retired' => false, 'tax' => false]);
    Pricing::where('rel_id', $product->id)->where('type', 'product')->delete();
    Pricing::create(['type' => 'product', 'rel_id' => $product->id, 'currency_id' => Currency::getDefault()?->id ?? Currency::factory()->default()->create()->id,
        'monthly' => $monthly, 'monthly_setup' => $setup, 'quarterly' => -1, 'semiannually' => -1, 'annually' => -1, 'biennially' => -1, 'triennially' => -1]);

    return $product;
}

function aosPlace(Product $product, array $extra = []): Order
{
    $client = Client::factory()->create(['tax_exempt' => true]);

    test()->actingAs(aosAdmin(), 'admin')->post(route('admin.orders.store'), $extra + [
        'client' => (string) $client->id, 'product_id' => $product->id, 'billing_cycle' => 'monthly', 'payment_method' => 'banktransfer',
    ])->assertRedirect()->assertSessionHasNoErrors();

    return Order::where('client_id', $client->id)->latest('id')->firstOrFail();
}

function aosSetupLines(Order $order): array
{
    return InvoiceItem::where('invoice_id', $order->invoice_id)->where('type', 'Setup')->pluck('amount')->map(fn ($a) => (float) $a)->all();
}

beforeEach(function () {
    Mail::fake();
    GatewaySettings::updateOrCreate(['gateway' => 'banktransfer', 'setting' => 'active'], ['value' => '1']);
});

it('charges the setup fee on an order placed by staff, as a line of its own', function () {
    $order = aosPlace(aosProduct(10, 25), ['setup_fee' => '1']);

    expect(aosSetupLines($order))->toBe([25.0])
        ->and((float) $order->invoice->total)->toBe(35.0);
});

it('charges it when the form field is not sent at all', function () {
    $order = aosPlace(aosProduct(10, 25));

    expect((float) $order->invoice->total)->toBe(35.0);
});

it('leaves it off when staff clear the box', function () {
    $order = aosPlace(aosProduct(10, 25), ['setup_fee' => '0']);

    expect(aosSetupLines($order))->toBe([])
        ->and((float) $order->invoice->total)->toBe(10.0);
});

it('adds it to a price staff set themselves', function () {
    $order = aosPlace(aosProduct(10, 25), ['price' => '7', 'setup_fee' => '1']);

    expect((float) $order->invoice->total)->toBe(32.0);
});

it('adds nothing for a product without a setup fee', function () {
    $order = aosPlace(aosProduct(10, 0), ['setup_fee' => '1']);

    expect(aosSetupLines($order))->toBe([])
        ->and((float) $order->invoice->total)->toBe(10.0);
});

it('shows the box on the form, ticked', function () {
    test()->actingAs(aosAdmin(), 'admin')->get(route('admin.orders.create'))->assertOk()
        ->assertSee('name="setup_fee" value="1" checked', false)
        ->assertSee(__('admin.orders.new_setup_fee'));
});
