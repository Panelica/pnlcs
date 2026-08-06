<?php

use App\Models\Admin;
use App\Models\Client;

/**
 * What a spreadsheet does with an exported cell.
 *
 * Both exports write what is in the database straight into the file. A cell
 * that begins with an equals sign, a plus, a minus or an at sign is not text to
 * Excel, Numbers or LibreOffice - it is a formula, and it runs when the
 * operator opens the file they just downloaded.
 *
 * The fields in those files are the ones customers fill in themselves: their
 * name, their company, their address. Nothing stops a customer typing a
 * formula into the box, and the person who opens the export is the operator.
 */
function exportingAdmin(): Admin
{
    return Admin::factory()->create();
}

function downloadedCsv(string $url): string
{
    $response = test()->actingAs(exportingAdmin(), 'admin')->get($url);

    $response->assertOk();

    ob_start();
    $response->sendContent();

    return ob_get_clean();
}

it('does not let a customer put a formula in the customer export', function () {
    Client::factory()->create([
        'first_name' => '=1+1',
        'last_name' => '+HYPERLINK("http://example.com")',
        'company_name' => '@SUM(A1:A9)',
        'email' => 'formula@test.local',
    ]);

    $csv = downloadedCsv(route('admin.clients.export'));

    expect($csv)->toContain('formula@test.local');

    foreach (["\n=1+1", ',=1+1', "\n+HYPER", ',+HYPER', ',@SUM', "\n@SUM"] as $dangerous) {
        expect($csv)->not->toContain($dangerous);
    }
});

it('keeps the value readable', function () {
    Client::factory()->create([
        'first_name' => '=1+1',
        'email' => 'readable@test.local',
    ]);

    $csv = downloadedCsv(route('admin.clients.export'));

    expect($csv)->toContain('1+1');
});
