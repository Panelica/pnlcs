<?php

use App\Models\Client;
use App\Models\Product;
use App\Models\Server;
use App\Models\Service;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Servers\Panelica\PanelicaModule;

/*
 * Every 403 from the panel on a new mailbox or database was reported to the
 * customer as "Your plan's mailbox limit has been reached." The panel answers
 * 403 for other reasons too - the API key lacks the scope, the account may not
 * use the feature - and a customer with room to spare was sent to upgrade. A
 * limit still reads as the translated message; anything else is shown as the
 * panel worded it, and logged.
 */

function prmService(): Service
{
    $server = Server::create([
        'name' => 'Panel', 'hostname' => 'panel.test', 'ip_address' => '10.0.0.9',
        'type' => 'panelica', 'username' => 'u', 'password' => 'pk', 'access_hash' => 'sk',
        'port' => 8443, 'active' => true,
    ]);

    return Service::factory()->create([
        'client_id' => Client::factory()->create()->id,
        'product_id' => Product::factory()->create(['server_type' => 'panelica'])->id,
        'server_id' => $server->id, 'status' => 'active', 'module_data' => ['panelica_user_id' => 'acct-1'],
    ]);
}

function prmRefuse(array $body): void
{
    Http::fake(function ($request) use ($body) {
        if (str_contains($request->url(), '/accounts/') && str_contains($request->url(), '/domains')) {
            return Http::response(['data' => [['id' => 'dom-own', 'domain_name' => 'example.com']]], 200);
        }

        return Http::response($body, 403);
    });
}

it('shows the panel\'s own reason when a 403 is not the plan\'s limit', function (string $what) {
    prmRefuse(['status' => 'error', 'error' => 'API key is missing the required scope: email:write']);
    Log::spy();
    $module = new PanelicaModule;

    $result = $what === 'mailbox'
        ? $module->createEmail(prmService(), 'dom-own', 'info', 'password123')
        : $module->createDatabase(prmService(), 'dom-own', 'shop', 'shopu', 'password123');

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toBe('API key is missing the required scope: email:write');
    Log::shouldHaveReceived('warning')->withArgs(fn ($m) => str_contains($m, 'refused a create'))->once();
})->with(['mailbox', 'database']);

it('keeps the translated limit message when the panel says it is the limit, or says nothing', function (array $body, string $what, string $key) {
    prmRefuse($body);
    app()->setLocale('tr');
    $module = new PanelicaModule;

    $result = $what === 'mailbox'
        ? $module->createEmail(prmService(), 'dom-own', 'info', 'password123')
        : $module->createDatabase(prmService(), 'dom-own', 'shop', 'shopu', 'password123');

    expect($result['message'])->toBe(__($key));
})->with([
    'mailbox limit' => [['status' => 'error', 'details' => 'Email account limit reached for this plan'], 'mailbox', 'panelica.mailbox_limit'],
    'database quota' => [['status' => 'error', 'message' => 'Database quota exceeded'], 'database', 'panelica.database_limit'],
    'nothing said' => [[], 'mailbox', 'panelica.mailbox_limit'],
]);
