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
use App\Models\Upgrade;
use App\Models\User;
use App\Services\OptionUpgradeService;
use App\Services\PaymentService;
use App\Services\ProvisioningService;
use App\Services\UpgradeService;

/**
 * What a package change is charged against.
 *
 * The prorated charge counted at most one cycle of the time left: a service
 * renewed early, paid up for two months, was charged the new price for one of
 * them. With a renewal invoice still open the time left is a day or none, so
 * the change was free, and the open invoice then billed the next period at the
 * old price. And an option change fixed its new recurring amount when it was
 * asked for, so a cycle changed before the invoice was paid got the old
 * cycle's price.
 */
function paidPeriodCurrency(): Currency
{
    return Currency::where('is_default', true)->first()
        ?? Currency::create(['code' => 'USD', 'prefix' => '$', 'suffix' => '', 'rate' => 1, 'is_default' => true]);
}

function paidPeriodProducts(int $daysLeft): array
{
    $currency = paidPeriodCurrency();
    $client = Client::factory()->create();
    $current = Product::factory()->create();
    Pricing::updateOrCreate(['type' => 'product', 'rel_id' => $current->id, 'currency_id' => $currency->id], ['monthly' => 10]);
    $bigger = Product::factory()->create();
    Pricing::updateOrCreate(['type' => 'product', 'rel_id' => $bigger->id, 'currency_id' => $currency->id], ['monthly' => 40]);
    $service = Service::factory()->create([
        'client_id' => $client->id, 'product_id' => $current->id, 'billing_cycle' => 'Monthly',
        'amount' => 10, 'next_due_date' => now()->addDays($daysLeft), 'status' => 'active',
    ]);

    return [$client, $service, $bigger];
}

function paidPeriodOptions(int $daysLeft = 15): array
{
    $currency = paidPeriodCurrency();
    $product = Product::factory()->create(['group_id' => ProductGroup::factory()->create()->id, 'server_type' => null, 'tax' => false]);
    Pricing::updateOrCreate(['type' => 'product', 'rel_id' => $product->id, 'currency_id' => $currency->id], ['monthly' => 20, 'annually' => 200]);
    $group = ConfigOptionGroup::create(['name' => 'Resources '.uniqid()]);
    ConfigOptionLink::create(['group_id' => $group->id, 'product_id' => $product->id]);
    $ram = ConfigOption::create(['group_id' => $group->id, 'option_name' => 'memory|RAM', 'option_type' => 'dropdown', 'sort_order' => 1]);
    $ram4 = ConfigOptionSub::create(['config_id' => $ram->id, 'option_name' => '4096|4 GB', 'sort_order' => 1]);
    $ram8 = ConfigOptionSub::create(['config_id' => $ram->id, 'option_name' => '8192|8 GB', 'sort_order' => 2]);
    foreach ([[$ram4, 5, 50], [$ram8, 15, 150]] as [$sub, $monthly, $annually]) {
        Pricing::updateOrCreate(['type' => ConfigOptionSub::PRICING_TYPE, 'rel_id' => $sub->id, 'currency_id' => $currency->id], ['monthly' => $monthly, 'annually' => $annually]);
    }

    $user = User::factory()->create();
    $client = Client::factory()->create();
    $user->clients()->attach($client->id, ['owner' => true]);
    $service = Service::factory()->create([
        'client_id' => $client->id, 'product_id' => $product->id, 'status' => 'active', 'amount' => 25,
        'billing_cycle' => 'Monthly', 'next_due_date' => now()->addDays($daysLeft),
    ]);
    ServiceConfigOption::create(['service_id' => $service->id, 'config_id' => $ram->id, 'option_id' => $ram4->id, 'qty' => 1, 'unit_price' => 5]);

    return compact('user', 'client', 'service', 'ram', 'ram4', 'ram8');
}

function openRenewalFor(Service $service): void
{
    $invoice = Invoice::factory()->create(['client_id' => $service->client_id, 'status' => 'unpaid', 'total' => $service->amount]);
    InvoiceItem::create([
        'invoice_id' => $invoice->id, 'client_id' => $service->client_id, 'type' => 'Hosting',
        'rel_id' => $service->id, 'description' => 'Renewal', 'amount' => $service->amount, 'taxed' => false,
    ]);
}

it('charges a package change for every day already paid for, beyond one cycle', function () {
    [, $service, $bigger] = paidPeriodProducts(45); // renewed early: a month and a half paid

    $calc = app(UpgradeService::class)->calculateProration($service, $bigger);

    expect($calc['prorated_diff'])->toBe(45.0); // (40 - 10) * 45/30, not capped at 30
});

it('still charges half a cycle for half a cycle left', function () {
    [, $service, $bigger] = paidPeriodProducts(15);

    expect(app(UpgradeService::class)->calculateProration($service, $bigger)['prorated_diff'])->toBe(15.0);
});

it('refuses a package change while a renewal invoice is open', function () {
    [, $service, $bigger] = paidPeriodProducts(0);
    openRenewalFor($service);

    $result = app(UpgradeService::class)->requestProductChange($service, $bigger);

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toBe(__('messages.error.upgrade_after_renewal_paid'))
        ->and(Upgrade::where('rel_id', $service->id)->exists())->toBeFalse()
        ->and((int) $service->fresh()->product_id)->not->toBe($bigger->id);
});

it('refuses an option change while a renewal invoice is open', function () {
    $fx = paidPeriodOptions(0);
    openRenewalFor($fx['service']);

    $result = app(OptionUpgradeService::class)->request($fx['service'], [$fx['ram']->id => $fx['ram8']->id]);

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toBe(__('messages.error.upgrade_after_renewal_paid'))
        ->and(Upgrade::where('rel_id', $fx['service']->id)->exists())->toBeFalse();
});

it('charges an option change for every day already paid for', function () {
    $fx = paidPeriodOptions(45);

    $result = app(OptionUpgradeService::class)->request($fx['service'], [$fx['ram']->id => $fx['ram8']->id]);

    $line = $result['invoice']->items()->where('type', 'Upgrade')->first();
    expect((float) $line->amount)->toBe(15.0); // +10/month for 45 of 30 days
});

it('prices an option change at the cycle the service is on when it is paid', function () {
    $fx = paidPeriodOptions(15);
    $spy = Mockery::mock(ProvisioningService::class);
    $spy->shouldReceive('changePackage')->andReturn(['success' => true]);
    app()->instance(ProvisioningService::class, $spy);

    $result = app(OptionUpgradeService::class)->request($fx['service'], [$fx['ram']->id => $fx['ram8']->id]);

    // Before the invoice is paid the customer moves to a yearly cycle, as
    // BillingCycleChange does it: the options and the amount at yearly prices.
    ServiceConfigOption::where('service_id', $fx['service']->id)->update(['unit_price' => 50]);
    $fx['service']->update(['billing_cycle' => 'Annually', 'amount' => 250]);

    app(PaymentService::class)->applyPayment($result['invoice'], 'banktransfer', 'tx-cycle-1', null);

    // 250 + (150 - 50): the yearly price of the bigger RAM, not the monthly 35.
    expect((float) $fx['service']->fresh()->amount)->toBe(350.0)
        ->and((float) ServiceConfigOption::where('service_id', $fx['service']->id)->value('unit_price'))->toBe(150.0);
});
