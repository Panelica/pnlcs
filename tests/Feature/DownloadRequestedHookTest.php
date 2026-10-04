<?php

use App\Models\Client;
use App\Models\Download;
use App\Models\DownloadCategory;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

/*
 * An addon can act when a customer downloads: refuse the file (a licence
 * that lapsed) or hand over the customer's own copy (one stamped with their
 * licence). Nothing could: the controller served the file and no hook ran.
 */

function drhSetup(): array
{
    Storage::fake(Download::DISK);
    Storage::disk(Download::DISK)->put('downloads/x/plugin.zip', 'original');
    $user = User::factory()->create();
    $client = Client::factory()->create();
    $user->clients()->attach($client->id, ['owner' => true]);
    $download = Download::create(['category_id' => DownloadCategory::create(['name' => 'Software'])->id, 'title' => 'Plugin',
        'type' => 'file', 'location' => 'downloads/x/plugin.zip', 'hidden' => false]);

    return [$user, $client, $download];
}

it('runs DownloadRequested with the download and the customer, and changes nothing when it returns nothing', function () {
    [$user, $client, $download] = drhSetup();
    $seen = [];
    add_hook('DownloadRequested', function (array $vars) use (&$seen) {
        $seen[] = [$vars['download']->id, $vars['clientId']];
    });

    $response = test()->actingAs($user)->get(route('client.downloads.download', $download));

    $response->assertOk()->assertDownload('plugin.zip');
    expect($response->streamedContent())->toBe('original')->and($seen)->toBe([[$download->id, $client->id]]);
});

it('hands over the file an addon gives instead', function () {
    [$user, , $download] = drhSetup();
    $stamped = tempnam(sys_get_temp_dir(), 'dl');
    file_put_contents($stamped, 'stamped for this customer');
    add_hook('DownloadRequested', fn () => ['path' => $stamped, 'name' => 'plugin-licensed.zip']);

    $response = test()->actingAs($user)->get(route('client.downloads.download', $download));

    $response->assertOk()->assertDownload('plugin-licensed.zip');
    expect(file_get_contents($response->baseResponse->getFile()->getPathname()))->toBe('stamped for this customer')
        ->and($download->fresh()->download_count)->toBe(1);
});

it('refuses when an addon says so, without counting the download', function () {
    [$user, , $download] = drhSetup();
    add_hook('DownloadRequested', fn () => ['abort' => 'Your licence has expired.']);

    test()->actingAs($user)->get(route('client.downloads.download', $download))->assertForbidden();
    expect($download->fresh()->download_count)->toBe(0);
});
