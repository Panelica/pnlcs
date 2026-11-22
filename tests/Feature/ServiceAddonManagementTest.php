<?php

use App\Models\Admin;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Order;
use App\Models\ProductAddon;
use App\Models\Service;
use App\Models\ServiceAddon;
use App\Services\AddonService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;

function pnlcs_test_service_addon(array $overrides = []): array
{
    $admin = Admin::factory()->create();
    $client = Client::factory()->create();
    $service = Service::factory()->create([
        'client_id' => $client->id,
        'status' => 'active',
        'auto_renew' => true,
        'next_due_date' => now()->addMonth(),
    ]);

    $productAddon = ProductAddon::create([
        'name' => 'Extra backup',
        'packages' => null,
        'hidden' => false,
        'retired' => false,
        'sort_order' => 0,
        'tax' => true,
    ]);

    $addon = ServiceAddon::create(array_merge([
        'service_id' => $service->id,
        'client_id' => $client->id,
        'addon_id' => $productAddon->id,
        'qty' => 1,
        'amount' => 50,
        'billing_cycle' => 'Annually',
        'next_due_date' => now()->addMonth(),
        'status' => 'active',
        'notes' => null,
    ], $overrides));

    return compact('admin', 'client', 'service', 'productAddon', 'addon');
}

function pnlcs_test_addon_order_setup(): array
{
    $admin = Admin::factory()->create();
    $client = Client::factory()->create();
    $service = Service::factory()->create([
        'client_id' => $client->id,
        'status' => 'active',
        'billing_cycle' => 'Monthly',
        'domain' => 'addon-order.test',
    ]);
    $productAddon = ProductAddon::create([
        'name' => 'Extra backup',
        'packages' => null,
        'hidden' => false,
        'retired' => false,
        'sort_order' => 0,
        'tax' => true,
    ]);

    return compact('admin', 'client', 'service', 'productAddon');
}

it('lets an admin edit the price and the next invoice line uses it', function () {
    $fx = pnlcs_test_service_addon();

    $this->actingAs($fx['admin'], 'admin')
        ->put(route('admin.services.addons.update', [$fx['service'], $fx['addon']]), [
            'amount' => 75.50,
            'billing_cycle' => 'Annually',
            'next_due_date' => now()->addYear()->toDateString(),
            'notes' => 'new price',
        ])
        ->assertRedirect(route('admin.services.show', $fx['service']));

    $addon = $fx['addon']->fresh();

    expect((float) $addon->amount)->toBe(75.5)
        ->and($addon->notes)->toBe('new price');

    $line = app(AddonService::class)->lineItem($addon);
    expect((float) $line['amount'])->toBe(75.5);
});

it('updates a pending addon and its open invoice line', function () {
    $fx = pnlcs_test_service_addon(['status' => 'pending']);

    $invoice = Invoice::factory()->create(['client_id' => $fx['client']->id]);
    $item = InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'client_id' => $fx['client']->id,
        'type' => 'Addon',
        'rel_id' => $fx['addon']->id,
        'description' => 'Extra backup',
        'amount' => 50,
        'taxed' => true,
    ]);

    $this->actingAs($fx['admin'], 'admin')
        ->put(route('admin.services.addons.update', [$fx['service'], $fx['addon']]), [
            'amount' => 75.50,
            'billing_cycle' => 'Annually',
            'next_due_date' => now()->addYear()->toDateString(),
            'notes' => 'before payment',
        ])
        ->assertRedirect(route('admin.services.show', $fx['service']));

    expect((float) $fx['addon']->fresh()->amount)->toBe(75.5)
        ->and((float) $item->fresh()->amount)->toBe(75.5);
});

it('suspends and resumes an addon without touching its service', function () {
    $fx = pnlcs_test_service_addon();
    $due = fn () => app(AddonService::class)->dueQuery(now()->addYear())->pluck('id')->all();

    expect($due())->toContain($fx['addon']->id);

    $this->actingAs($fx['admin'], 'admin')
        ->post(route('admin.services.addons.suspend', [$fx['service'], $fx['addon']]))
        ->assertRedirect();

    expect(strtolower((string) $fx['addon']->fresh()->status))->toBe('suspended')
        ->and($due())->not->toContain($fx['addon']->id);

    $this->actingAs($fx['admin'], 'admin')
        ->post(route('admin.services.addons.resume', [$fx['service'], $fx['addon']]))
        ->assertRedirect();

    expect(strtolower((string) $fx['addon']->fresh()->status))->toBe('active')
        ->and($due())->toContain($fx['addon']->id);
});

it('refuses to delete an addon whose invoice is paid', function () {
    $fx = pnlcs_test_service_addon();

    $invoice = Invoice::factory()->paid()->create(['client_id' => $fx['client']->id]);
    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'client_id' => $fx['client']->id,
        'type' => 'Addon',
        'rel_id' => $fx['addon']->id,
        'description' => 'Extra backup',
        'amount' => 50,
        'taxed' => true,
    ]);

    $this->actingAs($fx['admin'], 'admin')
        ->delete(route('admin.services.addons.destroy', [$fx['service'], $fx['addon']]))
        ->assertSessionHas('error');

    expect(ServiceAddon::find($fx['addon']->id))->not->toBeNull();
});

it('deletes an addon that never reached an invoice', function () {
    $fx = pnlcs_test_service_addon();

    $this->actingAs($fx['admin'], 'admin')
        ->delete(route('admin.services.addons.destroy', [$fx['service'], $fx['addon']]))
        ->assertRedirect();

    expect(ServiceAddon::find($fx['addon']->id))->toBeNull();
});

it('keeps an addon tied to its own service', function () {
    $fx = pnlcs_test_service_addon();
    $other = Service::factory()->create();

    $this->actingAs($fx['admin'], 'admin')
        ->get(route('admin.services.addons.edit', [$other, $fx['addon']]))
        ->assertNotFound();
});

it('deletes a cancelled addon and cancels its empty unpaid invoice', function () {
    $fx = pnlcs_test_service_addon(['status' => 'cancelled']);

    $invoice = Invoice::factory()->create(['client_id' => $fx['client']->id]);
    $item = InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'client_id' => $fx['client']->id,
        'type' => 'Addon',
        'rel_id' => $fx['addon']->id,
        'description' => 'Extra backup',
        'amount' => 50,
        'taxed' => true,
    ]);

    $this->actingAs($fx['admin'], 'admin')
        ->delete(route('admin.services.addons.destroy', [$fx['service'], $fx['addon']]))
        ->assertRedirect();

    expect(ServiceAddon::find($fx['addon']->id))->toBeNull()
        ->and(InvoiceItem::find($item->id))->toBeNull()
        ->and($invoice->fresh()->status)->toBe('cancelled');
});

it('runs a new addon through its own order and invoice', function () {
    Mail::fake();
    $fx = pnlcs_test_addon_order_setup();
    $issue = now()->addDays(10)->toDateString();

    $this->actingAs($fx['admin'], 'admin')
        ->post(route('admin.services.addons.store', $fx['service']), [
            'addon_id' => $fx['productAddon']->id,
            'billing_cycle' => 'Annually',
            'issue_date' => $issue,
        ])
        ->assertRedirect();

    $addon = ServiceAddon::where('service_id', $fx['service']->id)->firstOrFail();
    $order = Order::findOrFail($addon->order_id);
    $invoice = Invoice::findOrFail($order->invoice_id);

    // Like a product: an order and an invoice exist straight away.
    expect(strtolower((string) $addon->status))->toBe('pending')
        ->and(strtolower((string) $order->status))->toBe('pending')
        ->and($invoice->date->toDateString())->toBe($issue);

    $this->actingAs($fx['admin'], 'admin')
        ->put(route('admin.services.addons.update', [$fx['service'], $addon]), [
            'amount' => 75,
            'billing_cycle' => 'Annually',
            'next_due_date' => $issue,
            'notes' => 'planned',
        ])
        ->assertRedirect(route('admin.services.show', $fx['service']));

    $addon->refresh();
    $order->refresh();
    $invoice->refresh();

    expect((float) $addon->amount)->toBe(75.0)
        ->and((float) $order->amount)->toBe(75.0)
        ->and((float) $invoice->items()->where('type', 'Addon')->value('amount'))->toBe(75.0)
        ->and((float) $invoice->total)->toBe(75.0);

    // The customer pays; the order is accepted and the addon starts.
    $this->actingAs($fx['admin'], 'admin')
        ->post(route('admin.invoices.mark-paid', $invoice), ['gateway' => 'banktransfer'])
        ->assertRedirect();

    $addon->refresh();
    $order->refresh();

    expect(strtolower((string) $order->status))->toBe('active')
        ->and(strtolower((string) $addon->status))->toBe('active')
        ->and($addon->next_due_date->toDateString())->toBe(Carbon::parse($issue)->addYearNoOverflow()->toDateString());
});


it('deletes a draft addon together with its order', function () {
    $fx = pnlcs_test_addon_order_setup();

    $addon = app(AddonService::class)->draftForService($fx['service'], $fx['productAddon']);
    $order = Order::findOrFail($addon->order_id);

    $this->actingAs($fx['admin'], 'admin')
        ->delete(route('admin.services.addons.destroy', [$fx['service'], $addon]))
        ->assertRedirect();

    expect(ServiceAddon::find($addon->id))->toBeNull()
        ->and(Order::find($order->id))->toBeNull();
});


it('refuses to accept an addon twice', function () {
    Mail::fake();
    $fx = pnlcs_test_addon_order_setup();

    $addon = app(AddonService::class)->draftForService($fx['service'], $fx['productAddon']);

    $this->actingAs($fx['admin'], 'admin')
        ->post(route('admin.services.addons.accept', [$fx['service'], $addon]))
        ->assertRedirect();

    $this->actingAs($fx['admin'], 'admin')
        ->post(route('admin.services.addons.accept', [$fx['service'], $addon]))
        ->assertSessionHas('error');

    expect(Invoice::where('client_id', $fx['client']->id)->count())->toBe(1);
});


it('shows the addon on the service and order screens', function () {
    Mail::fake();
    $fx = pnlcs_test_addon_order_setup();

    $this->actingAs($fx['admin'], 'admin')
        ->post(route('admin.services.addons.store', $fx['service']), ['addon_id' => $fx['productAddon']->id])
        ->assertRedirect();

    $addon = ServiceAddon::where('service_id', $fx['service']->id)->firstOrFail();
    $order = Order::findOrFail($addon->order_id);

    $this->actingAs($fx['admin'], 'admin')
        ->get(route('admin.services.show', $fx['service']))
        ->assertOk()
        ->assertSee($addon->label());

    $this->actingAs($fx['admin'], 'admin')
        ->get(route('admin.orders.show', $order))
        ->assertOk()
        ->assertSee($addon->label());
});


it('issues the addon invoice when the order itself is accepted', function () {
    Mail::fake();
    $fx = pnlcs_test_addon_order_setup();
    $issue = now()->addDays(10)->toDateString();

    $this->actingAs($fx['admin'], 'admin')
        ->post(route('admin.services.addons.store', $fx['service']), [
            'addon_id' => $fx['productAddon']->id,
            'issue_date' => $issue,
        ])
        ->assertRedirect();

    $addon = ServiceAddon::where('service_id', $fx['service']->id)->firstOrFail();
    $order = Order::findOrFail($addon->order_id);

    $this->actingAs($fx['admin'], 'admin')
        ->put(route('admin.services.addons.update', [$fx['service'], $addon]), [
            'amount' => 75,
            'billing_cycle' => 'Annually',
            'next_due_date' => $issue,
        ]);

    // Staff accept the order from Orders; the invoice exists and stays unpaid.
    $this->actingAs($fx['admin'], 'admin')
        ->post(route('admin.orders.accept', $order))
        ->assertRedirect();

    $addon->refresh();
    $order->refresh();
    $invoice = Invoice::findOrFail($order->invoice_id);

    expect(strtolower((string) $order->status))->toBe('active')
        ->and(strtolower((string) $addon->status))->toBe('active')
        ->and($invoice->date->toDateString())->toBe($issue)
        ->and(strtolower((string) $invoice->status))->toBe('unpaid')
        ->and((float) $invoice->total)->toBe(75.0)
        ->and($addon->next_due_date->toDateString())->toBe(Carbon::parse($issue)->addYearNoOverflow()->toDateString());
});


it('repairs an active order that still has a draft addon', function () {
    Mail::fake();
    $fx = pnlcs_test_addon_order_setup();

    $addon = app(AddonService::class)->draftForService($fx['service'], $fx['productAddon']);
    $order = Order::findOrFail($addon->order_id);

    // What the old accept-through-orders path left behind: active order,
    // draft addon, no invoice.
    $order->update(['status' => 'active']);

    app(\App\Services\OrderService::class)->acceptOrder($order->fresh());

    $addon->refresh();
    $order->refresh();

    expect(strtolower((string) $order->status))->toBe('active')
        ->and($order->invoice_id)->not->toBeNull()
        ->and(strtolower((string) $addon->status))->toBe('active');
});


it('refuses to delete a product addon that is in use', function () {
    $fx = pnlcs_test_service_addon();

    $this->actingAs($fx['admin'], 'admin')
        ->delete(route('admin.config.addons.destroy', $fx['productAddon']->id))
        ->assertSessionHas('error');

    expect(ProductAddon::find($fx['productAddon']->id))->not->toBeNull();
});
