<?php

use App\Models\Server;
use Illuminate\Support\Facades\Http;
use Modules\Servers\Plesk\PleskModule;

/**
 * listPackages asks the XML API (agent.php) for the service plans. That API
 * takes a secret key in the KEY header; the module sent X-API-Key, the REST
 * API's header, so a server set up with a key never listed its plans.
 */
function pleskKeyServer(array $attributes = []): Server
{
    return Server::factory()->create(array_merge([
        'type' => 'plesk', 'hostname' => 'plesk.test', 'ip_address' => '', 'port' => 8443,
        'username' => 'admin', 'password' => 'admin-secret', 'access_hash' => 'sk-123',
    ], $attributes));
}

it('sends the secret key to the XML API in the KEY header', function () {
    Http::fake(['*/enterprise/control/agent.php' => Http::response('<packet><service-plan><get/></service-plan></packet>', 200)]);

    (new PleskModule)->listPackages(pleskKeyServer());

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/enterprise/control/agent.php')
        && $request->hasHeader('KEY', 'sk-123')
        && ! $request->hasHeader('X-API-Key'));
});

it('still signs in with the login and password when there is no key', function () {
    Http::fake(['*/enterprise/control/agent.php' => Http::response('<packet><service-plan><get/></service-plan></packet>', 200)]);

    (new PleskModule)->listPackages(pleskKeyServer(['access_hash' => '']));

    Http::assertSent(fn ($request) => $request->hasHeader('HTTP_AUTH_LOGIN', 'admin')
        && $request->hasHeader('HTTP_AUTH_PASSWD', 'admin-secret'));
});
