<?php

use App\Models\Server;
use Illuminate\Support\Facades\Http;
use Modules\Servers\DirectAdmin\DirectAdminModule;

/**
 * With wrong credentials DirectAdmin answers its API with the HTML login page
 * and a 200, so "Test connection" reported success for a server that every
 * provisioning call would then fail on.
 */
function daTestServer(): Server
{
    return Server::factory()->create([
        'type' => 'directadmin', 'hostname' => 'da.test', 'ip_address' => '', 'port' => 2222,
        'username' => 'admin', 'password' => 'wrong', 'access_hash' => '',
    ]);
}

it('fails the connection test when DirectAdmin answers with its login page', function () {
    Http::fake(['*' => Http::response('<!DOCTYPE html><html><body><form action="/CMD_LOGIN">Login</form></body></html>', 200)]);

    expect((new DirectAdminModule)->testConnection(daTestServer()))->toBeFalse();
});

it('passes the connection test when DirectAdmin answers the API', function () {
    Http::fake(['*' => Http::response('list[]=bob&list[]=alice', 200)]);

    expect((new DirectAdminModule)->testConnection(daTestServer()))->toBeTrue();
});
