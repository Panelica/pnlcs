<?php

use App\Models\Client;
use App\Models\Product;
use App\Models\ProductAddon;
use App\Models\ProductGroup;
use App\Models\Service;
use App\Models\ServiceAddon;
use App\Services\AddonService;

/**
 * Billing an extra on a service that was never set up.
 *
 * The service query bills active services only - a service still pending has
 * not been provisioned, so there is nothing to charge for yet. The addon query
 * beside it asked a different question: it skipped terminated, cancelled and
 * fraud, and billed everything else, pending included.
 *
 * Addons are marked active when the order is accepted, and that happens even
 * when the service they belong to failed to provision and stayed pending, or
 * when the product needs manual acceptance and nobody has accepted it yet. So a
 * customer whose account was never created still received an invoice for the
 * extras on it, and not paying it starts the suspension chain.
 */
function addonOnServiceWithStatus(string $status): ServiceAddon
{
    $client = Client::factory()->create();
    $group = ProductGroup::factory()->create();
    $product = Product::factory()->create(['group_id' => $group->id]);
    $addon = ProductAddon::create([
        'name' => 'Dedicated IP',
        'packages' => (string) $product->id,
        'hidden' => false,
        'retired' => false,
        'sort_order' => 1,
        'tax' => false,
    ]);

    $service = Service::factory()->create([
        'client_id' => $client->id,
        'product_id' => $product->id,
        'status' => $status,
        'auto_renew' => true,
        'next_due_date' => now()->addDays(3)->toDateString(),
    ]);

    return ServiceAddon::create([
        'service_id' => $service->id,
        'addon_id' => $addon->id,
        'client_id' => $client->id,
        'qty' => 1,
        'amount' => 5,
        'billing_cycle' => 'Monthly',
        'next_due_date' => now()->addDays(3),
        'status' => 'active',
    ]);
}

function addonsDueNow(): array
{
    return app(AddonService::class)->dueQuery(now()->addDays(14)->endOfDay())->pluck('id')->all();
}

it('does not bill an extra on a service that was never set up', function () {
    $addon = addonOnServiceWithStatus('pending');

    expect(addonsDueNow())->not->toContain($addon->id);
});

it('still bills an extra on a live service', function () {
    $addon = addonOnServiceWithStatus('active');

    expect(addonsDueNow())->toContain($addon->id);
});

it('still bills an extra on a suspended service', function () {
    $addon = addonOnServiceWithStatus('suspended');

    expect(addonsDueNow())->toContain($addon->id);
});

it('still leaves out an extra on a service that has ended', function () {
    $terminated = addonOnServiceWithStatus('terminated');
    $cancelled = addonOnServiceWithStatus('cancelled');

    $due = addonsDueNow();

    expect($due)->not->toContain($terminated->id)
        ->and($due)->not->toContain($cancelled->id);
});
