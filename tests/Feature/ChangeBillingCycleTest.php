<?php

use App\Models\Client;
use App\Models\ConfigOption;
use App\Models\ConfigOptionGroup;
use App\Models\ConfigOptionLink;
use App\Models\ConfigOptionSub;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Pricing;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Service;
use App\Models\ServiceConfigOption;
use App\Models\User;

/*
 * A customer moves a service to another billing cycle, from its next renewal.
 *
 * Only the admin could change a service's cycle. A customer who wanted to pay
 * yearly instead of monthly - and get the yearly price - had to ask.
 */

function bccFixture(): array
{
    $currency = Currency::where('is_default', true)->first()
        ?? Currency::create(['code' => 'USD', 'prefix' => '$', 'suffix' => '', 'rate' => 1, 'is_default' => true]);

    $product = Product::factory()->create(['group_id' => ProductGroup::factory()->create()->id, 'name' => 'VPS M', 'pay_type' => 'recurring', 'tax' => false]);
    Pricing::updateOrCreate(['type' => 'product', 'rel_id' => $product->id, 'currency_id' => $currency->id],
        ['monthly' => 20, 'quarterly' => -1, 'semiannually' => -1, 'annually' => 200, 'biennially' => -1, 'triennially' => -1]);

    $group = ConfigOptionGroup::create(['name' => 'Resources']);
    ConfigOptionLink::create(['group_id' => $group->id, 'product_id' => $product->id]);
    $ram = ConfigOption::create(['group_id' => $group->id, 'option_name' => 'RAM', 'option_type' => 'dropdown', 'sort_order' => 1]);
    $ram8 = ConfigOptionSub::create(['config_id' => $ram->id, 'option_name' => '8 GB', 'sort_order' => 1]);
    Pricing::updateOrCreate(['type' => ConfigOptionSub::PRICING_TYPE, 'rel_id' => $ram8->id, 'currency_id' => $currency->id], ['monthly' => 5, 'annually' => 50]);

    $user = User::factory()->create();
    $client = Client::factory()->create();
    $user->clients()->attach($client->id, ['owner' => true]);

    $due = now()->addDays(12)->startOfDay();
    $service = Service::factory()->create([
        'client_id' => $client->id, 'product_id' => $product->id, 'status' => 'active',
        'amount' => 25, 'billing_cycle' => 'Monthly', 'next_due_date' => $due,
    ]);
    ServiceConfigOption::create(['service_id' => $service->id, 'config_id' => $ram->id, 'option_id' => $ram8->id, 'qty' => 1, 'unit_price' => 5]);

    return compact('user', 'client', 'service', 'due');
}

it('offers the cycles the product is priced for, with the new amount', function () {
    $fx = bccFixture();

    test()->actingAs($fx['user'])->get(route('client.services.show', $fx['service']))
        ->assertOk()
        ->assertSee(route('client.services.cycle', $fx['service']), false)
        ->assertSee('value="Annually"', false)
        ->assertDontSee('value="Quarterly"', false)
        ->assertSee(money_fmt(250));
});

it('moves the service to the new cycle from its next renewal, options repriced', function () {
    $fx = bccFixture();

    test()->actingAs($fx['user'])->post(route('client.services.cycle', $fx['service']), ['billing_cycle' => 'Annually'])
        ->assertSessionHas('success');

    $service = $fx['service']->fresh();
    expect($service->billing_cycle)->toBe('Annually')
        ->and((float) $service->amount)->toBe(250.0)
        ->and($service->next_due_date->toDateString())->toBe($fx['due']->toDateString())
        ->and((float) ServiceConfigOption::where('service_id', $service->id)->value('unit_price'))->toBe(50.0)
        ->and(Invoice::where('client_id', $fx['client']->id)->count())->toBe(0);
});

it('refuses a cycle the product is not sold on, or the one it is on', function () {
    $fx = bccFixture();

    foreach (['Quarterly', 'Monthly', 'Weekly'] as $cycle) {
        test()->actingAs($fx['user'])->post(route('client.services.cycle', $fx['service']), ['billing_cycle' => $cycle])
            ->assertSessionHas('error');
    }

    expect($fx['service']->fresh()->billing_cycle)->toBe('Monthly')->and((float) $fx['service']->fresh()->amount)->toBe(25.0);
});

it('waits for an open renewal invoice to be settled first', function () {
    $fx = bccFixture();
    $invoice = Invoice::factory()->create(['client_id' => $fx['client']->id, 'status' => 'unpaid', 'total' => 25]);
    InvoiceItem::create(['invoice_id' => $invoice->id, 'client_id' => $fx['client']->id, 'type' => 'Hosting', 'rel_id' => $fx['service']->id, 'description' => 'VPS M', 'amount' => 25]);

    test()->actingAs($fx['user'])->post(route('client.services.cycle', $fx['service']), ['billing_cycle' => 'Annually'])
        ->assertSessionHas('error', __('client.services.cycle_invoice_open'));

    expect($fx['service']->fresh()->billing_cycle)->toBe('Monthly');
});

it('does not let another customer change it', function () {
    $fx = bccFixture();
    $stranger = User::factory()->create();
    $stranger->clients()->attach(Client::factory()->create()->id, ['owner' => true]);

    test()->actingAs($stranger)->post(route('client.services.cycle', $fx['service']), ['billing_cycle' => 'Annually'])->assertForbidden();

    expect($fx['service']->fresh()->billing_cycle)->toBe('Monthly');
});

it('bills the next renewal at the new cycle and moves the date on by it', function () {
    $fx = bccFixture();
    test()->actingAs($fx['user'])->post(route('client.services.cycle', $fx['service']), ['billing_cycle' => 'Annually']);

    $invoice = app(\App\Services\InvoiceGenerationService::class)->generateForService($fx['service']->fresh());
    expect((float) $invoice->total)->toBe(250.0);

    app(\App\Services\PaymentService::class)->applyPayment($invoice, 'banktransfer', 'tx-cycle', null);
    expect($fx['service']->fresh()->next_due_date->toDateString())->toBe($fx['due']->copy()->addYear()->toDateString());
});
