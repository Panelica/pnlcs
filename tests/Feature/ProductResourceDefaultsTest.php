<?php

use App\Models\Admin;
use App\Models\Product;

/*
 * The Panelica resources card filled every limit with the panel's default
 * (1 GB RAM, 100% CPU, 5 GB disk…), also on a product without a module. Saving
 * such a product for any reason wrote those numbers into it, and the price list
 * then showed "1 GB RAM · 1 vCPU · 5 GB disk" for a product that has none of it.
 */

function resourcesAdmin(): Admin
{
    return Admin::factory()->create();
}

function saveProduct(Product $product, array $extra = []): void
{
    test()->actingAs(resourcesAdmin(), 'admin')->put(route('admin.products.update', $product), array_merge([
        'name' => $product->name, 'group_id' => $product->group_id, 'type' => 'hosting', 'pay_type' => 'recurring',
    ], $extra))->assertRedirect();
}

test('a product without a module is not given the panel defaults by its edit form', function () {
    $product = Product::factory()->create(['server_type' => null, 'config_options' => null]);

    $this->actingAs(resourcesAdmin(), 'admin')->get(route('admin.products.edit', $product))
        ->assertOk()
        ->assertSee('name="res_memory_mb" value="" placeholder="1024"', false);
});

test('saving a product without a module as its form shows it leaves its resources empty, and the price list says nothing about them', function () {
    $product = Product::factory()->create(['server_type' => null, 'config_options' => null]);

    // Send back the numbers the edit form shows, as a browser would for an untouched card.
    $html = $this->actingAs(resourcesAdmin(), 'admin')->get(route('admin.products.edit', $product))->getContent();
    preg_match_all('/name="(res_[a-z_]+)" value="([^"]*)"/', $html, $m, PREG_SET_ORDER);
    saveProduct($product, ['server_type' => '', 'res_section' => 1] + array_column($m, 2, 1));

    $cfg = (array) $product->fresh()->config_options;
    expect($cfg)->not->toHaveKey('res_memory_mb')
        ->and($cfg)->not->toHaveKey('res_disk_mb')
        ->and($product->fresh()->resourceSummary())->toBe([]);
});

test('limits typed in on a product without a module are still kept', function () {
    $product = Product::factory()->create(['server_type' => null, 'config_options' => null]);

    saveProduct($product, ['server_type' => '', 'res_section' => 1, 'res_memory_mb' => 2048]);

    expect(((array) $product->fresh()->config_options)['res_memory_mb'])->toBe(2048);
});

test('a Panelica product still has the defaults filled in', function () {
    $product = Product::factory()->create(['server_type' => 'panelica', 'config_options' => null]);

    $this->actingAs(resourcesAdmin(), 'admin')->get(route('admin.products.edit', $product))
        ->assertOk()
        ->assertSee('name="res_memory_mb" value="1024"', false);
});

test('a request for another module that still carries the card does not give it Panelica limits', function () {
    $product = Product::factory()->create(['server_type' => 'cpanel', 'config_options' => null]);

    saveProduct($product, ['server_type' => 'cpanel', 'res_section' => 1, 'res_memory_mb' => 1024, 'res_disk_mb' => 5120]);

    expect((array) $product->fresh()->config_options)->not->toHaveKey('res_memory_mb');
});
