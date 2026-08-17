<?php

use App\Models\Client;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Server;
use App\Models\Service;
use Illuminate\Support\Facades\Http;
use Modules\Servers\Panelica\PanelicaModule;

/*
 * App Hosting: a product that sells one app rather than an empty account.
 *
 * The order has to deliver three things together - the account, the app, and
 * the domain pointing at it. Any one of them missing is a service the customer
 * paid for and cannot use, so a half-finished install must roll back rather
 * than report success. These pin that, plus the ownership argument the panel
 * needs to refuse a cross-account link.
 */

function appServer(): Server
{
    return Server::create([
        'name' => 'Panel', 'hostname' => 'panel.app.test', 'ip_address' => '10.0.0.11',
        'type' => 'panelica', 'username' => 'u', 'password' => 'pk', 'access_hash' => 'sk',
        'port' => 8443, 'active' => true,
    ]);
}

function appService(Server $server, ?string $slug): Service
{
    $config = ['res_max_containers' => 2];
    if ($slug !== null) {
        $config['panelica_app_template'] = $slug;
    }
    $product = Product::factory()->create([
        'group_id' => ProductGroup::factory()->create()->id,
        'server_type' => 'panelica',
        'config_options' => json_encode($config),
    ]);

    return Service::factory()->create([
        'client_id' => Client::factory()->create()->id,
        'product_id' => $product->id,
        'server_id' => $server->id,
        'domain' => 'appsite.test',
        'status' => 'pending',
    ]);
}

/** @param string $fail '' | 'deploy' | 'link' */
function fakeAppPanel(string $fail = '', array $templates = []): void
{
    Http::fake(function ($request) use ($fail, $templates) {
        $url = $request->url();
        $m = $request->method();
        if (str_contains($url, '/docker/templates') && $m === 'GET') {
            return Http::response(['data' => ['templates' => $templates]], 200);
        }
        if (str_contains($url, '/deploy')) {
            return $fail === 'deploy'
                ? Http::response(['message' => 'image pull failed'], 500)
                : Http::response(['data' => ['container_id' => 'ctr-1']], 200);
        }
        if (str_contains($url, '/docker/domains/link')) {
            return $fail === 'link'
                ? Http::response(['message' => 'container not running'], 400)
                : Http::response(['data' => ['domain' => 'appsite.test']], 200);
        }
        if (str_contains($url, '/docker/containers/')) {
            return Http::response(['status' => 'success'], 200);
        }
        if (str_contains($url, '/v1/domains')) {
            return Http::response(['data' => ['id' => 'dom-1']], 200);
        }
        if (str_contains($url, '/v1/accounts')) {
            return Http::response(['data' => ['id' => 'acct-1']], 200);
        }

        return Http::response(['data' => []], 200);
    });
}

it('leaves regular hosting products alone', function () {
    fakeAppPanel();
    $service = appService(appServer(), null);

    expect(app(PanelicaModule::class)->create($service)['success'])->toBeTrue();
    Http::assertNotSent(fn ($rq) => str_contains($rq->url(), '/deploy'));
    Http::assertNotSent(fn ($rq) => str_contains($rq->url(), '/domains/link'));
});

it('installs the product app and serves it on the domain', function () {
    fakeAppPanel();
    $service = appService(appServer(), 'wordpress');

    $result = app(PanelicaModule::class)->create($service);
    expect($result['success'])->toBeTrue();

    // Installed as the account that was just created, not as the operator.
    Http::assertSent(fn ($rq) => str_contains($rq->url(), '/docker/templates/wordpress/deploy')
        && ($rq->data()['owner_user_id'] ?? null) === 'acct-1');

    // The panel refuses a cross-account link only if it is told the account.
    Http::assertSent(fn ($rq) => str_contains($rq->url(), '/docker/domains/link')
        && ($rq->data()['domain_id'] ?? null) === 'dom-1'
        && ($rq->data()['container_id'] ?? null) === 'ctr-1'
        && ($rq->data()['owner_user_id'] ?? null) === 'acct-1');
});

it('records the app on the service so the panel tab and terminate can find it', function () {
    fakeAppPanel();
    $service = appService(appServer(), 'n8n');
    app(PanelicaModule::class)->create($service);

    $data = $service->fresh()->module_data;
    $data = is_string($data) ? json_decode($data, true) : (array) $data;
    expect($data['panelica_app_container_id'])->toBe('ctr-1')
        ->and($data['panelica_app_template'])->toBe('n8n')
        ->and($data['panelica_user_id'])->toBe('acct-1');
});

it('rolls the account back when the app cannot be installed', function () {
    fakeAppPanel('deploy');
    $service = appService(appServer(), 'wordpress');

    $result = app(PanelicaModule::class)->create($service);
    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('rolled back');

    // No half-built service left behind: the account is deleted and the
    // service is not marked active.
    Http::assertSent(fn ($rq) => $rq->method() === 'DELETE' && str_contains($rq->url(), '/v1/accounts/acct-1'));
    expect($service->fresh()->status)->not->toBe('active');
});

it('takes the container down when the domain cannot be pointed at it', function () {
    fakeAppPanel('link');
    $service = appService(appServer(), 'wordpress');

    expect(app(PanelicaModule::class)->create($service)['success'])->toBeFalse();

    // An app nobody can reach is not the product that was sold; it goes with
    // the account rather than being left running and billed for.
    Http::assertSent(fn ($rq) => $rq->method() === 'DELETE' && str_contains($rq->url(), '/docker/containers/ctr-1'));
    Http::assertSent(fn ($rq) => $rq->method() === 'DELETE' && str_contains($rq->url(), '/v1/accounts/acct-1'));
});

it('retries the link while the container is still starting', function () {
    $calls = 0;
    Http::fake(function ($request) use (&$calls) {
        $url = $request->url();
        if (str_contains($url, '/deploy')) {
            return Http::response(['data' => ['container_id' => 'ctr-1']], 200);
        }
        if (str_contains($url, '/docker/domains/link')) {
            $calls++;

            return $calls === 1
                ? Http::response(['message' => 'container not running'], 400)
                : Http::response(['data' => ['domain' => 'appsite.test']], 200);
        }
        if (str_contains($url, '/v1/domains')) {
            return Http::response(['data' => ['id' => 'dom-1']], 200);
        }
        if (str_contains($url, '/v1/accounts')) {
            return Http::response(['data' => ['id' => 'acct-1']], 200);
        }

        return Http::response(['data' => []], 200);
    });

    $service = appService(appServer(), 'wordpress');
    expect(app(PanelicaModule::class)->create($service)['success'])->toBeTrue()
        ->and($calls)->toBe(2);
});

/*
 * Container plans sell a pool of container resources rather than a website, so
 * they are the one product that may be ordered without a domain.
 */

function containerPlanService(Server $server, bool $withDomain = false): Service
{
    $product = Product::factory()->create([
        'group_id' => ProductGroup::factory()->create()->id,
        'server_type' => 'panelica',
        'config_options' => json_encode(['panelica_container_plan' => 1, 'res_max_containers' => 3]),
    ]);

    return Service::factory()->create([
        'client_id' => Client::factory()->create()->id,
        'product_id' => $product->id,
        'server_id' => $server->id,
        'domain' => $withDomain ? 'poolsite.test' : null,
        'status' => 'pending',
        'module_data' => ['panelica_user_id' => 'acct-1'],
    ]);
}

it('provisions a container plan without a domain', function () {
    fakeAppPanel();
    $service = containerPlanService(appServer());

    expect(app(PanelicaModule::class)->create($service)['success'])->toBeTrue();
    Http::assertSent(fn ($rq) => $rq->method() === 'POST' && str_contains($rq->url(), '/v1/accounts'));
    Http::assertNotSent(fn ($rq) => $rq->method() === 'POST' && str_contains($rq->url(), '/v1/domains'));
    expect($service->fresh()->status)->toBe('active');
});

it('still refuses a domainless order for a normal hosting product', function () {
    fakeAppPanel();
    $service = appService(appServer(), null);
    $service->update(['domain' => null]);

    // A missing domain here is an order that would open an account nobody can
    // use, so it fails loudly instead of provisioning something half-formed.
    expect(app(PanelicaModule::class)->create($service)['success'])->toBeFalse();
    Http::assertNotSent(fn ($rq) => $rq->method() === 'POST' && str_contains($rq->url(), '/v1/accounts'));
});

it('shows only the apps tab for a container plan', function () {
    fakeAppPanel();
    expect(app(PanelicaModule::class)->hostingFeatures(containerPlanService(appServer())))
        ->toBe(['containers']);
});

it('keeps the full toolset for regular hosting', function () {
    fakeAppPanel();
    $service = appService(appServer(), null);
    $service->update(['module_data' => ['panelica_user_id' => 'acct-1']]);

    expect(app(PanelicaModule::class)->hostingFeatures($service))
        ->toContain('emails')->toContain('containers');
});

it('lists the whole catalogue for the product form', function () {
    fakeAppPanel('', [
        ['slug' => 'n8n', 'name' => 'n8n'],
        ['slug' => 'wordpress', 'name' => 'WordPress'],
        ['slug' => '', 'name' => 'broken'],
    ]);

    $list = app(PanelicaModule::class)->appTemplates(appServer());

    // Sorted by name and free of entries the form could not submit.
    expect($list)->toHaveCount(2)
        ->and($list[0]['slug'])->toBe('n8n')
        ->and($list[1]['slug'])->toBe('wordpress');

    // The admin picks from everything the server offers, so no account is named.
    Http::assertSent(fn ($rq) => str_contains($rq->url(), '/docker/templates')
        && ! str_contains($rq->url(), 'owner_user_id'));
});
