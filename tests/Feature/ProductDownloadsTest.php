<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;
use App\Models\Download;
use App\Models\DownloadCategory;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Service;
use App\Models\User;

/*
 * A download can be kept to the customers who own a product.
 *
 * Every published download went to every signed-in customer: software sold as
 * a product could not be delivered through Downloads without giving it away.
 */

function pdCustomer(): array
{
    $user = User::factory()->create();
    $client = Client::factory()->create();
    $user->clients()->attach($client->id, ['owner' => true]);

    return [$user, $client];
}

function pdSetup(): array
{
    $product = Product::factory()->create(['group_id' => ProductGroup::factory()->create()->id, 'name' => 'Theme Pro']);
    $category = DownloadCategory::create(['name' => 'Software']);
    $open = Download::create(['category_id' => $category->id, 'title' => 'Open guide', 'location' => 'https://example.test/guide.pdf', 'hidden' => false]);
    $kept = Download::create(['category_id' => $category->id, 'title' => 'Theme Pro package', 'location' => 'https://example.test/theme-pro.zip', 'hidden' => false]);
    $kept->products()->sync([$product->id]);

    return [$product, $open, $kept];
}

it('shows and serves a product\'s download to its owner only', function () {
    [$product, $open, $kept] = pdSetup();
    [$owner, $ownerClient] = pdCustomer();
    Service::factory()->create(['client_id' => $ownerClient->id, 'product_id' => $product->id, 'status' => 'active']);
    [$stranger] = pdCustomer();

    test()->actingAs($owner)->get(route('client.downloads.index'))->assertOk()->assertSee('Theme Pro package')->assertSee('Open guide');
    test()->actingAs($owner)->get(route('client.downloads.download', $kept))->assertRedirect('https://example.test/theme-pro.zip');

    test()->actingAs($stranger)->get(route('client.downloads.index'))->assertOk()->assertDontSee('Theme Pro package')->assertSee('Open guide');
    test()->actingAs($stranger)->get(route('client.downloads.download', $kept))->assertNotFound();
    test()->actingAs($stranger)->get(route('client.downloads.download', $open))->assertRedirect('https://example.test/guide.pdf');

    expect($kept->fresh()->download_count)->toBe(1);
});

it('closes the download when the service is no longer active', function () {
    [$product, , $kept] = pdSetup();
    [$owner, $client] = pdCustomer();
    Service::factory()->create(['client_id' => $client->id, 'product_id' => $product->id, 'status' => 'terminated']);

    test()->actingAs($owner)->get(route('client.downloads.download', $kept))->assertNotFound();
});

it('is set from the downloads screen', function () {
    $product = Product::factory()->create(['group_id' => ProductGroup::factory()->create()->id, 'name' => 'Theme Pro']);
    $category = DownloadCategory::create(['name' => 'Software']);
    $admin = Admin::factory()->create(['role_id' => AdminRole::factory()->create(['name' => 'R'.uniqid(), 'permissions' => ['manage_kb']])->id]);

    test()->actingAs($admin, 'admin')->post(route('admin.config.downloads.store'), [
        'category_id' => $category->id, 'title' => 'Theme Pro package', 'location' => 'https://example.test/theme-pro.zip', 'published' => 1, 'products' => [$product->id],
    ])->assertSessionHas('success');

    expect(Download::where('title', 'Theme Pro package')->sole()->products->pluck('id')->all())->toBe([$product->id]);
    test()->actingAs($admin, 'admin')->get(route('admin.config.downloads'))->assertOk()->assertSee('name="products[]"', false)->assertSee('Theme Pro', false);
});
