<?php

use App\Models\Client;
use App\Models\Product;
use App\Models\Server;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Facades\Http;

/**
 * A suspended service is shown, not worked on.
 *
 * The VPS pages have always read a suspended machine and refused to change it.
 * The hosting side checked only that the service had not ended, so on a
 * service suspended for an unpaid invoice the customer could still sign in to
 * the control panel, create mailboxes, add cron jobs, upload files and press
 * the module's buttons: everything the suspension was meant to stop.
 */
function suspendedHostingService(string $status = 'suspended'): array
{
    $client = Client::factory()->create();
    $user = User::factory()->create();
    $user->clients()->attach($client->id);

    $server = Server::create([
        'name' => 'Panel', 'hostname' => 'panel.test', 'ip_address' => '10.0.0.7',
        'type' => 'panelica', 'username' => 'u', 'password' => 'pk', 'access_hash' => 'sk',
        'port' => 8443, 'active' => true,
    ]);
    $product = Product::factory()->create(['server_type' => 'panelica']);
    $service = Service::factory()->create([
        'client_id' => $client->id, 'product_id' => $product->id, 'server_id' => $server->id,
        'status' => $status, 'module_data' => ['panelica_user_id' => 'acct-1'],
    ]);

    return [$user, $service];
}

function fakeSuspendedPanel(): void
{
    Http::fake(function ($request) {
        $url = $request->url();
        if (str_contains($url, '/sso-login')) {
            return Http::response(['data' => ['url' => 'https://panel.test:8443/sso?token=xyz']], 200);
        }
        if (str_contains($url, '/accounts/') && str_contains($url, '/domains')) {
            return Http::response(['data' => [['id' => 'dom-own', 'domain_name' => 'example.com']]], 200);
        }
        if (preg_match('#/v1/accounts/[^/?]+$#', parse_url($url, PHP_URL_PATH) ?? '')) {
            return Http::response(['data' => ['id' => 'acct-1', 'plan_id' => 'plan-1']], 200);
        }
        if (str_contains($url, '/v1/plans')) {
            return Http::response(['data' => [['id' => 'plan-1', 'cron_jobs_enabled' => true, 'max_cron_jobs' => 5]]], 200);
        }
        if (str_contains($url, '/cron-jobs') && $request->method() === 'POST') {
            return Http::response(['status' => 'success', 'data' => ['id' => 'new']], 201);
        }

        return Http::response(['data' => []], 200);
    });
}

it('does not sign the customer in to the panel of a suspended service', function () {
    fakeSuspendedPanel();
    [$user, $service] = suspendedHostingService();

    $this->actingAs($user)
        ->from(route('client.services.show', $service))
        ->get(route('client.services.login', $service))
        ->assertRedirect(route('client.services.show', $service))
        ->assertSessionHas('error', __('client.services.suspended_for_action'));

    Http::assertNotSent(fn ($r) => str_contains($r->url(), '/sso-login'));
});

it('still signs the customer in to the panel of an active service', function () {
    fakeSuspendedPanel();
    [$user, $service] = suspendedHostingService('active');

    $this->actingAs($user)
        ->get(route('client.services.login', $service))
        ->assertRedirect('https://panel.test:8443/sso?token=xyz');
});

it('refuses a change on a hosting tab of a suspended service, and sends nothing to the panel', function () {
    fakeSuspendedPanel();
    [$user, $service] = suspendedHostingService();

    $this->actingAs($user)
        ->from(route('client.services.cron', $service))
        ->post(route('client.services.cron.store', $service), [
            'domain_id' => 'dom-own', 'task_name' => 'x', 'command' => 'php artisan x',
            'minute' => '0', 'hour' => '3', 'day_of_month' => '*', 'month' => '*', 'day_of_week' => '*',
        ])
        ->assertRedirect(route('client.services.cron', $service))
        ->assertSessionHas('error', __('client.services.suspended_for_action'));

    Http::assertNotSent(fn ($r) => $r->method() !== 'GET');
});

it('answers a script with a 409 when it changes a suspended service', function () {
    fakeSuspendedPanel();
    [$user, $service] = suspendedHostingService();

    $this->actingAs($user)
        ->postJson(route('client.services.cron.store', $service), ['domain_id' => 'dom-own', 'task_name' => 'x', 'command' => 'y'])
        ->assertStatus(409)
        ->assertJson(['success' => false, 'message' => __('client.services.suspended_for_action')]);
});

it('still shows a hosting tab of a suspended service', function () {
    fakeSuspendedPanel();
    [$user, $service] = suspendedHostingService();

    $this->actingAs($user)
        ->get(route('client.services.cron', $service))
        ->assertOk();
});

it('refuses the module buttons on a suspended service', function () {
    [$user, $service] = suspendedHostingService();

    $this->actingAs($user)
        ->from(route('client.services.show', $service))
        ->post(route('client.services.module-function', [$service, 'anything']))
        ->assertSessionHas('error', __('client.services.suspended_for_action'));
});

it('refuses the module buttons on a service that has ended', function () {
    [$user, $service] = suspendedHostingService('terminated');

    $this->actingAs($user)
        ->from(route('client.services.show', $service))
        ->post(route('client.services.module-function', [$service, 'anything']))
        ->assertSessionHas('error', __('client.services.not_live_for_action'));
});
