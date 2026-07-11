<?php

use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Pricing;
use App\Models\Product;
use App\Models\Service;
use App\Models\Upgrade;
use App\Models\User;
use App\Services\PaymentService;
use App\Services\UpgradeService;

/**
 * Prorated product upgrade/downgrade. Before this work processUpgrade wrote a
 * dead Upgrade row (amount 0, pending) that nothing ever processed, the upgrade
 * page passed the wrong view variable and pointed at a non-existent route, so
 * the feature did nothing.
 */

function upgradeSetup(float $currentMonthly, float $newMonthly, int $remainingDays): array
{
    $client  = Client::factory()->create();

    $current = Product::factory()->create();
    Pricing::factory()->create(['type' => 'product', 'rel_id' => $current->id, 'monthly' => $currentMonthly]);

    $new = Product::factory()->create();
    Pricing::factory()->create(['type' => 'product', 'rel_id' => $new->id, 'monthly' => $newMonthly]);

    $service = Service::factory()->create([
        'client_id'     => $client->id,
        'product_id'    => $current->id,
        'billing_cycle' => 'Monthly',
        'amount'        => $currentMonthly,
        'next_due_date' => now()->addDays($remainingDays),
        'status'        => 'active',
    ]);

    return [$client, $service, $current, $new];
}

function actingClient(Client $client): User
{
    $user = User::factory()->create();
    $user->clients()->attach($client->id);
    return $user;
}

it('computes a positive prorated difference for an upgrade', function () {
    [$client, $service, $cur, $new] = upgradeSetup(10.00, 40.00, 15); // half a 30-day cycle left

    $calc = app(UpgradeService::class)->calculateProration($service, $new);

    expect($calc['available'])->toBeTrue()
        ->and($calc['prorated_diff'])->toBe(15.00);  // (40 - 10) * 15/30
});

it('creates a pending upgrade + invoice, and applies the package change when paid', function () {
    [$client, $service, $cur, $new] = upgradeSetup(10.00, 40.00, 15);
    $user = actingClient($client);

    $this->actingAs($user)
        ->post(route('client.services.upgrade.process', $service), ['new_product_id' => $new->id])
        ->assertRedirect();

    $upgrade = Upgrade::where('rel_id', $service->id)->first();
    expect($upgrade)->not->toBeNull()
        ->and($upgrade->status)->toBe('pending')
        ->and((float) $upgrade->amount)->toBe(15.00);

    $invoice = Invoice::whereHas('items', fn ($q) => $q->where('type', 'Upgrade')->where('rel_id', $upgrade->id))->first();
    expect($invoice)->not->toBeNull();

    app(PaymentService::class)->applyPayment($invoice, 'banktransfer', 'TXN-U1', (float) $invoice->total);

    $service->refresh();
    $upgrade->refresh();
    expect($service->product_id)->toBe($new->id)
        ->and((float) $service->amount)->toBe(40.00)
        ->and($upgrade->status)->toBe('completed');
});

it('applies a downgrade immediately with no invoice', function () {
    [$client, $service, $cur, $new] = upgradeSetup(40.00, 10.00, 15); // cheaper product
    $user = actingClient($client);

    $this->actingAs($user)
        ->post(route('client.services.upgrade.process', $service), ['new_product_id' => $new->id])
        ->assertRedirect();

    $service->refresh();
    expect($service->product_id)->toBe($new->id)
        ->and((float) $service->amount)->toBe(10.00)
        ->and(InvoiceItem::where('type', 'Upgrade')->count())->toBe(0);

    $upgrade = Upgrade::where('rel_id', $service->id)->first();
    expect($upgrade->status)->toBe('completed');
});

it('rejects upgrading to the same product', function () {
    [$client, $service, $cur, $new] = upgradeSetup(10.00, 40.00, 15);
    $user = actingClient($client);

    $this->actingAs($user)
        ->post(route('client.services.upgrade.process', $service), ['new_product_id' => $service->product_id])
        ->assertRedirect();

    expect(Upgrade::count())->toBe(0);
});
