<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Currency;
use App\Models\Pricing;
use App\Models\Product;
use App\Models\ProductGroup;

/*
 * Copying a product from the list (#348): the duplicate carries the prices and
 * opens for editing, and it arrives hidden - replicate() copies `hidden` too,
 * so copying a product that is on sale would otherwise put "<name> - copy" in
 * the customer store at the same prices the moment the button is pressed.
 */

function copyAdmin(): Admin
{
    return Admin::factory()->create(['role_id' => AdminRole::factory()->fullAdmin()->create()->id]);
}

function copyProduct(array $attributes = []): Product
{
    $product = Product::create(array_merge([
        'name' => 'VPS Small',
        'slug' => 'vps-small',
        'group_id' => ProductGroup::factory()->create()->id,
        'type' => 'hosting',
        'pay_type' => 'recurring',
        'hidden' => false,
        'retired' => false,
    ], $attributes));

    // One pricing row per product per currency carries every cycle: the table
    // has a unique index on (type, currency_id, rel_id).
    $currency = Currency::getDefault() ?: Currency::factory()->default()->create();
    Pricing::create([
        'type' => 'product', 'rel_id' => $product->id, 'currency_id' => $currency->id,
        'monthly' => 10.0, 'annually' => 100.0,
    ]);

    return $product;
}

it('copies a product with its prices and opens the duplicate', function () {
    $product = copyProduct();

    $this->actingAs(copyAdmin(), 'admin')
        ->post(route('admin.products.copy', $product))
        ->assertRedirect()
        ->assertSessionHas('success');

    $copy = Product::where('name', 'VPS Small - copy')->firstOrFail();

    expect($copy->id)->not->toBe($product->id)
        ->and($copy->slug)->not->toBe($product->slug)
        ->and($copy->type)->toBe($product->type)
        ->and($copy->group_id)->toBe($product->group_id);

    $prices = Pricing::where('type', 'product')->where('rel_id', $copy->id)->get();
    expect($prices)->toHaveCount(1)
        ->and((float) $prices->first()->monthly)->toBe(10.0)
        ->and((float) $prices->first()->annually)->toBe(100.0);
});

it('leaves the duplicate out of the customer store until it is published', function () {
    $product = copyProduct(['hidden' => false]);

    $this->actingAs(copyAdmin(), 'admin')->post(route('admin.products.copy', $product));

    $copy = Product::where('name', 'VPS Small - copy')->firstOrFail();

    expect($copy->hidden)->toBeTrue()
        ->and(Product::active()->pluck('name')->all())->toContain('VPS Small')
        ->and(Product::active()->pluck('name')->all())->not->toContain('VPS Small - copy');
});

it('gives a second copy its own slug', function () {
    $product = copyProduct();
    $admin = copyAdmin();

    $this->actingAs($admin, 'admin')->post(route('admin.products.copy', $product));
    $this->actingAs($admin, 'admin')->post(route('admin.products.copy', $product));

    $slugs = Product::where('name', 'VPS Small - copy')->pluck('slug');

    expect($slugs)->toHaveCount(2)->and($slugs->unique())->toHaveCount(2);
});
