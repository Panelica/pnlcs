<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Service;

/*
 * services.product_id is a plain column. Deleting a product that customers
 * still hold left every one of their services pointing at nothing: no name
 * on the renewal line, no server module to suspend or terminate with, and a
 * product page that quietly said "Hosting Service".
 */
function productOwnerAdmin(): Admin
{
    return Admin::factory()->create(['role_id' => AdminRole::factory()->fullAdmin()->create()->id]);
}

test('a product customers still hold cannot be deleted, only retired', function () {
    $product = Product::factory()->create(['group_id' => ProductGroup::factory()->create()->id]);
    Service::factory()->active()->create(['product_id' => $product->id, 'client_id' => Client::factory()->create()->id]);

    $this->actingAs(productOwnerAdmin(), 'admin')->delete(route('admin.products.destroy', $product))->assertSessionHas('error');

    expect(Product::find($product->id))->not->toBeNull();
});

test('a product whose services have all ended can be deleted', function () {
    $product = Product::factory()->create(['group_id' => ProductGroup::factory()->create()->id]);
    Service::factory()->create(['product_id' => $product->id, 'client_id' => Client::factory()->create()->id, 'status' => 'terminated']);

    $this->actingAs(productOwnerAdmin(), 'admin')->delete(route('admin.products.destroy', $product))->assertSessionHas('success');

    expect(Product::find($product->id))->toBeNull();
});
