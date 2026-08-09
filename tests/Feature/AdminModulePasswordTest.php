<?php

use App\Models\Admin;
use App\Models\Client;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Server;
use App\Models\Service;
use Illuminate\Support\Facades\Http;

/**
 * The admin screen sending the control panel whatever password arrived.
 *
 * The change-password box on a service is guarded by the browser alone -
 * required, minlength 6 - and the controller then reads it with a default of ''
 * and hands it to the module. Anything that is not a browser obeying those
 * attributes reaches the panel unchecked, and when the module reports success
 * the service row is overwritten with what was sent: the account's password on
 * the hosting panel and the copy this application keeps both become an empty
 * string, and nothing here refused it.
 *
 * The API door for the same operation, in ServiceApiController, validates min:6
 * and refuses an empty password outright. Only this door does not.
 */
function serviceForPasswordChange(): Service
{
    $server = Server::factory()->create(['type' => 'cpanel', 'hostname' => 'cp.test', 'access_hash' => 'secret-key']);
    $group = ProductGroup::factory()->create();
    $product = Product::factory()->create(['group_id' => $group->id, 'server_type' => 'cpanel']);

    return Service::factory()->create([
        'client_id' => Client::factory()->create()->id,
        'product_id' => $product->id,
        'server_id' => $server->id,
        'domain' => 'password-door.test',
        'status' => 'active',
        'username' => 'pwuser',
        'password' => 'the-old-one',
    ]);
}

function changeServicePassword(Service $service, array $payload)
{
    return test()->actingAs(Admin::factory()->create(), 'admin')
        ->post(route('admin.services.module-action', [$service, 'changepassword']), $payload);
}

it('refuses an empty password instead of sending it to the panel', function () {
    Http::fake(['*' => Http::response(['metadata' => ['result' => 1, 'reason' => 'OK']], 200)]);
    $service = serviceForPasswordChange();

    changeServicePassword($service, ['password' => ''])->assertSessionHasErrors('password');

    Http::assertNothingSent();
    expect($service->fresh()->password)->toBe('the-old-one');
});

it('refuses a password shorter than the box asks for', function () {
    Http::fake(['*' => Http::response(['metadata' => ['result' => 1, 'reason' => 'OK']], 200)]);
    $service = serviceForPasswordChange();

    changeServicePassword($service, ['password' => 'abc'])->assertSessionHasErrors('password');

    Http::assertNothingSent();
    expect($service->fresh()->password)->toBe('the-old-one');
});

it('refuses a request that carries no password field at all', function () {
    Http::fake(['*' => Http::response(['metadata' => ['result' => 1, 'reason' => 'OK']], 200)]);
    $service = serviceForPasswordChange();

    changeServicePassword($service, [])->assertSessionHasErrors('password');

    Http::assertNothingSent();
});

it('still changes the password when a proper one is given', function () {
    Http::fake(['*' => Http::response(['metadata' => ['result' => 1, 'reason' => 'OK']], 200)]);
    $service = serviceForPasswordChange();

    changeServicePassword($service, ['password' => 'a-decent-one'])->assertSessionHas('success');

    expect($service->fresh()->password)->toBe('a-decent-one');
});

it('still runs an action that takes no password', function () {
    Http::fake(['*' => Http::response(['metadata' => ['result' => 1, 'reason' => 'OK']], 200)]);
    $service = serviceForPasswordChange();

    test()->actingAs(Admin::factory()->create(), 'admin')
        ->post(route('admin.services.module-action', [$service, 'suspend']), ['reason' => 'unpaid'])
        ->assertRedirect();

    expect($service->fresh()->status)->toBe('suspended');
});
