<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;
use App\Models\Currency;
use App\Models\Pricing;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Server;
use App\Models\Service;
use App\Models\Upgrade;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/*
 * #276: a customer with three apps moved themselves onto a two-app package,
 * and the move went through. The panel applies a plan's limits when something
 * is created, not to what exists, so the account kept all three apps on the
 * smaller package. A customer moving themselves is now told what to remove
 * first; staff can still change the product on the service's admin page.
 */

/** A panel answering from what the test sets; every request it gets is recorded. */
function fitPanel(array $state = []): object
{
    $panel = (object) array_merge([
        'plans' => [],
        'apps' => 3,
        'stats' => ['domain_count' => 1, 'email_count' => 0, 'ftp_count' => 0],
        'domains' => [['id' => 'dom-1', 'domain_name' => 'shop.test']],
        'subdomains' => [],
        'disk' => 100,
        'memory' => null,
        'down' => false,
    ], $state);

    Http::fake(function (Request $request) use ($panel) {
        // The module calls the panel's external API under /api/external.
        $path = preg_replace('#^/api/external#', '', (string) parse_url($request->url(), PHP_URL_PATH));
        $ok = fn ($data) => Http::response(['status' => 'success', 'data' => $data]);
        if ($panel->down) {
            return Http::response(['status' => 'error'], 500);
        }
        if ($request->method() === 'PATCH' && str_starts_with($path, '/v1/accounts/')) {
            return $ok([]);
        }
        if ($request->method() === 'POST' && $path === '/v1/plans') {
            return $ok(['id' => 'managed-new']);
        }
        if ($request->method() === 'PATCH' && str_starts_with($path, '/v1/plans/')) {
            return $ok([]);
        }

        return match (true) {
            $path === '/v1/plans' => $ok($panel->plans),
            $path === '/v1/docker/containers' => $ok(array_merge(
                array_map(fn ($i) => ['id' => "c$i", 'name' => "app$i", 'state' => 'running', 'labels' => ['panelica.user_id' => 'acc-1'], 'ports' => []], $panel->apps > 0 ? range(1, $panel->apps) : []),
                [['id' => 'other', 'name' => 'theirs', 'state' => 'running', 'labels' => ['panelica.user_id' => 'acc-2'], 'ports' => []]],
            )),
            $path === '/v1/accounts/acc-1/stats' => $ok($panel->stats),
            $path === '/v1/accounts/acc-1/domains' => $ok($panel->domains),
            str_starts_with($path, '/v1/domains/') => $ok($panel->subdomains[explode('/', $path)[3]] ?? []),
            $path === '/v1/accounts/acc-1/disk-usage' => $ok(['used_mb' => $panel->disk, 'quota_mb' => 0]),
            $path === '/v1/accounts/acc-1/resource-usage' => $ok($panel->memory ?? ['available' => false]),
            $path === '/v1/accounts/acc-1' => $ok(['plan_id' => 'plan-big']),
            default => $ok([]),
        };
    });

    return $panel;
}

function fitPlan(string $id, array $limits = []): array
{
    return array_merge([
        'id' => $id, 'name' => $id, 'max_containers' => -1, 'max_domains' => -1, 'max_email_accounts' => -1,
        'max_ftp_accounts' => -1, 'max_subdomains' => -1, 'disk_quota_mb' => 0, 'memory_limit_mb' => 0,
    ], $limits);
}

function fitProduct(string $name, float $monthly, array $config): Product
{
    $currency = Currency::where('is_default', true)->first()
        ?? Currency::create(['code' => 'USD', 'prefix' => '$', 'suffix' => '', 'rate' => 1, 'is_default' => true]);
    $product = Product::factory()->create([
        'group_id' => ProductGroup::factory()->create()->id, 'name' => $name, 'server_type' => 'panelica',
        'tax' => false, 'config_options' => $config,
    ]);
    Pricing::updateOrCreate(['type' => 'product', 'rel_id' => $product->id, 'currency_id' => $currency->id], ['monthly' => $monthly]);

    return $product;
}

/** @return array{user: User, service: Service, big: Product} */
function fitService(): array
{
    $server = Server::factory()->create(['type' => 'panelica', 'hostname' => 'panel.test', 'ip_address' => '', 'port' => 8443, 'password' => 'pk_test', 'access_hash' => 'sk_test']);
    $big = fitProduct('Fit Big', 30, ['package_name' => 'plan-big']);
    $user = User::factory()->create();
    $client = Client::factory()->create();
    $user->clients()->attach($client->id);
    $service = Service::factory()->create([
        'client_id' => $client->id, 'product_id' => $big->id, 'server_id' => $server->id, 'status' => 'active',
        'amount' => 30, 'billing_cycle' => 'Monthly', 'next_due_date' => now()->addDays(20), 'domain' => 'shop.test',
    ]);
    $service->forceFill(['module_data' => ['panelica_user_id' => 'acc-1']])->save();

    return ['user' => $user, 'service' => $service->fresh(), 'big' => $big];
}

function fitPatchedPlan(): ?string
{
    $plan = null;
    Http::assertSent(function ($request) use (&$plan) {
        if ($request->method() === 'PATCH' && str_ends_with($request->url(), '/v1/accounts/acc-1')) {
            $plan = $request['plan_id'] ?? null;
        }

        return true;
    });

    return $plan;
}

test('three apps do not move onto a two-app package, and the customer is told to remove one', function () {
    ['user' => $user, 'service' => $service, 'big' => $big] = fitService();
    $small = fitProduct('Fit Small', 10, ['package_name' => 'plan-small']);
    fitPanel(['plans' => [fitPlan('plan-big', ['max_containers' => 5]), fitPlan('plan-small', ['max_containers' => 2])], 'apps' => 3]);
    $problem = __('panelica.fit_apps', ['used' => 3, 'limit' => 2, 'remove' => 1]);

    $html = $this->actingAs($user)->get(route('client.services.upgrade', $service))->assertOk()->getContent();
    expect($html)->toMatch('#<input type="radio" name="new_product_id" value="'.$small->id.'"[^>]*disabled#')
        ->and($html)->toContain(e(__('client.services.package_fit_intro')))
        ->and($html)->toContain(e($problem));

    $this->actingAs($user)->post(route('client.services.upgrade.process', $service), ['new_product_id' => $small->id])
        ->assertSessionHas('error', __('client.services.package_does_not_fit', ['problems' => $problem]));

    expect(Upgrade::count())->toBe(0)
        ->and($service->fresh()->product_id)->toBe($big->id)
        ->and(fitPatchedPlan())->toBeNull();
});

test('once the apps fit, the package can be chosen and the move goes through', function () {
    ['user' => $user, 'service' => $service] = fitService();
    $small = fitProduct('Fit Small', 10, ['package_name' => 'plan-small']);
    fitPanel(['plans' => [fitPlan('plan-small', ['max_containers' => 2])], 'apps' => 2]);

    $html = $this->actingAs($user)->get(route('client.services.upgrade', $service))->assertOk()->getContent();
    expect($html)->toMatch('#<input type="radio" name="new_product_id" value="'.$small->id.'"(?![^>]*disabled)[^>]*>#')
        ->and($html)->not->toContain(e(__('client.services.package_fit_intro')));

    $this->actingAs($user)->post(route('client.services.upgrade.process', $service), ['new_product_id' => $small->id])
        ->assertSessionHasNoErrors()->assertSessionMissing('error');

    expect($service->fresh()->product_id)->toBe($small->id)
        ->and(fitPatchedPlan())->toBe('plan-small');
});

test('a package without apps refuses an account that has some', function () {
    ['user' => $user, 'service' => $service] = fitService();
    $none = fitProduct('Fit No Apps', 10, ['package_name' => 'plan-none']);
    fitPanel(['plans' => [fitPlan('plan-none', ['max_containers' => 0])], 'apps' => 1]);

    $this->actingAs($user)->post(route('client.services.upgrade.process', $service), ['new_product_id' => $none->id])
        ->assertSessionHas('error', __('client.services.package_does_not_fit', ['problems' => __('panelica.fit_apps_none', ['used' => 1])]));
});

test('domains, email and FTP accounts, subdomains per domain and disk are counted as the panel counts them', function () {
    ['service' => $service] = fitService();
    $small = fitProduct('Fit Small', 10, ['package_name' => 'plan-small']);
    fitPanel([
        'plans' => [fitPlan('plan-small', ['max_domains' => 1, 'max_email_accounts' => 2, 'max_ftp_accounts' => 0, 'max_subdomains' => 1, 'disk_quota_mb' => 1000])],
        'apps' => 0,
        'stats' => ['domain_count' => 2, 'email_count' => 2, 'ftp_count' => 1],
        'domains' => [['id' => 'dom-1', 'domain_name' => 'shop.test'], ['id' => 'dom-2', 'domain_name' => 'blog.test']],
        'subdomains' => ['dom-1' => [['id' => 's1'], ['id' => 's2'], ['id' => 's3']], 'dom-2' => [['id' => 's4']]],
        'disk' => 1500,
    ]);

    $fit = app(\App\Services\UpgradeService::class)->packageFit($service, $small);

    expect($fit['problems'])->toBe([
        __('panelica.fit_domains', ['used' => 2, 'limit' => 1, 'remove' => 1]),
        __('panelica.fit_ftp', ['used' => 1, 'limit' => 0, 'remove' => 1]),
        __('panelica.fit_subdomains', ['domain' => 'shop.test', 'used' => 3, 'limit' => 1, 'remove' => 2]),
        __('panelica.fit_disk', ['used' => '1,500', 'limit' => '1,000', 'remove' => '500']),
    ])->and($fit['warnings'])->toBe([]);
});

test('memory above the package is a warning, not a refusal, and only from a recent sample', function () {
    ['user' => $user, 'service' => $service] = fitService();
    $small = fitProduct('Fit Small', 10, ['package_name' => 'plan-small']);
    $panel = fitPanel(['plans' => [fitPlan('plan-small', ['memory_limit_mb' => 512])], 'apps' => 0,
        'memory' => ['available' => true, 'memory_usage_mb' => 900, 'recorded_at' => now()->subMinutes(5)->toIso8601String()]]);
    $warning = __('panelica.fit_memory', ['used' => '900', 'limit' => '512']);

    $html = $this->actingAs($user)->get(route('client.services.upgrade', $service))->assertOk()->getContent();
    expect($html)->toContain(e($warning))
        ->and($html)->toMatch('#<input type="radio" name="new_product_id" value="'.$small->id.'"(?![^>]*disabled)[^>]*>#');

    $panel->memory['recorded_at'] = now()->subHours(2)->toIso8601String();
    expect(app(\App\Services\UpgradeService::class)->packageFit($service, $small)['warnings'])->toBe([]);

    $this->actingAs($user)->post(route('client.services.upgrade.process', $service), ['new_product_id' => $small->id])
        ->assertSessionMissing('error');
    expect($service->fresh()->product_id)->toBe($small->id);
});

test('a panel that cannot tell stops nobody', function () {
    ['service' => $service] = fitService();
    $small = fitProduct('Fit Small', 10, ['package_name' => 'plan-small']);
    fitPanel(['down' => true]);

    expect(app(\App\Services\UpgradeService::class)->packageFit($service, $small))->toBe(['problems' => [], 'warnings' => []]);
});

test('the API refuses the same move', function () {
    ['service' => $service, 'big' => $big] = fitService();
    $small = fitProduct('Fit Small', 10, ['package_name' => 'plan-small']);
    fitPanel(['plans' => [fitPlan('plan-small', ['max_containers' => 2])], 'apps' => 3]);
    $credential = \App\Models\ApiCredential::factory()->create();

    $this->withHeaders(['X-API-Key' => $credential->identifier, 'X-API-Secret' => \Database\Factories\ApiCredentialFactory::PLAINTEXT_SECRET, 'Accept' => 'application/json'])
        ->post('/api/v1/upgradeproduct', ['serviceid' => $service->id, 'packageid' => $small->id])
        ->assertStatus(422);

    expect($service->fresh()->product_id)->toBe($big->id);
});

test('staff can still move the service on its admin page', function () {
    ['service' => $service] = fitService();
    $small = fitProduct('Fit Small', 10, ['package_name' => 'plan-small']);
    fitPanel(['plans' => [fitPlan('plan-small', ['max_containers' => 2])], 'apps' => 3]);
    $admin = Admin::factory()->create(['role_id' => AdminRole::factory()->create(['name' => 'Fit '.uniqid(), 'permissions' => ['view_services', 'manage_services']])->id]);

    $this->actingAs($admin, 'admin')->put(route('admin.services.update', $service), [
        'product_id' => $small->id, 'domain' => $service->domain, 'username' => $service->username,
        'billing_cycle' => $service->billing_cycle, 'amount' => 10, 'override_auto_suspend_date' => null, 'notes' => $service->notes,
    ])->assertSessionHas('success');

    expect($service->fresh()->product_id)->toBe($small->id)
        ->and(fitPatchedPlan())->toBe('plan-small');
});

test('a managed package is judged on its own resources, and the account moves onto its plan', function () {
    ['user' => $user, 'service' => $service] = fitService();
    $managed = fitProduct('Fit Managed', 10, ['res_managed' => 1, 'res_max_containers' => 2, 'res_max_domains' => -1, 'res_max_subdomains' => -1, 'res_max_email' => -1, 'res_max_ftp' => -1, 'res_disk_mb' => 0, 'res_memory_mb' => 0]);
    $panel = fitPanel(['plans' => [], 'apps' => 3]);

    expect(app(\App\Services\UpgradeService::class)->packageFit($service, $managed)['problems'])
        ->toBe([__('panelica.fit_apps', ['used' => 3, 'limit' => 2, 'remove' => 1])]);

    $panel->apps = 2;
    $this->actingAs($user)->post(route('client.services.upgrade.process', $service), ['new_product_id' => $managed->id])
        ->assertSessionMissing('error');

    expect($service->fresh()->product_id)->toBe($managed->id)
        ->and(fitPatchedPlan())->toBe('managed-new');
    Http::assertSent(fn ($request) => $request->method() === 'POST' && str_ends_with($request->url(), '/v1/plans') && $request['name'] === 'pnlcs-p'.$managed->id);
});

test('an account already over its plan can still move to a package that gives it no less', function () {
    ['user' => $user, 'service' => $service] = fitService();
    $mid = fitProduct('Fit Mid', 20, ['package_name' => 'plan-mid']);
    // Moved by staff onto a one-app plan with three apps; the two-app package
    // takes nothing away from it.
    fitPanel(['plans' => [fitPlan('plan-big', ['max_containers' => 1, 'max_domains' => 1]), fitPlan('plan-mid', ['max_containers' => 2, 'max_domains' => 1])],
        'apps' => 3, 'stats' => ['domain_count' => 2, 'email_count' => 0, 'ftp_count' => 0]]);

    expect(app(\App\Services\UpgradeService::class)->packageFit($service, $mid)['problems'])->toBe([]);

    $this->actingAs($user)->post(route('client.services.upgrade.process', $service), ['new_product_id' => $mid->id])
        ->assertSessionMissing('error');
});
