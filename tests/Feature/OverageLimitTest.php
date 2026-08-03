<?php

use App\Models\Client;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Service;
use App\Services\InvoiceGenerationService;

/**
 * The allowance an overage is billed against.
 *
 * Overage reads the limit recorded on the service, and only the cPanel module
 * writes one. Panelica reports the disk quota and never the bandwidth
 * allowance; Plesk reports neither. On those panels a customer could use any
 * amount of either and no overage line was ever raised.
 *
 * The figure the product was sold with stands in when the panel has not said.
 */
function limitOverageProduct(array $config = []): Product
{
    return Product::factory()->create([
        'group_id' => ProductGroup::factory()->create()->id,
        'overage_enabled' => true,
        'overage_disk_rate' => 0.10,
        'overage_bw_rate' => 0.05,
        'tax' => false,
        'config_options' => $config === [] ? null : json_encode($config),
    ]);
}

function limitOverageService(Product $product, array $usage): Service
{
    return Service::factory()->create(array_merge([
        'client_id' => Client::factory()->create()->id,
        'product_id' => $product->id,
        'server_id' => null,
        'status' => 'active',
        'domain' => 'heavy-user.example',
    ], $usage));
}

test('a panel that reports the limit is billed against it', function () {
    $service = limitOverageService(limitOverageProduct(), [
        'disk_usage' => 1500,
        'disk_limit' => 1000,
        'bw_usage' => 0,
        'bw_limit' => 0,
    ]);

    $items = app(InvoiceGenerationService::class)->calculateOverageItems($service);

    expect($items)->toHaveCount(1)
        ->and($items[0]['amount'])->toBe(50.0);
});

test('a panel that reports no bandwidth allowance falls back to the plan', function () {
    $product = limitOverageProduct(['res_bandwidth_mb' => 10240]);

    $service = limitOverageService($product, [
        'disk_usage' => 0,
        'disk_limit' => 0,
        'bw_usage' => 12240,
        'bw_limit' => 0,
    ]);

    $items = app(InvoiceGenerationService::class)->calculateOverageItems($service);

    expect($items)->toHaveCount(1)
        ->and($items[0]['amount'])->toBe(100.0);
});

test('a panel that reports no disk quota falls back to the plan', function () {
    $product = limitOverageProduct(['res_disk_mb' => 1024]);

    $service = limitOverageService($product, [
        'disk_usage' => 1524,
        'disk_limit' => 0,
        'bw_usage' => 0,
        'bw_limit' => 0,
    ]);

    $items = app(InvoiceGenerationService::class)->calculateOverageItems($service);

    expect($items)->toHaveCount(1)
        ->and($items[0]['amount'])->toBe(50.0);
});

test('what the panel says wins over the plan', function () {
    $product = limitOverageProduct(['res_disk_mb' => 100]);

    $service = limitOverageService($product, [
        'disk_usage' => 1500,
        'disk_limit' => 2000,
        'bw_usage' => 0,
        'bw_limit' => 0,
    ]);

    expect(app(InvoiceGenerationService::class)->calculateOverageItems($service))->toBe([]);
});

test('with no allowance anywhere nothing is billed', function () {
    $service = limitOverageService(limitOverageProduct(), [
        'disk_usage' => 9999,
        'disk_limit' => 0,
        'bw_usage' => 9999,
        'bw_limit' => 0,
    ]);

    expect(app(InvoiceGenerationService::class)->calculateOverageItems($service))->toBe([]);
});
