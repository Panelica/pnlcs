<?php

use App\Models\Client;
use App\Models\ConfigOption;
use App\Models\ConfigOptionGroup;
use App\Models\ConfigOptionLink;
use App\Models\ConfigOptionSub;
use App\Models\Currency;
use App\Models\Pricing;
use App\Models\Product;
use App\Models\ProductAddon;
use App\Models\ProductGroup;
use App\Models\Service;
use App\Models\ServiceAddon;
use App\Models\ServiceConfigOption;
use App\Models\Upgrade;
use App\Services\UpgradeService;
use Illuminate\Support\Facades\Http;

/**
 * Changing package must not quietly change what the customer pays for their
 * extras.
 *
 * A configurable option's price lives inside the service amount, so setting
 * that amount to the new product's base price on its own throws the option
 * money away and the customer renews cheaper than they configured — for the
 * life of the service.
 */
function upgradeFixture(): array
{
    $currency = Currency::where('is_default', true)->first()
        ?? Currency::create(['code' => 'USD', 'prefix' => '$', 'suffix' => '', 'rate' => 1, 'is_default' => true]);

    $small = Product::factory()->create([
        'group_id' => ProductGroup::factory()->create()->id,
        'name' => 'Small', 'server_type' => null, 'tax' => false,
    ]);
    $big = Product::factory()->create([
        'group_id' => ProductGroup::factory()->create()->id,
        'name' => 'Big', 'server_type' => null, 'tax' => false,
    ]);

    Pricing::updateOrCreate(['type' => 'product', 'rel_id' => $small->id, 'currency_id' => $currency->id], ['monthly' => 20]);
    Pricing::updateOrCreate(['type' => 'product', 'rel_id' => $big->id, 'currency_id' => $currency->id], ['monthly' => 50]);

    // One option group, offered with BOTH products.
    $group = ConfigOptionGroup::create(['name' => 'Resources']);
    ConfigOptionLink::create(['group_id' => $group->id, 'product_id' => $small->id]);
    ConfigOptionLink::create(['group_id' => $group->id, 'product_id' => $big->id]);
    $ram = ConfigOption::create(['group_id' => $group->id, 'option_name' => 'RAM', 'option_type' => 'dropdown', 'sort_order' => 1]);
    $ram8 = ConfigOptionSub::create(['config_id' => $ram->id, 'option_name' => '8 GB', 'sort_order' => 1]);
    Pricing::updateOrCreate(
        ['type' => ConfigOptionSub::PRICING_TYPE, 'rel_id' => $ram8->id, 'currency_id' => $currency->id],
        ['monthly' => 10]
    );

    // A second group only the small product offers.
    $legacyGroup = ConfigOptionGroup::create(['name' => 'Legacy']);
    ConfigOptionLink::create(['group_id' => $legacyGroup->id, 'product_id' => $small->id]);
    $legacy = ConfigOption::create(['group_id' => $legacyGroup->id, 'option_name' => 'Legacy Extra', 'option_type' => 'dropdown', 'sort_order' => 1]);
    $legacyOn = ConfigOptionSub::create(['config_id' => $legacy->id, 'option_name' => 'On', 'sort_order' => 1]);
    Pricing::updateOrCreate(
        ['type' => ConfigOptionSub::PRICING_TYPE, 'rel_id' => $legacyOn->id, 'currency_id' => $currency->id],
        ['monthly' => 7]
    );

    return compact('small', 'big', 'ram', 'ram8', 'legacy', 'legacyOn', 'currency');
}

function serviceOnSmall(array $fx, Client $client): Service
{
    // 20 base + 10 RAM + 7 legacy
    $service = Service::factory()->create([
        'client_id' => $client->id,
        'product_id' => $fx['small']->id,
        'status' => 'active',
        'amount' => 37,
        'billing_cycle' => 'Monthly',
        'next_due_date' => now()->addMonth(),
        'domain' => 'upgrade-me.com',
    ]);

    ServiceConfigOption::create([
        'service_id' => $service->id,
        'config_id' => $fx['ram']->id,
        'option_id' => $fx['ram8']->id,
        'qty' => 1,
        'unit_price' => 10,
    ]);
    ServiceConfigOption::create([
        'service_id' => $service->id,
        'config_id' => $fx['legacy']->id,
        'option_id' => $fx['legacyOn']->id,
        'qty' => 1,
        'unit_price' => 7,
    ]);

    return $service;
}

test('an upgrade keeps charging for options the new product still offers', function () {
    Http::fake(['*' => Http::response(['success' => true], 200)]);
    $fx = upgradeFixture();
    $client = Client::factory()->create();
    $service = serviceOnSmall($fx, $client);

    $upgrade = Upgrade::create([
        'client_id' => $client->id,
        'type' => 'product',
        'rel_id' => $service->id,
        'original_value' => $fx['small']->id,
        'new_value' => $fx['big']->id,
        'amount' => 30,
        'status' => 'pending',
    ]);

    app(UpgradeService::class)->apply($upgrade);

    $service->refresh();

    // 50 for the new package plus the 10 RAM option it still offers. The
    // legacy option is not sold with the new product, so it goes.
    expect((float) $service->amount)->toBe(60.0)
        ->and($service->product_id)->toBe($fx['big']->id);
});

test('an option the new product does not offer is dropped, not silently kept', function () {
    Http::fake(['*' => Http::response(['success' => true], 200)]);
    $fx = upgradeFixture();
    $client = Client::factory()->create();
    $service = serviceOnSmall($fx, $client);

    $upgrade = Upgrade::create([
        'client_id' => $client->id, 'type' => 'product', 'rel_id' => $service->id,
        'original_value' => $fx['small']->id, 'new_value' => $fx['big']->id,
        'amount' => 30, 'status' => 'pending',
    ]);

    app(UpgradeService::class)->apply($upgrade);

    $remaining = ServiceConfigOption::where('service_id', $service->id)->pluck('config_id')->all();

    expect($remaining)->toBe([$fx['ram']->id]);
});

test('an upgrade leaves addons alone', function () {
    Http::fake(['*' => Http::response(['success' => true], 200)]);
    $fx = upgradeFixture();
    $client = Client::factory()->create();
    $service = serviceOnSmall($fx, $client);

    $addon = ProductAddon::create([
        'name' => 'Dedicated IP', 'packages' => null,
        'hidden' => false, 'retired' => false, 'sort_order' => 1, 'tax' => false,
    ]);
    $serviceAddon = ServiceAddon::create([
        'service_id' => $service->id,
        'addon_id' => $addon->id,
        'client_id' => $client->id,
        'qty' => 1,
        'amount' => 5,
        'billing_cycle' => 'Monthly',
        'next_due_date' => now()->addMonth(),
        'status' => 'active',
    ]);

    $upgrade = Upgrade::create([
        'client_id' => $client->id, 'type' => 'product', 'rel_id' => $service->id,
        'original_value' => $fx['small']->id, 'new_value' => $fx['big']->id,
        'amount' => 30, 'status' => 'pending',
    ]);

    app(UpgradeService::class)->apply($upgrade);

    // Addons bill on their own record, so a package change must not disturb them.
    expect($serviceAddon->fresh()->status)->toBe('active')
        ->and((float) $serviceAddon->fresh()->amount)->toBe(5.0);
});

test('the quoted proration matches the bill the customer ends up with', function () {
    Http::fake(['*' => Http::response(['success' => true], 200)]);
    $fx = upgradeFixture();
    $client = Client::factory()->create();

    // 20 base + 10 RAM, a full cycle left to run.
    $service = Service::factory()->create([
        'client_id' => $client->id,
        'product_id' => $fx['small']->id,
        'status' => 'active',
        'amount' => 30,
        'billing_cycle' => 'Monthly',
        'next_due_date' => now()->addDays(30),
        'domain' => 'proration.com',
    ]);
    ServiceConfigOption::create([
        'service_id' => $service->id,
        'config_id' => $fx['ram']->id,
        'option_id' => $fx['ram8']->id,
        'qty' => 1,
        'unit_price' => 10,
    ]);

    $calc = app(UpgradeService::class)->calculateProration($service, $fx['big']);

    // The service will renew at 60 (50 + the 10 option the new package keeps),
    // so the difference to charge is 30 — not 20, which is what comparing the
    // new base price against an amount that includes options gives.
    expect((float) $calc['new_recurring'])->toBe(60.0)
        ->and((float) $calc['prorated_diff'])->toBe(30.0);

    $upgrade = Upgrade::create([
        'client_id' => $client->id, 'type' => 'product', 'rel_id' => $service->id,
        'original_value' => $fx['small']->id, 'new_value' => $fx['big']->id,
        'amount' => $calc['prorated_diff'], 'status' => 'pending',
    ]);
    app(UpgradeService::class)->apply($upgrade);

    expect((float) $service->fresh()->amount)->toBe((float) $calc['new_recurring']);
});
