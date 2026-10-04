<?php

use App\Models\ActivityLog;
use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;
use App\Models\Download;
use App\Models\DownloadCategory;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Service;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/*
 * A file sold as a product is kept private and handed over by the panel.
 *
 * A download was only ever a redirect to the address typed in for it, so the
 * file sat at a public address: whoever was given the link, or found it, had
 * the file, paid for or not. The form said "File URL or Upload" and took
 * multipart data, but nothing stored an upload; nor could a download or a
 * category be changed after it was made, and nothing recorded who downloaded
 * what.
 */

function dfAdmin(array $permissions = ['manage_kb']): Admin
{
    return Admin::factory()->create(['role_id' => AdminRole::factory()->create(['name' => 'R'.uniqid(), 'permissions' => $permissions])->id]);
}

function dfCustomer(?Product $owns = null): array
{
    $user = User::factory()->create();
    $client = Client::factory()->create();
    $user->clients()->attach($client->id, ['owner' => true]);
    if ($owns) {
        Service::factory()->create(['client_id' => $client->id, 'product_id' => $owns->id, 'status' => 'active']);
    }

    return [$user, $client];
}

beforeEach(function () {
    Storage::fake(Download::DISK);
});

it('stores an uploaded file privately and hands it only to the product\'s owners, through the panel', function () {
    $product = Product::factory()->create(['group_id' => ProductGroup::factory()->create()->id]);
    $category = DownloadCategory::create(['name' => 'Software']);

    test()->actingAs(dfAdmin(), 'admin')->post(route('admin.config.downloads.store'), [
        'category_id' => $category->id, 'title' => 'Theme Pro 2.1', 'published' => 1, 'products' => [$product->id],
        'file' => UploadedFile::fake()->createWithContent('theme pro 2.1.zip', 'PK-zip-bytes'),
    ])->assertSessionHas('success');

    $download = Download::where('title', 'Theme Pro 2.1')->firstOrFail();
    expect($download->type)->toBe('file')
        ->and($download->location)->toStartWith('downloads/')
        ->and($download->fileName())->toBe('theme-pro-2.1.zip');
    Storage::disk(Download::DISK)->assertExists($download->location);

    [$owner, $ownerClient] = dfCustomer($product);
    $response = test()->actingAs($owner)->get(route('client.downloads.download', $download));
    $response->assertOk()->assertDownload('theme-pro-2.1.zip');
    expect($response->streamedContent())->toBe('PK-zip-bytes')
        ->and($download->fresh()->download_count)->toBe(1)
        ->and(ActivityLog::where('client_id', $ownerClient->id)->where('description', 'like', '%Theme Pro 2.1%')->exists())->toBeTrue();

    [$stranger] = dfCustomer();
    test()->actingAs($stranger)->get(route('client.downloads.download', $download))->assertNotFound();
});

it('still sends a link to its address', function () {
    $category = DownloadCategory::create(['name' => 'Guides']);
    test()->actingAs(dfAdmin(), 'admin')->post(route('admin.config.downloads.store'), [
        'category_id' => $category->id, 'title' => 'Guide', 'published' => 1, 'location' => 'https://example.test/guide.pdf',
    ])->assertSessionHas('success');

    [$user] = dfCustomer();
    test()->actingAs($user)->get(route('client.downloads.download', Download::where('title', 'Guide')->firstOrFail()))
        ->assertRedirect('https://example.test/guide.pdf');
});

it('needs a file or a link', function () {
    $category = DownloadCategory::create(['name' => 'Empty']);
    test()->actingAs(dfAdmin(), 'admin')->post(route('admin.config.downloads.store'), ['category_id' => $category->id, 'title' => 'Nothing'])
        ->assertSessionHasErrors('location');
});

it('edits a download: replacing its file removes the old one, and a link replaces a file', function () {
    $category = DownloadCategory::create(['name' => 'Software']);
    $other = DownloadCategory::create(['name' => 'Archive']);
    $admin = dfAdmin();
    test()->actingAs($admin, 'admin')->post(route('admin.config.downloads.store'), [
        'category_id' => $category->id, 'title' => 'Plugin', 'published' => 1, 'file' => UploadedFile::fake()->createWithContent('plugin-1.0.zip', 'v1'),
    ]);
    $download = Download::where('title', 'Plugin')->firstOrFail();
    $first = $download->location;

    test()->actingAs($admin, 'admin')->get(route('admin.config.downloads.edit', $download))->assertOk()->assertSee('plugin-1.0.zip');
    test()->actingAs($admin, 'admin')->put(route('admin.config.downloads.update', $download), [
        'category_id' => $other->id, 'title' => 'Plugin 1.1', 'published' => 1, 'file' => UploadedFile::fake()->createWithContent('plugin-1.1.zip', 'v2'),
    ])->assertRedirect(route('admin.config.downloads'));

    $download->refresh();
    expect($download->title)->toBe('Plugin 1.1')->and($download->category_id)->toBe($other->id)->and($download->fileName())->toBe('plugin-1.1.zip');
    Storage::disk(Download::DISK)->assertMissing($first);
    Storage::disk(Download::DISK)->assertExists($download->location);

    $second = $download->location;
    test()->actingAs($admin, 'admin')->put(route('admin.config.downloads.update', $download), [
        'category_id' => $other->id, 'title' => 'Plugin 1.1', 'published' => 0, 'location' => 'https://example.test/plugin.zip',
    ]);
    expect($download->fresh())->type->toBe('link')->location->toBe('https://example.test/plugin.zip')->hidden->toBeTrue();
    Storage::disk(Download::DISK)->assertMissing($second);
});

it('renames a category, and deleting one deletes its files', function () {
    $category = DownloadCategory::create(['name' => 'Softwre']);
    $admin = dfAdmin();
    test()->actingAs(dfAdmin(['manage_announcements']), 'admin')->put(route('admin.config.downloads.categories.update', $category), ['name' => 'X'])->assertForbidden();
    test()->actingAs($admin, 'admin')->put(route('admin.config.downloads.categories.update', $category), ['name' => 'Software'])->assertSessionHas('success');
    expect($category->fresh()->name)->toBe('Software');

    test()->actingAs($admin, 'admin')->post(route('admin.config.downloads.store'), [
        'category_id' => $category->id, 'title' => 'Tool', 'published' => 1, 'file' => UploadedFile::fake()->createWithContent('tool.zip', 'x'),
    ]);
    $path = Download::where('title', 'Tool')->value('location');

    test()->actingAs($admin, 'admin')->delete(route('admin.config.downloads.categories.destroy', $category));
    Storage::disk(Download::DISK)->assertMissing($path);
});
