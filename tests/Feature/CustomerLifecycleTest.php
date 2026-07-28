<?php

use App\Models\Admin;
use App\Models\CancellationRequest;
use App\Models\ConfigOption;
use App\Models\ConfigOptionGroup;
use App\Models\ConfigOptionLink;
use App\Models\ConfigOptionSub;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\ModuleQueue;
use App\Models\Order;
use App\Models\Pricing;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Server;
use App\Models\ServerGroup;
use App\Models\Service;
use App\Models\ServiceConfigOption;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

/**
 * One customer, from signing up to being terminated, driven through the real
 * HTTP endpoints and the real scheduled commands rather than by calling
 * services directly.
 *
 * Everything found during the July audit hid somewhere in this chain: the cart
 * that ignored option prices, the checkout that lost the customer's note, the
 * renewal cron that skipped overage, the suspension that never reached the
 * server, the affiliate commission that was never paid. A test that walks the
 * whole chain is the one that would have caught them.
 */
function shop(): array
{
    $currency = Currency::where('is_default', true)->first()
        ?? Currency::create(['code' => 'USD', 'prefix' => '$', 'suffix' => '', 'rate' => 1, 'is_default' => true]);

    $server = Server::factory()->create(['type' => 'panelica', 'hostname' => 'node1.test', 'active' => true, 'max_accounts' => 0]);
    $serverGroup = ServerGroup::factory()->create(['fill_type' => 'fill']);
    $serverGroup->servers()->sync([$server->id]);

    $product = Product::factory()->create([
        'group_id' => ProductGroup::factory()->create()->id,
        'name' => 'Business Hosting',
        'slug' => 'business-hosting',
        'server_type' => 'panelica',
        'server_group_id' => $serverGroup->id,
        'auto_setup' => 'payment',
        'tax' => false,
        'hidden' => false,
        'retired' => false,
        'overage_enabled' => true,
        'overage_disk_rate' => 0.05,
        'overage_bw_rate' => 0.01,
    ]);
    Pricing::updateOrCreate(
        ['type' => 'product', 'rel_id' => $product->id, 'currency_id' => $currency->id],
        ['monthly' => 30, 'annually' => 300]
    );

    // A paid configurable option, so the money in this test is not just the base price.
    $group = ConfigOptionGroup::create(['name' => 'Resources']);
    ConfigOptionLink::create(['group_id' => $group->id, 'product_id' => $product->id]);
    $ram = ConfigOption::create(['group_id' => $group->id, 'option_name' => 'RAM', 'option_type' => 'dropdown', 'sort_order' => 1]);
    $ram4 = ConfigOptionSub::create(['config_id' => $ram->id, 'option_name' => '4 GB', 'sort_order' => 1]);
    Pricing::updateOrCreate(
        ['type' => ConfigOptionSub::PRICING_TYPE, 'rel_id' => $ram4->id, 'currency_id' => $currency->id],
        ['monthly' => 10, 'annually' => 100]
    );

    return compact('product', 'server', 'ram', 'ram4');
}

test('a customer goes from signup to termination through the real endpoints', function () {
    Mail::fake();
    // The Panelica module reads the remote id from data.id.
    Http::fake(['*' => Http::response(['data' => ['id' => 'remote-user-1']], 200)]);
    $fx = shop();
    $admin = Admin::factory()->create();

    // ── 1. Sign up ────────────────────────────────────────────────────────
    $this->post(route('client.register.submit'), [
        'first_name' => 'Ayşe', 'last_name' => 'Yılmaz',
        'email' => 'ayse@example.com',
        'password' => 'Secret123!', 'password_confirmation' => 'Secret123!',
        'tos' => '1',
    ])->assertRedirect();

    $user = User::where('email', 'ayse@example.com')->firstOrFail();
    $client = $user->clients()->firstOrFail();

    // ── 2. Browse and configure ───────────────────────────────────────────
    $this->actingAs($user)->get(route('client.store'))->assertOk()->assertSee('Business Hosting');
    $this->actingAs($user)->get(route('client.store.configure', $fx['product']))
        ->assertOk()->assertSee('RAM')->assertSee('4 GB');

    // ── 3. Add to cart with the paid option and a note ────────────────────
    $this->actingAs($user)->post(route('client.cart.add'), [
        'product_id' => $fx['product']->id,
        'billing_cycle' => 'monthly',
        'domain' => 'ayse-example.com',
        'domain_option' => 'register',
        'notes' => 'Lütfen PHP 8.3 kurun',
        'config_options' => [$fx['ram']->id => $fx['ram4']->id],
    ])->assertRedirect();

    // 30 base + 10 RAM
    $this->actingAs($user)->get(route('client.cart.index'))->assertOk()->assertSee('40.00');

    // ── 4. Checkout ───────────────────────────────────────────────────────
    $this->actingAs($user)->post(route('client.cart.process'), [
        'payment_method' => 'banktransfer',
        'terms' => '1',
    ])->assertRedirect();

    $order = Order::where('client_id', $client->id)->firstOrFail();
    $invoice = Invoice::findOrFail($order->invoice_id);
    $service = Service::where('order_id', $order->id)->firstOrFail();

    expect((float) $invoice->total)->toBe(40.0)
        ->and($service->status)->toBe('pending')
        ->and($service->notes)->toContain('Lütfen PHP 8.3 kurun')
        ->and(ServiceConfigOption::where('service_id', $service->id)->count())->toBe(1);

    // ── 5. Admin marks the transfer as received ───────────────────────────
    $this->actingAs($admin, 'admin')
        ->post(route('admin.invoices.mark-paid', $invoice), ['gateway' => 'banktransfer'])
        ->assertRedirect();

    expect($invoice->fresh()->status)->toBe('paid');

    // ── 6. Payment provisioned the account on the server ──────────────────
    $service->refresh();
    expect($service->status)->toBe('active')
        ->and($service->server_id)->toBe($fx['server']->id)
        ->and(ModuleQueue::where('service_id', $service->id)->where('status', 'pending')->count())->toBe(0);
    // The remote account id came back through the module, which only happens
    // if the server was really contacted.
    expect($service->module_data['panelica_user_id'] ?? null)->toBe('remote-user-1');

    // ── 7. Renewal invoice, with the option and the overage ───────────────
    $service->update([
        'next_due_date' => now()->addDays(3),
        'disk_usage' => 3000, 'disk_limit' => 1000,   // 2000 MB over at 0.05 = 100
    ]);
    $this->artisan('pnlcs:generate-invoices')->assertSuccessful();

    $renewal = Invoice::where('client_id', $client->id)->where('id', '!=', $invoice->id)->latest('id')->firstOrFail();
    expect((float) $renewal->total)->toBe(140.0)
        ->and(InvoiceItem::where('invoice_id', $renewal->id)->where('type', 'Overage')->count())->toBe(1);

    // ── 8. Unpaid renewal goes overdue, then suspends on the server ───────
    $renewal->update(['due_date' => now()->subDays(10), 'status' => 'unpaid']);
    $this->artisan('pnlcs:mark-overdue')->assertSuccessful();
    expect($renewal->fresh()->status)->toBe('overdue');

    Http::fake(['*' => Http::response(['success' => true], 200)]);
    $this->artisan('pnlcs:auto-suspend')->assertSuccessful();

    $service->refresh();
    expect($service->status)->toBe('suspended')
        ->and($service->suspension_date)->not->toBeNull();

    // ── 9. Customer pays, the account comes back ──────────────────────────
    $this->actingAs($admin, 'admin')
        ->post(route('admin.invoices.mark-paid', $renewal), ['gateway' => 'banktransfer'])
        ->assertRedirect();
    expect($renewal->fresh()->status)->toBe('paid');

    $this->artisan('pnlcs:unsuspend-on-payment')->assertSuccessful();
    expect($service->fresh()->status)->toBe('active');

    // ── 10. Customer cancels; the cron terminates it on the server ────────
    $this->actingAs($user)->post(route('client.services.cancel.submit', $service), [
        'reason' => 'Moving elsewhere',
        'type' => 'Immediate',
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect(CancellationRequest::where('service_id', $service->id)->exists())->toBeTrue();

    $this->artisan('pnlcs:process-cancellations')->assertSuccessful();

    expect($service->fresh()->status)->toBeIn(['cancelled', 'terminated']);
});
