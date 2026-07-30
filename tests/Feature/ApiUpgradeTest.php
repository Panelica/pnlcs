<?php

use App\Models\ApiCredential;
use App\Models\Client;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\Pricing;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Service;
use Database\Factories\ApiCredentialFactory;
use Illuminate\Support\Facades\Mail;

/**
 * Changing a customer's package through the API.
 *
 * The endpoint wrote product_id and stopped. The customer was moved onto a
 * bigger plan while the recurring amount stayed at the old price, the
 * difference was never invoiced and the server was never told — the client
 * area has always done all three.
 */
function apiKeyHeaders(): array
{
    $credential = ApiCredential::factory()->create();

    return [
        'X-API-Key' => $credential->identifier,
        'X-API-Secret' => ApiCredentialFactory::PLAINTEXT_SECRET,
    ];
}

function pricedPlan(float $monthly, bool $sellable = true): Product
{
    $product = Product::factory()->create([
        'group_id' => ProductGroup::factory()->create()->id,
        'hidden' => ! $sellable,
        'retired' => false,
        'tax' => false,
    ]);

    Pricing::create([
        'type' => 'product',
        'rel_id' => $product->id,
        'currency_id' => Currency::firstOrCreate(
            ['code' => 'USD'],
            ['prefix' => '$', 'suffix' => '', 'rate' => 1, 'is_default' => true]
        )->id,
        'monthly' => $monthly,
    ]);

    return $product;
}

function upgradableService(Product $current): Service
{
    return Service::factory()->create([
        'client_id' => Client::factory()->create(['tax_exempt' => true])->id,
        'product_id' => $current->id,
        'status' => 'active',
        'billing_cycle' => 'Monthly',
        'amount' => 10,
        'next_due_date' => now()->addDays(15),
    ]);
}

test('an upgrade through the api bills the difference', function () {
    Mail::fake();

    $service = upgradableService(pricedPlan(10));
    $bigger = pricedPlan(30);

    $response = $this->withHeaders(apiKeyHeaders())
        ->postJson('/api/v1/upgradeproduct', [
            'serviceid' => $service->id,
            'packageid' => $bigger->id,
        ])->assertSuccessful();

    $body = $response->json();
    $invoiceId = $body['data']['invoiceid'] ?? $body['invoiceid'] ?? null;

    expect($invoiceId)->not->toBeNull()
        ->and((float) Invoice::findOrFail($invoiceId)->total)->toBeGreaterThan(0.0);
});

test('the package does not change until the difference is paid', function () {
    Mail::fake();

    $current = pricedPlan(10);
    $service = upgradableService($current);
    $bigger = pricedPlan(30);

    $this->withHeaders(apiKeyHeaders())
        ->postJson('/api/v1/upgradeproduct', [
            'serviceid' => $service->id,
            'packageid' => $bigger->id,
        ])->assertSuccessful();

    expect($service->fresh()->product_id)->toBe($current->id);
});

test('an upgrade to a package that is not for sale is refused', function () {
    Mail::fake();

    $service = upgradableService(pricedPlan(10));
    $hidden = pricedPlan(30, sellable: false);

    $this->withHeaders(apiKeyHeaders())
        ->postJson('/api/v1/upgradeproduct', [
            'serviceid' => $service->id,
            'packageid' => $hidden->id,
        ])->assertStatus(422);

    expect($service->fresh()->product_id)->not->toBe($hidden->id);
});

test('an upgrade to the package the service is already on is refused', function () {
    Mail::fake();

    $current = pricedPlan(10);
    $service = upgradableService($current);

    $this->withHeaders(apiKeyHeaders())
        ->postJson('/api/v1/upgradeproduct', [
            'serviceid' => $service->id,
            'packageid' => $current->id,
        ])->assertStatus(422);
});

test('a custom module function is refused rather than reported as run', function () {
    $service = upgradableService(pricedPlan(10));

    $this->withHeaders(apiKeyHeaders())
        ->postJson('/api/v1/modulecustom', ['serviceid' => $service->id])
        ->assertStatus(501);
});
