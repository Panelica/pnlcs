<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;
use App\Models\Currency;
use App\Models\Pricing;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\User;

/*
 * Products and groups took Str::slug(name) as it came. A second product with
 * the same name shared the first one's slug, and the order page finds a product
 * by its slug: the second could not be ordered, and with the first hidden its
 * link was a 404. A second group with the same name broke on the unique index.
 */

function slugAdmin(): Admin
{
    return Admin::factory()->create(['role_id' => AdminRole::factory()->fullAdmin()->create()->id]);
}

function slugProduct(Admin $admin, string $name, ProductGroup $group): Product
{
    test()->actingAs($admin, 'admin')->post(route('admin.products.store'), [
        'name' => $name, 'group_id' => $group->id, 'type' => 'hosting', 'pay_type' => 'recurring',
    ]);

    return Product::where('name', $name)->latest('id')->firstOrFail();
}

function slugShopper(): User
{
    $user = User::factory()->create();
    $user->clients()->attach(Client::factory()->create()->id);

    return $user;
}

test('a second product with the same name gets a slug of its own', function () {
    $admin = slugAdmin();
    $group = ProductGroup::factory()->create();

    $first = slugProduct($admin, 'VPS S', $group);
    $second = slugProduct($admin, 'VPS S', $group);
    $third = slugProduct($admin, 'VPS S', $group);

    expect([$first->slug, $second->slug, $third->slug])->toBe(['vps-s', 'vps-s-2', 'vps-s-3']);
});

test('the second product opens on the order page while the first is hidden', function () {
    $currency = Currency::where('is_default', true)->first()
        ?? Currency::create(['code' => 'USD', 'prefix' => '$', 'suffix' => '', 'rate' => 1, 'is_default' => true]);
    $admin = slugAdmin();
    $group = ProductGroup::factory()->create(['hidden' => false]);

    $old = slugProduct($admin, 'VPS S', $group);
    $old->update(['hidden' => true]);
    $new = slugProduct($admin, 'VPS S', $group);
    Pricing::updateOrCreate(['type' => 'product', 'rel_id' => $new->id, 'currency_id' => $currency->id], ['monthly' => 9]);

    $this->actingAs(slugShopper())->get(route('client.store.configure', $new))
        ->assertOk()
        ->assertViewHas('product', fn (Product $p) => $p->id === $new->id);
});

test('a name a slug cannot spell still gets one', function () {
    $product = slugProduct(slugAdmin(), '专业版', ProductGroup::factory()->create());

    expect($product->slug)->toBe('product');
});

test('a second group with the same name is created with a slug of its own', function () {
    $admin = slugAdmin();
    foreach ([1, 2] as $i) {
        $this->actingAs($admin, 'admin')->post(route('admin.products.groups.store'), ['name' => 'Shared Hosting'])
            ->assertRedirect(route('admin.products.index'));
    }

    expect(ProductGroup::where('name', 'Shared Hosting')->orderBy('id')->pluck('slug')->all())
        ->toBe(['shared-hosting', 'shared-hosting-2']);
});

test('the migration gives each product that shared a slug its own, and the oldest keeps it', function () {
    $group = ProductGroup::factory()->create();
    $make = fn (string $name, ?string $slug) => Product::factory()->create(['group_id' => $group->id, 'name' => $name, 'slug' => $slug]);
    $a = $make('VPS S', 'vps-s');
    $b = $make('VPS S', 'vps-s');
    $c = $make('VPS S', 'vps-s');
    $d = $make('Starter', '');
    $e = $make('Other', 'other');

    (require database_path('migrations/2026_10_12_000001_give_products_with_a_shared_slug_their_own.php'))->up();

    expect([$a->fresh()->slug, $b->fresh()->slug, $c->fresh()->slug, $d->fresh()->slug, $e->fresh()->slug])
        ->toBe(['vps-s', 'vps-s-2', 'vps-s-3', 'starter', 'other']);
});
