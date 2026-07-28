<?php

use App\Models\Admin;
use App\Models\ApiCredential;
use App\Models\Client;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Order;
use App\Models\Pricing;
use App\Models\Product;
use App\Models\ProductAddon;
use App\Models\ProductGroup;
use App\Models\Service;
use App\Models\ServiceAddon;
use App\Models\User;
use App\Services\AddonService;
use App\Services\PaymentService;
use Database\Factories\ApiCredentialFactory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * Product addons: extras sold beside a hosting package, each renewing on its
 * own date.
 *
 * The tables, the models and a listing table on the customer's service page
 * existed, and nothing else did: no price field, no way to buy one, no invoice
 * line, and a renewal generator that never looked at them. An addon was sold
 * once and then renewed itself for free.
 */
function addonShop(array $addonOverrides = [], float $monthly = 5.0): array
{
    $currency = Currency::where('is_default', true)->first()
        ?? Currency::create(['code' => 'USD', 'prefix' => '$', 'suffix' => '', 'rate' => 1, 'is_default' => true]);

    $product = Product::factory()->create([
        'group_id' => ProductGroup::factory()->create()->id,
        'name' => 'Starter Hosting',
        'slug' => 'starter-hosting',
        'auto_setup' => 'payment',
        'server_type' => null,
        'tax' => false,
        'hidden' => false,
        'retired' => false,
    ]);
    Pricing::updateOrCreate(
        ['type' => 'product', 'rel_id' => $product->id, 'currency_id' => $currency->id],
        ['monthly' => 20, 'annually' => 200]
    );

    $addon = ProductAddon::create(array_merge([
        'name' => 'Dedicated IP',
        'description' => 'Your own IPv4 address',
        'packages' => (string) $product->id,
        'hidden' => false,
        'retired' => false,
        'sort_order' => 1,
        'tax' => false,
    ], $addonOverrides));

    Pricing::updateOrCreate(
        ['type' => ProductAddon::PRICING_TYPE, 'rel_id' => $addon->id, 'currency_id' => $currency->id],
        ['monthly' => $monthly, 'annually' => $monthly * 10]
    );

    return compact('product', 'addon', 'currency');
}

// ---------------------------------------------------------------------------
// Pricing and applicability
// ---------------------------------------------------------------------------

test('an addon knows its price for a cycle', function () {
    $fx = addonShop(monthly: 7.5);

    expect($fx['addon']->priceFor('monthly'))->toBe(7.5)
        ->and($fx['addon']->priceFor('annually'))->toBe(75.0)
        ->and($fx['addon']->priceFor('nonsense'))->toBe(0.0);
});

test('an addon limited to one product is not offered with another', function () {
    $fx = addonShop();
    $other = Product::factory()->create(['group_id' => ProductGroup::factory()->create()->id]);

    $service = app(AddonService::class);

    expect($service->availableFor($fx['product'])->pluck('id'))->toContain($fx['addon']->id)
        ->and($service->availableFor($other)->pluck('id'))->not->toContain($fx['addon']->id);
});

test('an addon with no product list is offered with everything', function () {
    $fx = addonShop(['packages' => null]);
    $other = Product::factory()->create(['group_id' => ProductGroup::factory()->create()->id]);

    expect(app(AddonService::class)->availableFor($other)->pluck('id'))->toContain($fx['addon']->id);
});

test('hidden and retired addons are never offered', function () {
    $hidden = addonShop(['hidden' => true]);
    $retired = addonShop(['retired' => true]);

    expect(app(AddonService::class)->availableFor($hidden['product'])->pluck('id'))->not->toContain($hidden['addon']->id)
        ->and(app(AddonService::class)->availableFor($retired['product'])->pluck('id'))->not->toContain($retired['addon']->id);
});

test('an addon belonging to another product is refused, not silently sold', function () {
    $fx = addonShop();
    $other = Product::factory()->create(['group_id' => ProductGroup::factory()->create()->id]);

    app(AddonService::class)->normalise($other, [$fx['addon']->id], 'monthly');
})->throws(ValidationException::class);

// ---------------------------------------------------------------------------
// Ordering with the service
// ---------------------------------------------------------------------------

test('an addon chosen at checkout is charged and recorded', function () {
    Mail::fake();
    $fx = addonShop(monthly: 5.0);
    $user = User::factory()->create();
    $client = Client::factory()->create();
    $user->clients()->attach($client->id);

    $this->actingAs($user)->get(route('client.store.configure', $fx['product']))
        ->assertOk()->assertSee('Dedicated IP');

    $this->actingAs($user)->post(route('client.cart.add'), [
        'product_id' => $fx['product']->id,
        'billing_cycle' => 'monthly',
        'domain' => 'addon-test.com',
        'addons' => [$fx['addon']->id],
    ])->assertRedirect()->assertSessionHasNoErrors();

    // 20 for the package, 5 for the addon.
    $this->actingAs($user)->get(route('client.cart.index'))->assertOk()->assertSee('25.00');

    $this->actingAs($user)->post(route('client.cart.process'), [
        'payment_method' => 'banktransfer',
        'terms' => '1',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $order = Order::where('client_id', $client->id)->firstOrFail();
    $invoice = Invoice::findOrFail($order->invoice_id);
    $service = Service::where('order_id', $order->id)->firstOrFail();
    $serviceAddon = ServiceAddon::where('service_id', $service->id)->firstOrFail();

    expect((float) $invoice->total)->toBe(25.0)
        // The addon is its own line, not folded into the hosting price.
        ->and(InvoiceItem::where('invoice_id', $invoice->id)->where('type', 'Addon')->count())->toBe(1)
        ->and((float) $service->amount)->toBe(20.0)
        ->and((float) $serviceAddon->amount)->toBe(5.0)
        ->and($serviceAddon->status)->toBe('pending');
});

test('paying the order starts the addon billing', function () {
    Mail::fake();
    Http::fake(['*' => Http::response(['success' => true], 200)]);
    $fx = addonShop();
    $user = User::factory()->create();
    $client = Client::factory()->create();
    $user->clients()->attach($client->id);
    $admin = Admin::factory()->create();

    $this->actingAs($user)->post(route('client.cart.add'), [
        'product_id' => $fx['product']->id,
        'billing_cycle' => 'monthly',
        'domain' => 'addon-pay.com',
        'addons' => [$fx['addon']->id],
    ])->assertSessionHasNoErrors();
    $this->actingAs($user)->post(route('client.cart.process'), [
        'payment_method' => 'banktransfer', 'terms' => '1',
    ])->assertSessionHasNoErrors();

    $order = Order::where('client_id', $client->id)->firstOrFail();
    $serviceAddon = ServiceAddon::where('client_id', $client->id)->firstOrFail();

    expect($serviceAddon->status)->toBe('pending');

    $this->actingAs($admin, 'admin')
        ->post(route('admin.invoices.mark-paid', Invoice::findOrFail($order->invoice_id)), ['gateway' => 'banktransfer'])
        ->assertRedirect();

    $serviceAddon->refresh();
    expect($serviceAddon->status)->toBe('active')
        ->and($serviceAddon->next_due_date)->not->toBeNull();
});

// ---------------------------------------------------------------------------
// Renewal
// ---------------------------------------------------------------------------

test('a due addon is billed on the renewal invoice', function () {
    $fx = addonShop(monthly: 5.0);
    $client = Client::factory()->create();
    $service = Service::factory()->create([
        'client_id' => $client->id,
        'product_id' => $fx['product']->id,
        'status' => 'active',
        'amount' => 20,
        'billing_cycle' => 'Monthly',
        'next_due_date' => now()->addDays(3),
        'domain' => 'renew-addon.com',
    ]);
    ServiceAddon::create([
        'service_id' => $service->id,
        'addon_id' => $fx['addon']->id,
        'client_id' => $client->id,
        'qty' => 1,
        'amount' => 5,
        'billing_cycle' => 'Monthly',
        'next_due_date' => now()->addDays(3),
        'status' => 'active',
    ]);

    $this->artisan('pnlcs:generate-invoices')->assertSuccessful();

    $invoice = Invoice::where('client_id', $client->id)->latest('id')->firstOrFail();

    expect((float) $invoice->total)->toBe(25.0)
        ->and(InvoiceItem::where('invoice_id', $invoice->id)->where('type', 'Addon')->count())->toBe(1);
});

test('an addon due on its own is billed even when the service is not', function () {
    $fx = addonShop(monthly: 5.0);
    $client = Client::factory()->create();
    $service = Service::factory()->create([
        'client_id' => $client->id,
        'product_id' => $fx['product']->id,
        'status' => 'active',
        'amount' => 20,
        'billing_cycle' => 'Monthly',
        // Nowhere near due.
        'next_due_date' => now()->addMonths(6),
        'domain' => 'late-addon.com',
    ]);
    ServiceAddon::create([
        'service_id' => $service->id,
        'addon_id' => $fx['addon']->id,
        'client_id' => $client->id,
        'qty' => 1,
        'amount' => 5,
        'billing_cycle' => 'Monthly',
        'next_due_date' => now()->addDays(2),
        'status' => 'active',
    ]);

    $this->artisan('pnlcs:generate-invoices')->assertSuccessful();

    $invoice = Invoice::where('client_id', $client->id)->latest('id')->first();

    expect($invoice)->not->toBeNull()
        ->and((float) $invoice->total)->toBe(5.0);
});

test('a cancelled addon is never billed again', function () {
    $fx = addonShop();
    $client = Client::factory()->create();
    $service = Service::factory()->create([
        'client_id' => $client->id,
        'product_id' => $fx['product']->id,
        'status' => 'active',
        'amount' => 20,
        'billing_cycle' => 'Monthly',
        'next_due_date' => now()->addMonths(6),
        'domain' => 'cancelled-addon.com',
    ]);
    $serviceAddon = ServiceAddon::create([
        'service_id' => $service->id,
        'addon_id' => $fx['addon']->id,
        'client_id' => $client->id,
        'qty' => 1,
        'amount' => 5,
        'billing_cycle' => 'Monthly',
        'next_due_date' => now()->addDays(2),
        'status' => 'active',
    ]);

    app(AddonService::class)->cancel($serviceAddon);

    $this->artisan('pnlcs:generate-invoices')->assertSuccessful();

    expect(Invoice::where('client_id', $client->id)->count())->toBe(0)
        ->and($service->fresh()->status)->toBe('active');
});

test('paying a renewal moves the addon date on by one cycle', function () {
    $fx = addonShop();
    $client = Client::factory()->create();
    $service = Service::factory()->create([
        'client_id' => $client->id,
        'product_id' => $fx['product']->id,
        'status' => 'active',
        'billing_cycle' => 'Monthly',
        'next_due_date' => now()->addMonths(6),
        'domain' => 'advance-addon.com',
    ]);
    $serviceAddon = ServiceAddon::create([
        'service_id' => $service->id,
        'addon_id' => $fx['addon']->id,
        'client_id' => $client->id,
        'qty' => 1,
        'amount' => 5,
        'billing_cycle' => 'Monthly',
        'next_due_date' => now()->addDays(2),
        'status' => 'active',
    ]);

    $due = $serviceAddon->next_due_date->copy();

    $this->artisan('pnlcs:generate-invoices')->assertSuccessful();
    $invoice = Invoice::where('client_id', $client->id)->latest('id')->firstOrFail();

    app(PaymentService::class)->applyPayment($invoice, 'banktransfer', 'TXN-ADDON', (float) $invoice->total);

    expect($serviceAddon->fresh()->next_due_date->toDateString())
        ->toBe($due->addMonth()->toDateString());
});

// ---------------------------------------------------------------------------
// Buying one later, and stopping it
// ---------------------------------------------------------------------------

test('a customer can order an addon for a running service', function () {
    Mail::fake();
    $fx = addonShop(monthly: 9.0);
    $user = User::factory()->create();
    $client = Client::factory()->create();
    $user->clients()->attach($client->id);
    $service = Service::factory()->create([
        'client_id' => $client->id,
        'product_id' => $fx['product']->id,
        'status' => 'active',
        'billing_cycle' => 'Monthly',
        'next_due_date' => now()->addMonth(),
        'domain' => 'later-addon.com',
    ]);

    $this->actingAs($user)->get(route('client.services.show', $service))
        ->assertOk()->assertSee('Dedicated IP');

    $this->actingAs($user)->post(route('client.services.addons.store', $service), [
        'addon_id' => $fx['addon']->id,
    ])->assertRedirect()->assertSessionHasNoErrors();

    $serviceAddon = ServiceAddon::where('service_id', $service->id)->firstOrFail();
    $invoice = Invoice::where('client_id', $client->id)->latest('id')->firstOrFail();

    expect($serviceAddon->status)->toBe('pending')
        // Nothing is billed until it is paid for.
        ->and($serviceAddon->next_due_date)->toBeNull()
        ->and((float) $invoice->total)->toBe(9.0);

    app(PaymentService::class)->applyPayment($invoice, 'banktransfer', 'TXN-LATER', 9.0);

    $serviceAddon->refresh();
    expect($serviceAddon->status)->toBe('active')
        ->and($serviceAddon->next_due_date->toDateString())->toBe(now()->addMonth()->toDateString());
});

test('a customer cannot order an addon on somebody else service', function () {
    $fx = addonShop();
    $intruder = User::factory()->create();
    $intruder->clients()->attach(Client::factory()->create()->id);
    $victim = Client::factory()->create();
    $service = Service::factory()->create([
        'client_id' => $victim->id,
        'product_id' => $fx['product']->id,
        'status' => 'active',
        'domain' => 'victim.com',
    ]);

    $this->actingAs($intruder)
        ->post(route('client.services.addons.store', $service), ['addon_id' => $fx['addon']->id])
        ->assertForbidden();

    expect(ServiceAddon::count())->toBe(0);
});

test('cancelling an addon leaves the service alone', function () {
    $fx = addonShop();
    $user = User::factory()->create();
    $client = Client::factory()->create();
    $user->clients()->attach($client->id);
    $service = Service::factory()->create([
        'client_id' => $client->id,
        'product_id' => $fx['product']->id,
        'status' => 'active',
        'domain' => 'keep-running.com',
    ]);
    $serviceAddon = ServiceAddon::create([
        'service_id' => $service->id,
        'addon_id' => $fx['addon']->id,
        'client_id' => $client->id,
        'qty' => 1,
        'amount' => 5,
        'billing_cycle' => 'Monthly',
        'next_due_date' => now()->addMonth(),
        'status' => 'active',
    ]);

    $this->actingAs($user)
        ->post(route('client.services.addons.cancel', [$service, $serviceAddon]))
        ->assertRedirect();

    expect($serviceAddon->fresh()->status)->toBe('cancelled')
        ->and($service->fresh()->status)->toBe('active');
});

// ---------------------------------------------------------------------------
// Admin side
// ---------------------------------------------------------------------------

test('the admin screen saves an addon price', function () {
    $admin = Admin::factory()->create();
    Currency::where('is_default', true)->first()
        ?? Currency::create(['code' => 'USD', 'prefix' => '$', 'suffix' => '', 'rate' => 1, 'is_default' => true]);

    $this->actingAs($admin, 'admin')->post(route('admin.config.addons.store'), [
        'name' => 'Backup Space',
        'description' => '50 GB of offsite backup',
        'sort_order' => 2,
        'pricing' => ['monthly' => 3.5, 'annually' => 35],
    ])->assertRedirect();

    $addon = ProductAddon::where('name', 'Backup Space')->firstOrFail();

    expect($addon->priceFor('monthly'))->toBe(3.5)
        ->and($addon->priceFor('annually'))->toBe(35.0);
});

test('editing an addon keeps its product list', function () {
    $fx = addonShop();
    $admin = Admin::factory()->create();

    $this->actingAs($admin, 'admin')->put(route('admin.config.addons.update', $fx['addon']->id), [
        'name' => 'Dedicated IP v6',
        'sort_order' => 1,
        'packages' => [$fx['product']->id],
        'pricing' => ['monthly' => 6],
    ])->assertRedirect();

    $addon = $fx['addon']->fresh();

    expect($addon->name)->toBe('Dedicated IP v6')
        ->and($addon->packageIds())->toBe([$fx['product']->id])
        ->and($addon->priceFor('monthly'))->toBe(6.0);
});

test('an operator can put an addon on a service and it gets invoiced', function () {
    Mail::fake();
    $fx = addonShop(monthly: 4.0);
    $admin = Admin::factory()->create();
    $client = Client::factory()->create();
    $service = Service::factory()->create([
        'client_id' => $client->id,
        'product_id' => $fx['product']->id,
        'status' => 'active',
        'billing_cycle' => 'Monthly',
        'domain' => 'admin-added.com',
    ]);

    $this->actingAs($admin, 'admin')
        ->post(route('admin.services.addons.store', $service), ['addon_id' => $fx['addon']->id])
        ->assertRedirect();

    $serviceAddon = ServiceAddon::where('service_id', $service->id)->firstOrFail();
    $invoice = Invoice::where('client_id', $client->id)->latest('id')->firstOrFail();

    expect((float) $serviceAddon->amount)->toBe(4.0)
        ->and((float) $invoice->total)->toBe(4.0);
});

test('the service page names the addon instead of showing an id', function () {
    $fx = addonShop();
    $user = User::factory()->create();
    $client = Client::factory()->create();
    $user->clients()->attach($client->id);
    $service = Service::factory()->create([
        'client_id' => $client->id,
        'product_id' => $fx['product']->id,
        'status' => 'active',
        'domain' => 'named-addon.com',
    ]);
    ServiceAddon::create([
        'service_id' => $service->id,
        'addon_id' => $fx['addon']->id,
        'client_id' => $client->id,
        'qty' => 1,
        'amount' => 5,
        'billing_cycle' => 'Monthly',
        'next_due_date' => now()->addMonth(),
        'status' => 'active',
    ]);

    $this->actingAs($user)->get(route('client.services.show', $service))
        ->assertOk()
        ->assertSee('Dedicated IP')
        ->assertDontSee('Addon #');
});

// ---------------------------------------------------------------------------
// The WHMCS-compatible API
// ---------------------------------------------------------------------------

test('the addons API endpoint returns addons instead of failing', function () {
    $cred = ApiCredential::factory()->create();
    $headers = [
        'X-API-Key' => $cred->identifier,
        'X-API-Secret' => ApiCredentialFactory::PLAINTEXT_SECRET,
    ];

    $fx = addonShop();
    $client = Client::factory()->create();
    $service = Service::factory()->create([
        'client_id' => $client->id,
        'product_id' => $fx['product']->id,
        'status' => 'active',
        'domain' => 'api-addon.com',
    ]);
    ServiceAddon::create([
        'service_id' => $service->id,
        'addon_id' => $fx['addon']->id,
        'client_id' => $client->id,
        'qty' => 1,
        'amount' => 5,
        'billing_cycle' => 'Monthly',
        'next_due_date' => now()->addMonth(),
        'status' => 'active',
    ]);

    $this->getJson('/api/v1/getclientsaddons?serviceid='.$service->id, $headers)
        ->assertStatus(200)
        ->assertJson(['result' => 'success']);
});

// ---------------------------------------------------------------------------
// The addon must not outlive the service
// ---------------------------------------------------------------------------

function addonOnService(array $fx, string $serviceStatus): ServiceAddon
{
    $client = Client::factory()->create();
    $service = Service::factory()->create([
        'client_id' => $client->id,
        'product_id' => $fx['product']->id,
        'status' => $serviceStatus,
        'amount' => 20,
        'billing_cycle' => 'Monthly',
        'next_due_date' => now()->addMonths(6),
        'domain' => $serviceStatus.'-addon.com',
    ]);

    return ServiceAddon::create([
        'service_id' => $service->id,
        'addon_id' => $fx['addon']->id,
        'client_id' => $client->id,
        'qty' => 1,
        'amount' => 5,
        'billing_cycle' => 'Monthly',
        'next_due_date' => now()->addDays(2),
        'status' => 'active',
    ]);
}

test('an addon on a terminated service is not billed', function () {
    $fx = addonShop();
    $terminated = addonOnService($fx, 'terminated');
    $cancelled = addonOnService($fx, 'cancelled');

    $this->artisan('pnlcs:generate-invoices')->assertSuccessful();

    expect(Invoice::where('client_id', $terminated->client_id)->count())->toBe(0)
        ->and(Invoice::where('client_id', $cancelled->client_id)->count())->toBe(0);
});

test('an addon on a suspended service is still billed', function () {
    // Suspension is usually non-payment; the customer still owes for what they
    // bought, and paying is what brings the account back.
    $fx = addonShop();
    $suspended = addonOnService($fx, 'suspended');

    $this->artisan('pnlcs:generate-invoices')->assertSuccessful();

    expect(Invoice::where('client_id', $suspended->client_id)->count())->toBe(1);
});

test('terminating a service stops its addons', function () {
    $fx = addonShop();
    $addon = addonOnService($fx, 'active');
    $service = Service::findOrFail($addon->service_id);

    $service->update(['status' => 'terminated']);

    expect(strtolower($addon->fresh()->status))->toBe('cancelled');
});

test('an addon cannot be bought for a service that has ended', function () {
    $fx = addonShop();
    $user = User::factory()->create();
    $client = Client::factory()->create();
    $user->clients()->attach($client->id);
    $service = Service::factory()->create([
        'client_id' => $client->id,
        'product_id' => $fx['product']->id,
        'status' => 'terminated',
        'domain' => 'gone.com',
    ]);

    $this->actingAs($user)
        ->post(route('client.services.addons.store', $service), ['addon_id' => $fx['addon']->id])
        ->assertSessionHasErrors();

    expect(ServiceAddon::count())->toBe(0)
        ->and(Invoice::count())->toBe(0);
});
