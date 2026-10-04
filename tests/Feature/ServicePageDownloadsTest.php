<?php

use App\Models\Client;
use App\Models\Download;
use App\Models\DownloadCategory;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Service;
use App\Models\User;

/*
 * A product's files are on the page of the service that bought them.
 *
 * A download kept to a product's owners was only listed on the Downloads
 * page, among everything else; the service page of the product - where a
 * customer looks for what they bought - said nothing about it.
 */

function spdSetup(string $status = 'active'): array
{
    $user = User::factory()->create();
    $client = Client::factory()->create();
    $user->clients()->attach($client->id, ['owner' => true]);
    $product = Product::factory()->create(['group_id' => ProductGroup::factory()->create()->id]);
    $service = Service::factory()->create(['client_id' => $client->id, 'product_id' => $product->id, 'status' => $status, 'server_id' => null]);
    $category = DownloadCategory::create(['name' => 'Software']);

    $kept = Download::create(['category_id' => $category->id, 'title' => 'Theme Pro package', 'description' => 'Version 2.1', 'location' => 'https://example.test/theme.zip', 'hidden' => false]);
    $kept->products()->sync([$product->id]);
    $other = Download::create(['category_id' => $category->id, 'title' => 'Another product file', 'location' => 'https://example.test/other.zip', 'hidden' => false]);
    $other->products()->sync([Product::factory()->create(['group_id' => $product->group_id])->id]);
    Download::create(['category_id' => $category->id, 'title' => 'Open guide', 'location' => 'https://example.test/guide.pdf', 'hidden' => false]);
    $draft = Download::create(['category_id' => $category->id, 'title' => 'Unreleased build', 'location' => 'https://example.test/next.zip', 'hidden' => true]);
    $draft->products()->sync([$product->id]);

    return [$user, $service, $kept];
}

it('lists the product\'s own published files on its service page', function () {
    [$user, $service, $kept] = spdSetup();

    test()->actingAs($user)->get(route('client.services.show', $service))->assertOk()
        ->assertSee('Theme Pro package')->assertSee('Version 2.1')
        ->assertSee(route('client.downloads.download', $kept), false)
        ->assertDontSee('Another product file')->assertDontSee('Open guide')->assertDontSee('Unreleased build');
});

it('lists nothing while the service is not active', function () {
    [$user, $service] = spdSetup('suspended');

    test()->actingAs($user)->get(route('client.services.show', $service))->assertOk()->assertDontSee('Theme Pro package');
});
