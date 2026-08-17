<?php

use App\Models\Client;
use App\Models\DockerApp;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Server;
use App\Models\User;
use Illuminate\Support\Facades\Http;

/*
 * Ordering an app.
 *
 * Ninety-eight apps cannot be ninety-eight products, so one product asks the
 * customer which app they want and the order installs it. These pin the whole
 * chain: what the order form offers, what the request is allowed to claim, and
 * that the choice survives all the way to provisioning.
 */

function appOrderServer(): Server
{
    return Server::create([
        'name' => 'Panel', 'hostname' => 'panel.order.test', 'ip_address' => '10.0.0.14',
        'type' => 'panelica', 'username' => 'u', 'password' => 'pk', 'access_hash' => 'sk',
        'port' => 8443, 'active' => true,
    ]);
}

function appOrderProduct(bool $choose = true): Product
{
    $product = Product::factory()->create([
        'group_id' => ProductGroup::factory()->create()->id,
        'server_type' => 'panelica',
        'hidden' => false,
        'retired' => false,
        'config_options' => json_encode($choose ? ['panelica_app_choose' => 1, 'res_max_containers' => 1] : ['res_max_containers' => 1]),
    ]);

    // A product with no price cannot be added to a cart at all, which would
    // fail these tests for a reason that has nothing to do with apps.
    App\Models\Pricing::create([
        'type' => 'product',
        'currency_id' => App\Models\Currency::getDefault()?->id ?? App\Models\Currency::factory()->create()->id,
        'rel_id' => $product->id,
        'monthly' => 10,
    ]);

    return $product;
}

function appOrderUser(): User
{
    $user = User::factory()->create();
    $user->clients()->attach(Client::factory()->create()->id);

    return $user;
}

function fakeAppCatalogue(array $apps): void
{
    Http::fake(function ($request) use ($apps) {
        if (str_contains($request->url(), '/v1/docker/templates')) {
            return Http::response(['data' => ['templates' => $apps]], 200);
        }

        return Http::response(['data' => []], 200);
    });
}

$CATALOGUE = [
    ['slug' => 'wordpress', 'name' => 'WordPress', 'description' => 'Blog', 'logo_url' => '', 'categories' => ['cms']],
    ['slug' => 'n8n', 'name' => 'n8n', 'description' => 'Automation', 'logo_url' => '', 'categories' => ['automation']],
];

it('shows the catalogue on the order form for a pick-your-app product', function () use ($CATALOGUE) {
    appOrderServer();
    fakeAppCatalogue($CATALOGUE);
    $product = appOrderProduct();

    $this->actingAs(appOrderUser())
        ->get(route('client.store.configure', $product))
        ->assertOk()
        ->assertSee('WordPress')
        ->assertSee('data-slug="n8n"', false);
});

it('leaves every other product alone', function () use ($CATALOGUE) {
    appOrderServer();
    fakeAppCatalogue($CATALOGUE);
    $product = appOrderProduct(choose: false);

    $page = $this->actingAs(appOrderUser())->get(route('client.store.configure', $product))->assertOk();

    // No catalogue, and no call to fetch one: an ordinary hosting product pays
    // nothing for this feature.
    expect($page->viewData('apps'))->toBe([]);
    Http::assertNotSent(fn ($rq) => str_contains($rq->url(), '/docker/templates'));
});

it('hides an app the operator took off the shelf', function () use ($CATALOGUE) {
    appOrderServer();
    fakeAppCatalogue($CATALOGUE);
    DockerApp::create(['slug' => 'n8n', 'is_sellable' => false]);

    $page = $this->actingAs(appOrderUser())->get(route('client.store.configure', appOrderProduct()))->assertOk();

    expect(collect($page->viewData('apps'))->pluck('slug')->all())->toBe(['wordpress']);
});

it('carries the chosen app into the cart', function () use ($CATALOGUE) {
    appOrderServer();
    fakeAppCatalogue($CATALOGUE);
    $product = appOrderProduct();

    $this->actingAs(appOrderUser())->post(route('client.cart.add'), [
        'product_id' => $product->id,
        'billing_cycle' => 'monthly',
        'app_slug' => 'wordpress',
    ])->assertRedirect(route('client.cart.index'));

    // The cart keeps its contents as a JSON string, not a cast array.
    $data = json_decode(App\Models\Cart::latest()->firstOrFail()->data, true) ?: [];
    expect($data['items'][0]['app_slug'] ?? null)->toBe('wordpress');
});

it('takes an order with no app at all', function () use ($CATALOGUE) {
    appOrderServer();
    fakeAppCatalogue($CATALOGUE);

    // The hosting is the product. Someone who has not decided what to run yet
    // is still buying something, and installs from the panel afterwards.
    $this->actingAs(appOrderUser())->post(route('client.cart.add'), [
        'product_id' => appOrderProduct()->id,
        'billing_cycle' => 'monthly',
    ])->assertRedirect(route('client.cart.index'))->assertSessionHasNoErrors();

    $data = json_decode(App\Models\Cart::latest()->firstOrFail()->data, true) ?: [];
    expect($data['items'][0])->toHaveKey('app_slug')
        ->and($data['items'][0]['app_slug'])->toBeNull();
});

it('opens a plain hosting account when no app was chosen', function () {
    $server = appOrderServer();
    Http::fake(function ($request) {
        $url = $request->url();
        if (str_contains($url, '/v1/domains')) {
            return Http::response(['data' => ['id' => 'dom-1']], 200);
        }
        if (str_contains($url, '/v1/accounts')) {
            return Http::response(['data' => ['id' => 'acct-1']], 200);
        }

        return Http::response(['data' => []], 200);
    });

    $product = Product::factory()->create([
        'group_id' => ProductGroup::factory()->create()->id,
        'server_type' => 'panelica',
        'config_options' => json_encode(['panelica_app_choose' => 1, 'res_max_containers' => 3]),
    ]);
    $service = App\Models\Service::factory()->create([
        'client_id' => Client::factory()->create()->id,
        'product_id' => $product->id, 'server_id' => $server->id,
        'domain' => 'plain.test', 'status' => 'pending',
    ]);

    expect(app(Modules\Servers\Panelica\PanelicaModule::class)->create($service)['success'])->toBeTrue();
    Http::assertNotSent(fn ($rq) => str_contains($rq->url(), '/deploy'));
    expect($service->fresh()->status)->toBe('active');
});

it('refuses an app that is not on the shelf', function () use ($CATALOGUE) {
    appOrderServer();
    fakeAppCatalogue($CATALOGUE);
    DockerApp::create(['slug' => 'n8n', 'is_sellable' => false]);

    // The form is built from what we offer, but the request is not the form.
    $this->actingAs(appOrderUser())->post(route('client.cart.add'), [
        'product_id' => appOrderProduct()->id,
        'billing_cycle' => 'monthly',
        'app_slug' => 'n8n',
    ])->assertSessionHasErrors('app_slug');
});

it('installs what the customer chose, not the product default', function () {
    $server = appOrderServer();
    Http::fake(function ($request) {
        $url = $request->url();
        if (str_contains($url, '/deploy')) {
            return Http::response(['data' => ['container_id' => 'ctr-1']], 200);
        }
        if (str_contains($url, '/docker/domains/link')) {
            return Http::response(['data' => []], 200);
        }
        if (str_contains($url, '/v1/domains')) {
            return Http::response(['data' => ['id' => 'dom-1']], 200);
        }
        if (str_contains($url, '/v1/accounts')) {
            return Http::response(['data' => ['id' => 'acct-1']], 200);
        }

        return Http::response(['data' => []], 200);
    });

    // The product names a fixed app; the customer chose another while ordering.
    $product = Product::factory()->create([
        'group_id' => ProductGroup::factory()->create()->id,
        'server_type' => 'panelica',
        'config_options' => json_encode(['panelica_app_template' => 'wordpress', 'res_max_containers' => 1]),
    ]);
    $service = App\Models\Service::factory()->create([
        'client_id' => Client::factory()->create()->id,
        'product_id' => $product->id,
        'server_id' => $server->id,
        'domain' => 'chosen.test',
        'status' => 'pending',
        'module_data' => ['panelica_app_template' => 'n8n'],
    ]);

    expect(app(Modules\Servers\Panelica\PanelicaModule::class)->create($service)['success'])->toBeTrue();

    Http::assertSent(fn ($rq) => str_contains($rq->url(), '/docker/templates/n8n/deploy'));
    Http::assertNotSent(fn ($rq) => str_contains($rq->url(), '/docker/templates/wordpress/deploy'));
});

/*
 * What a plan contains, on the card.
 *
 * The store used to show a name and a price and nothing else, so the customer
 * was choosing between plans without being told what separated them. The
 * figures are read from the product's own limits rather than typed into a
 * feature list, so they cannot drift from what the panel enforces.
 */

it('states the plan resources in units a customer understands', function () {
    $product = Product::factory()->create([
        'group_id' => ProductGroup::factory()->create()->id,
        'server_type' => 'panelica',
        'config_options' => json_encode([
            'res_memory_mb' => 4096, 'res_cpu_percent' => 200, 'res_disk_mb' => 51200,
            'res_max_containers' => 3, 'res_max_domains' => 1,
        ]),
    ]);

    $text = collect($product->resourceSummary())->pluck('text')->implode(' | ');

    // Cores, not percentages: "200%" reads like an error to anyone who has not
    // seen a cgroup.
    expect($text)->toContain('4 GB RAM')->toContain('2 vCPU')
        ->toContain('50 GB')->toContain('3 apps')->toContain('1 website');
});

it('says nothing rather than zeroes for a product with no limits set', function () {
    $product = Product::factory()->create([
        'group_id' => ProductGroup::factory()->create()->id,
        'config_options' => json_encode([]),
    ]);

    expect($product->resourceSummary())->toBe([]);
});

it('shows the resources on the order form', function () use ($CATALOGUE) {
    appOrderServer();
    fakeAppCatalogue($CATALOGUE);
    $product = appOrderProduct();
    $product->update(['config_options' => json_encode([
        'panelica_app_choose' => 1, 'res_max_containers' => 2, 'res_memory_mb' => 2048,
    ])]);

    $this->actingAs(appOrderUser())
        ->get(route('client.store.configure', $product))
        ->assertOk()
        ->assertSee('2 GB RAM')
        ->assertSee('2 apps');
});

it('carries the chosen app all the way from the cart to the service', function () use ($CATALOGUE) {
    appOrderServer();
    fakeAppCatalogue($CATALOGUE);
    $product = appOrderProduct();
    $user = appOrderUser();

    $this->actingAs($user)->post(route('client.cart.add'), [
        'product_id' => $product->id,
        'billing_cycle' => 'monthly',
        'app_slug' => 'wordpress',
        'domain' => 'chainsite.test',
        'domain_option' => 'own',
    ])->assertRedirect();

    $this->actingAs($user)->post(route('client.cart.checkout'), [
        'payment_method' => 'banktransfer',
        'terms' => '1',
    ])->assertRedirect();

    // The gap this closes: the cart recorded the choice and the service knew
    // how to use it, but the step in between rebuilt the line item and left the
    // app behind - so an order placed for WordPress provisioned an empty
    // account, silently.
    $service = App\Models\Service::latest('id')->firstOrFail();
    $data = is_string($service->module_data)
        ? (json_decode($service->module_data, true) ?: [])
        : ((array) $service->module_data);

    expect($service->domain)->toBe('chainsite.test')
        ->and($data['panelica_app_template'] ?? null)->toBe('wordpress');
});

it('names the container when nobody supplied one', function () use ($CATALOGUE) {
    $server = appOrderServer();
    Http::fake(function ($request) {
        $url = $request->url();
        if (str_contains($url, '/deploy')) {
            return Http::response(['data' => ['container_id' => 'ctr-1']], 200);
        }
        if (str_contains($url, '/docker/domains/link')) {
            return Http::response(['data' => []], 200);
        }
        if (str_contains($url, '/v1/domains')) {
            return Http::response(['data' => ['id' => 'dom-1']], 200);
        }
        if (str_contains($url, '/v1/accounts')) {
            return Http::response(['data' => ['id' => 'acct-1']], 200);
        }

        return Http::response(['data' => []], 200);
    });

    $product = Product::factory()->create([
        'group_id' => ProductGroup::factory()->create()->id,
        'server_type' => 'panelica',
        'config_options' => json_encode(['panelica_app_template' => 'caddy', 'res_max_containers' => 1]),
    ]);
    $service = App\Models\Service::factory()->create([
        'client_id' => Client::factory()->create()->id,
        'product_id' => $product->id, 'server_id' => $server->id,
        'domain' => 'named.test', 'status' => 'pending',
    ]);

    expect(app(Modules\Servers\Panelica\PanelicaModule::class)->create($service)['success'])->toBeTrue();

    // The panel validates container_name as required; an order that did not
    // send one failed and rolled the whole account back.
    Http::assertSent(fn ($rq) => str_contains($rq->url(), '/templates/caddy/deploy')
        && ($rq->data()['container_name'] ?? null) === 'caddy');
});
