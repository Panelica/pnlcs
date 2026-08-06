<?php

use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Addons\ProjectManagement\ProjectManagementModule;

/**
 * What an addon screen does with what customers typed.
 *
 * Addons build their own HTML and the addon page prints it unescaped, the same
 * arrangement the dashboard widgets have. The projects addon joins the client
 * table and puts the customer's name, and the project title, straight into a
 * table row.
 *
 * A customer called <script> is not a naming curiosity there: it is script
 * running in the operator's browser when they open the projects screen.
 */
function projectFor(Client $client, string $title): void
{
    DB::table('projects')->insert([
        'client_id' => $client->id,
        'admin_id' => null,
        'title' => $title,
        'description' => 'x',
        'status' => 'Open',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

it('does not run a customer name as markup on the projects screen', function () {
    $client = Client::factory()->create([
        'first_name' => '<script>alert(1)</script>',
        'last_name' => 'Smith',
    ]);

    projectFor($client, 'Ordinary project');

    $html = (new ProjectManagementModule)->output(Request::create('/'));

    expect($html)->not->toContain('<script>alert(1)</script>')
        ->and($html)->toContain('alert(1)');
});

it('does not run a project title as markup', function () {
    $client = Client::factory()->create(['first_name' => 'Plain', 'last_name' => 'Customer']);

    projectFor($client, '<img src=x onerror=alert(2)>');

    $html = (new ProjectManagementModule)->output(Request::create('/'));

    expect($html)->not->toContain('<img src=x onerror=alert(2)>');
});
