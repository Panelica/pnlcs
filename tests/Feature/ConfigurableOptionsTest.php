<?php

use App\Models\Admin;
use App\Models\Client;
use App\Models\ConfigOption;
use App\Models\ConfigOptionGroup;
use App\Models\ConfigOptionLink;
use App\Models\ConfigOptionSub;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\Pricing;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Service;
use App\Models\ServiceConfigOption;
use App\Models\User;
use App\Services\CartService;
use App\Services\ConfigOptionService;
use Illuminate\Validation\ValidationException;

/**
 * Configurable options existed only as an admin screen: the customer order form
 * never rendered them and the cart ignored their prices, so an option cost
 * nothing no matter what the operator configured. This covers the feature end
 * to end — offering, pricing, ordering and renewal.
 */
function optionCurrency(): Currency
{
    return Currency::where('is_default', true)->first()
        ?? Currency::create(['code' => 'USD', 'prefix' => '$', 'suffix' => '', 'rate' => 1, 'is_default' => true]);
}

function priceSub(ConfigOptionSub $sub, array $prices): void
{
    Pricing::updateOrCreate(
        ['type' => ConfigOptionSub::PRICING_TYPE, 'rel_id' => $sub->id, 'currency_id' => optionCurrency()->id],
        $prices
    );
}

function productWithOptions(float $base = 20.0): array
{
    $product = Product::factory()->create([
        'group_id' => ProductGroup::factory()->create()->id,
        'server_type' => null,
        'tax' => false,
    ]);
    Pricing::updateOrCreate(
        ['type' => 'product', 'rel_id' => $product->id, 'currency_id' => optionCurrency()->id],
        ['monthly' => $base, 'annually' => $base * 10]
    );

    $group = ConfigOptionGroup::create(['name' => 'Resources']);
    ConfigOptionLink::create(['group_id' => $group->id, 'product_id' => $product->id]);

    $ram = ConfigOption::create(['group_id' => $group->id, 'option_name' => 'RAM', 'option_type' => 'dropdown', 'sort_order' => 1]);
    $ram2 = ConfigOptionSub::create(['config_id' => $ram->id, 'option_name' => '2 GB', 'sort_order' => 1]);
    $ram4 = ConfigOptionSub::create(['config_id' => $ram->id, 'option_name' => '4 GB', 'sort_order' => 2]);
    priceSub($ram2, ['monthly' => 0, 'annually' => 0]);
    priceSub($ram4, ['monthly' => 5, 'annually' => 50]);

    $ips = ConfigOption::create(['group_id' => $group->id, 'option_name' => 'Extra IP', 'option_type' => 'quantity', 'qty_minimum' => 0, 'qty_maximum' => 5, 'sort_order' => 2]);
    $ipUnit = ConfigOptionSub::create(['config_id' => $ips->id, 'option_name' => 'IP address', 'sort_order' => 1]);
    priceSub($ipUnit, ['monthly' => 2, 'annually' => 20]);

    $backup = ConfigOption::create(['group_id' => $group->id, 'option_name' => 'Managed backups', 'option_type' => 'checkbox', 'sort_order' => 3]);
    $backupSub = ConfigOptionSub::create(['config_id' => $backup->id, 'option_name' => 'Enabled', 'sort_order' => 1]);
    priceSub($backupSub, ['monthly' => 3, 'annually' => 30]);

    return compact('product', 'ram', 'ram2', 'ram4', 'ips', 'backup');
}

function optionCustomer(): array
{
    $client = Client::factory()->create();
    $user = User::factory()->create();
    $user->clients()->attach($client->id);

    return [$user, $client];
}

// ---------------------------------------------------------------------------
// Pricing
// ---------------------------------------------------------------------------

test('chosen options are added to the recurring price', function () {
    $fx = productWithOptions(20);
    $svc = app(ConfigOptionService::class);

    $normalised = $svc->normalise($fx['product'], [
        $fx['ram']->id => $fx['ram4']->id,   // +5
        $fx['ips']->id => 3,                  // 3 x 2 = 6
        $fx['backup']->id => '1',             // +3
    ], 'monthly');

    expect($svc->priceOf($normalised))->toBe(14.0);
});

test('option prices follow the billing cycle', function () {
    $fx = productWithOptions(20);
    $svc = app(ConfigOptionService::class);

    $normalised = $svc->normalise($fx['product'], [$fx['ram']->id => $fx['ram4']->id], 'annually');

    expect($svc->priceOf($normalised))->toBe(50.0);
});

test('a free option adds nothing', function () {
    $fx = productWithOptions(20);
    $svc = app(ConfigOptionService::class);

    $normalised = $svc->normalise($fx['product'], [$fx['ram']->id => $fx['ram2']->id], 'monthly');

    expect($svc->priceOf($normalised))->toBe(0.0);
});

// ---------------------------------------------------------------------------
// Validation — the price must never be decided by what the browser posts
// ---------------------------------------------------------------------------

test('an option belonging to another product is rejected', function () {
    $mine = productWithOptions(20);
    $theirs = productWithOptions(30);

    app(ConfigOptionService::class)->normalise($mine['product'], [
        $theirs['ram']->id => $theirs['ram4']->id,
    ], 'monthly');
})->throws(ValidationException::class);

test('a sub-option from a different option is rejected', function () {
    $fx = productWithOptions(20);
    $other = productWithOptions(20);

    app(ConfigOptionService::class)->normalise($fx['product'], [
        $fx['ram']->id => $other['ram4']->id,
    ], 'monthly');
})->throws(ValidationException::class);

test('a quantity beyond the configured maximum is rejected', function () {
    $fx = productWithOptions(20);

    app(ConfigOptionService::class)->normalise($fx['product'], [
        $fx['ram']->id => $fx['ram4']->id,
        $fx['ips']->id => 99,
    ], 'monthly');
})->throws(ValidationException::class);

test('a required choice must be made', function () {
    $fx = productWithOptions(20);

    app(ConfigOptionService::class)->normalise($fx['product'], [], 'monthly');
})->throws(ValidationException::class);

// ---------------------------------------------------------------------------
// Cart and ordering
// ---------------------------------------------------------------------------

test('the cart charges the base price plus the options', function () {
    $fx = productWithOptions(20);
    [, $client] = optionCustomer();
    $cart = app(CartService::class)->getOrCreateCart($client->id);

    app(CartService::class)->addProduct($cart, $fx['product'], 'monthly', null, [
        $fx['ram']->id => $fx['ram4']->id,
        $fx['ips']->id => 2,
    ]);

    $totals = app(CartService::class)->calculateTotal($cart->fresh());

    // 20 base + 5 RAM + (2 x 2) IPs
    expect((float) $totals['subtotal'])->toBe(29.0);
});

test('checkout records the chosen options against the service', function () {
    $fx = productWithOptions(20);
    [, $client] = optionCustomer();
    $svc = app(CartService::class);
    $cart = $svc->getOrCreateCart($client->id);

    $svc->addProduct($cart, $fx['product'], 'monthly', null, [
        $fx['ram']->id => $fx['ram4']->id,
        $fx['ips']->id => 2,
        $fx['backup']->id => '1',
    ]);
    $order = $svc->checkout($cart, $client->id, 'banktransfer');

    $service = Service::where('order_id', $order->id)->firstOrFail();
    $chosen = ServiceConfigOption::where('service_id', $service->id)->get();

    expect($chosen)->toHaveCount(3)
        // The money is folded into the service amount, so renewals bill it too.
        ->and((float) $service->amount)->toBe(32.0)
        ->and($chosen->firstWhere('config_id', $fx['ips']->id)->qty)->toBe(2);
});

test('the renewal invoice keeps charging for the options', function () {
    $fx = productWithOptions(20);
    [, $client] = optionCustomer();
    $svc = app(CartService::class);
    $cart = $svc->getOrCreateCart($client->id);
    $svc->addProduct($cart, $fx['product'], 'monthly', null, [$fx['ram']->id => $fx['ram4']->id]);
    $order = $svc->checkout($cart, $client->id, 'banktransfer');

    $service = Service::where('order_id', $order->id)->firstOrFail();
    $service->update(['status' => 'active', 'next_due_date' => now()->addDays(3)]);

    $this->artisan('pnlcs:generate-invoices')->assertSuccessful();

    $renewal = Invoice::where('client_id', $client->id)
        ->where('id', '!=', $order->invoice_id)->latest('id')->first();

    expect($renewal)->not->toBeNull()
        ->and((float) $renewal->total)->toBe(25.0);
});

// ---------------------------------------------------------------------------
// The screens
// ---------------------------------------------------------------------------

test('the order form offers the options with their prices', function () {
    $fx = productWithOptions(20);
    [$user] = optionCustomer();

    $this->actingAs($user)
        ->get(route('client.store.configure', $fx['product']))
        ->assertOk()
        ->assertSee('RAM')
        ->assertSee('4 GB')
        ->assertSee('Extra IP')
        ->assertSee('Managed backups');
});

test('an admin can link an option group to products', function () {
    $fx = productWithOptions(20);
    $other = Product::factory()->create(['group_id' => ProductGroup::factory()->create()->id]);
    $group = ConfigOptionGroup::where('name', 'Resources')->latest('id')->first();

    $this->actingAs(Admin::factory()->create(), 'admin')
        ->post(route('admin.config.config-option-groups.link', $group->id), [
            'product_ids' => [$other->id],
        ])->assertRedirect();

    expect(ConfigOptionLink::where('group_id', $group->id)->pluck('product_id')->all())->toBe([$other->id]);
});
