<?php

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Pricing;
use App\Models\Product;
use App\Models\Service;
use App\Models\Upgrade;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\UpgradeService;

/**
 * The same upgrade, ordered twice.
 *
 * Asking to move to another package raises an Upgrade row and a prorated
 * invoice, and nothing checks whether one is already waiting. A customer who
 * clicks the button twice - or reloads the confirmation - gets two upgrade
 * rows and two invoices for the same move. Both are payable, and paying the
 * second one charges again for something already bought.
 *
 * Both doors into this, the client area and the API, go through the same
 * method, so neither used to notice.
 */
function twoProductService(float $currentMonthly, float $newMonthly): array
{
    $client = Client::factory()->create();

    $current = Product::factory()->create();
    Pricing::factory()->create(['type' => 'product', 'rel_id' => $current->id, 'monthly' => $currentMonthly]);

    $new = Product::factory()->create();
    Pricing::factory()->create(['type' => 'product', 'rel_id' => $new->id, 'monthly' => $newMonthly]);

    $service = Service::factory()->create([
        'client_id' => $client->id,
        'product_id' => $current->id,
        'billing_cycle' => 'Monthly',
        'amount' => $currentMonthly,
        'next_due_date' => now()->addDays(15),
        'status' => 'active',
    ]);

    return [$client, $service, $new];
}

it('does not raise a second upgrade while the first is waiting to be paid', function () {
    [$client, $service, $new] = twoProductService(10.00, 40.00);

    $first = app(UpgradeService::class)->requestProductChange($service, $new);
    expect($first['success'])->toBeTrue();

    $second = app(UpgradeService::class)->requestProductChange($service->fresh(), $new);

    expect($second['success'])->toBeFalse();
    expect(Upgrade::where('rel_id', $service->id)->count())->toBe(1);
    expect(Invoice::where('client_id', $client->id)->count())->toBe(1);
});

it('does not let a different package slip past the one already ordered', function () {
    [$client, $service, $new] = twoProductService(10.00, 40.00);

    $third = Product::factory()->create();
    Pricing::factory()->create(['type' => 'product', 'rel_id' => $third->id, 'monthly' => 80.00]);

    app(UpgradeService::class)->requestProductChange($service, $new);
    $second = app(UpgradeService::class)->requestProductChange($service->fresh(), $third);

    expect($second['success'])->toBeFalse();
    expect(Upgrade::where('rel_id', $service->id)->count())->toBe(1);
});

it('lets the customer move again once the first move is done', function () {
    [$client, $service, $new] = twoProductService(10.00, 40.00);

    $first = app(UpgradeService::class)->requestProductChange($service, $new);
    app(UpgradeService::class)->apply($first['upgrade']);

    $third = Product::factory()->create();
    Pricing::factory()->create(['type' => 'product', 'rel_id' => $third->id, 'monthly' => 80.00]);

    $second = app(UpgradeService::class)->requestProductChange($service->fresh(), $third);

    expect($second['success'])->toBeTrue();
    expect(Upgrade::where('rel_id', $service->id)->count())->toBe(2);
});

it('does not count somebody else\'s pending upgrade against this service', function () {
    [$client, $service, $new] = twoProductService(10.00, 40.00);
    [$otherClient, $otherService, $otherNew] = twoProductService(10.00, 40.00);

    app(UpgradeService::class)->requestProductChange($otherService, $otherNew);

    $mine = app(UpgradeService::class)->requestProductChange($service, $new);

    expect($mine['success'])->toBeTrue();
});

it('does not lock the customer out when the invoice was cancelled', function () {
    [$client, $service, $new] = twoProductService(10.00, 40.00);

    $first = app(UpgradeService::class)->requestProductChange($service, $new);
    app(InvoiceService::class)->cancelInvoice($first['invoice']);

    $second = app(UpgradeService::class)->requestProductChange($service->fresh(), $new);

    expect($second['success'])->toBeTrue();
    expect(Upgrade::where('rel_id', $service->id)->where('status', 'cancelled')->count())->toBe(1);
});

it('closes the door through the client area too', function () {
    [$client, $service, $new] = twoProductService(10.00, 40.00);

    $user = User::factory()->create();
    $user->clients()->attach($client->id);

    $this->actingAs($user)
        ->post(route('client.services.upgrade.process', $service), ['new_product_id' => $new->id])
        ->assertRedirect();

    $this->actingAs($user)
        ->post(route('client.services.upgrade.process', $service), ['new_product_id' => $new->id])
        ->assertRedirect();

    expect(Invoice::where('client_id', $client->id)->count())->toBe(1);
});
