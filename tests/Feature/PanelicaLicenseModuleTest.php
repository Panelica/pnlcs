<?php

use App\Events\InvoicePaid;
use App\Listeners\RenewOnPaymentListener;
use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\ModuleQueue;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Service;
use App\Models\Setting;
use App\Models\User;
use App\Services\EmailTemplateService;
use App\Services\Module\ModuleRegistry;
use App\Services\ProvisioningService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Modules\Servers\PanelicaLicense\CentralClient;
use Modules\Servers\PanelicaLicense\PanelicaLicenseModule;
use Modules\Servers\PanelicaLicense\PartnerAccount;

/*
 * Panelica licenses sold with a Panelica partner account
 * (modules/Servers/PanelicaLicense). Central is faked at the HTTP level with
 * the shapes its partner API answers with (Central: ResellerApiController).
 *
 * The flow under test: the order is paid and the customer gets an install
 * command of their own at this site's address; nothing is bought. Their
 * server runs it, the panel starts a trial, Central writes the trial to the
 * partner and remembers the order's reference; the sync finds the server by
 * that reference and buys the plan the customer paid for.
 */

const PL_PLAN = '11111111-2222-3333-4444-555555555555';
const PL_LICENSE = '99999999-8888-7777-6666-555555555555';

function plCentral(array $over = []): array
{
    return array_merge([
        'calls' => [],
        'link' => ['code' => 'acme', 'url' => 'https://www.panelica.com/install/acme'],
        'installs' => [],
        'license' => [
            'id' => PL_LICENSE, 'license_key' => 'PNLC-AAAA-BBBB-CCCC-DDDD', 'status' => 'active',
            'plan' => ['id' => PL_PLAN, 'name' => 'Business'], 'billing_cycle' => 'yearly',
            'server_ip' => '203.0.113.7', 'server_hostname' => 'srv1.customer.test',
            'expires_at' => '2027-10-21T10:00:00+00:00', 'last_heartbeat_at' => '2026-10-21T10:05:00+00:00',
        ],
        'convert' => 200,
        'renew' => 200,
        'script' => 200,
        'filter' => true,
    ], $over);
}

function plFake(array &$central): void
{
    Http::fake(function (Request $request) use (&$central) {
        $path = parse_url($request->url(), PHP_URL_PATH);
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
        $central['calls'][] = [$request->method(), $path, $query, $request->data(), $request->header('Idempotency-Key')[0] ?? null, $request->header('Authorization')[0] ?? null];
        $ok = fn ($data, $meta = []) => Http::response(['success' => true, 'data' => $data] + ($meta ? ['meta' => $meta] : []), 200);
        $no = fn ($status, $error) => Http::response(['success' => false, 'error' => $error, 'code' => $status], $status);
        $api = '/api/reseller/v1/';

        return match (true) {
            $path === $api.'me' => $ok(['id' => 'r1', 'company_name' => 'Acme Hosting', 'status' => 'approved', 'balance' => 500]),
            $path === $api.'plans' => $ok([['id' => PL_PLAN, 'name' => 'Business', 'reseller_price_monthly' => 8, 'reseller_price_yearly' => 80]]),
            $path === $api.'install-link' => $central['link'] ? $ok($central['link']) : $no(404, 'Not found'),
            // 'filter' => false is a Central that does not know ?ref= and lists every install.
            $path === $api.'installs' => $ok(array_values(array_filter($central['installs'], fn ($i) => ! $central['filter'] || ($i['ref'] ?? null) === ($query['ref'] ?? null))), ['total' => count($central['installs'])]),
            $path === $api.'licenses/'.PL_LICENSE.'/convert' => $central['convert'] === 200
                ? $ok(['id' => PL_LICENSE, 'license_key' => 'PNLC-AAAA-BBBB-CCCC-DDDD', 'plan' => ['id' => PL_PLAN, 'name' => 'Business'], 'billing_cycle' => $request->data()['billing_cycle'] ?? null, 'status' => 'active', 'expires_at' => '2027-10-21T10:00:00+00:00', 'charged' => 80])
                : $no($central['convert'], 'Insufficient balance. Required: $80.00, Available: $3.00'),
            $path === $api.'licenses/'.PL_LICENSE.'/renew' => $central['renew'] === 200
                ? $ok(['id' => PL_LICENSE, 'status' => 'active', 'expires_at' => '2028-10-21T10:00:00+00:00', 'charged' => 80])
                : $no($central['renew'], 'Insufficient balance. Required: $80.00, Available: $3.00'),
            $path === $api.'licenses/'.PL_LICENSE.'/suspend' => (function () use (&$central, $ok, $no) {
                if ($central['license']['status'] === 'suspended') {
                    return $no(409, 'License is already suspended.');
                }
                $central['license']['status'] = 'suspended';

                return $ok(['id' => PL_LICENSE, 'status' => 'suspended']);
            })(),
            $path === $api.'licenses/'.PL_LICENSE.'/activate' => (function () use (&$central, $ok) {
                $central['license']['status'] = 'active';

                return $ok(['id' => PL_LICENSE, 'status' => 'active']);
            })(),
            $path === $api.'licenses/'.PL_LICENSE => $ok($central['license']),
            str_starts_with($path, '/install/') => $central['script'] === 200
                ? Http::response("#!/usr/bin/env bash\n# Panelica installer\npanelica_installer() { :; }\npanelica_installer \"\$@\"\n# for {$path}\n", 200, ['Content-Type' => 'text/plain'])
                : Http::response('', 302, ['Location' => 'https://latest.panelica.com/install.sh']),
            default => $no(404, 'Unexpected call '.$path),
        };
    });
}

/** The partner account this site sells with (the Panelica Licenses page). */
function plConnect(?string $central = null): void
{
    PartnerAccount::save('rk_live_secret_partner_key', $central);
}

function plService(array $attrs = []): Service
{
    plConnect();
    $product = Product::factory()->create([
        'group_id' => ProductGroup::factory()->create()->id, 'type' => 'other', 'server_type' => 'panelicalicense',
        'config_options' => ['package_name' => PL_PLAN], 'pay_type' => 'recurring',
    ]);

    return Service::factory()->create(array_merge([
        'client_id' => Client::factory()->create()->id, 'product_id' => $product->id, 'server_id' => null,
        'status' => 'active', 'billing_cycle' => 'Annually', 'amount' => 120, 'next_due_date' => '2027-10-21',
        'domain' => null, 'username' => null,
    ], $attrs));
}

function plModule(): PanelicaLicenseModule
{
    return app(PanelicaLicenseModule::class);
}

function plWaiting(array &$central, array $attrs = []): Service
{
    plFake($central);
    $service = plService($attrs);
    expect(plModule()->create($service)['success'])->toBeTrue();

    return $service->fresh();
}

function plInstall(Service $service, array $over = []): array
{
    return array_merge([
        'id' => 'inst-1', 'hostname' => 'srv1.customer.test', 'ip_address' => '203.0.113.7', 'os' => 'Ubuntu 24.04 LTS',
        'code' => 'acme', 'ref' => $service->module_data['install_ref'], 'status' => 'linked', 'note' => null,
        'reported_at' => '2026-10-21T09:00:00+00:00', 'matched_at' => '2026-10-21T09:10:00+00:00', 'converted_at' => null,
        'license' => ['id' => PL_LICENSE, 'license_key' => 'PNLC-AAAA-BBBB-CCCC-DDDD', 'plan' => 'Free Trial', 'status' => 'active', 'is_trial' => true, 'convertible' => true],
    ], $over);
}

function plCalls(array $central, string $method, string $suffix): array
{
    return array_values(array_filter($central['calls'], fn ($c) => $c[0] === $method && str_ends_with($c[1], $suffix)));
}

function plSite(): string
{
    return 'pnlcs-'.(parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'pnlcs');
}

function plStaff(array $permissions = ['view_services', 'manage_services']): Admin
{
    return Admin::factory()->create(['role_id' => AdminRole::factory()->create(['name' => 'R'.uniqid(), 'permissions' => $permissions])->id]);
}

function plCustomer(Service $service): User
{
    $user = User::factory()->create();
    $user->clients()->attach($service->client_id);

    return $user;
}

// ---------------------------------------------------------------------------

test('it is a built-in module with no server records: the partner account is set on its own page', function () {
    $registry = app(ModuleRegistry::class);

    expect($registry->getServerModule('panelicalicense'))->toBeInstanceOf(PanelicaLicenseModule::class)
        ->and($registry->serverModuleNames())->toHaveKey('panelicalicense', 'Panelica Licenses')
        ->and($registry->usesServerRecords('panelicalicense'))->toBeFalse()
        ->and($registry->usesServerRecords('cpanel'))->toBeTrue();

    $servers = $this->actingAs(plStaff(['manage_servers']), 'admin')->get(route('admin.config.servers'))->assertOk()->getContent();
    expect($servers)->not->toContain('value="panelicalicense"')
        ->and($servers)->toContain('value="cpanel"');
});

test('the partner key is kept encrypted, and Central is www.panelica.com unless another address is given', function () {
    plConnect();

    expect(PartnerAccount::apiKey())->toBe('rk_live_secret_partner_key')
        ->and((string) Setting::get('panelica_partner_api_key'))->not->toContain('rk_live')
        ->and(CentralClient::partner()->baseUrl())->toBe('https://www.panelica.com');

    plConnect('http://127.0.0.1:8100/');
    expect(CentralClient::partner()->baseUrl())->toBe('http://127.0.0.1:8100');

    PartnerAccount::forget();
    expect(CentralClient::partner())->toBeNull();
});

test('the connection test and the plans come from the partner account', function () {
    $central = plCentral();
    plFake($central);
    plConnect();

    expect(plModule()->testConnection())->toBeTrue()
        ->and(plModule()->listPackages())->toBe([['id' => PL_PLAN, 'name' => 'Business (your price: $8.00 a month, $80.00 a year)']])
        ->and($central['calls'][0][5])->toBe('Bearer rk_live_secret_partner_key');

    PartnerAccount::forget();
    expect(fn () => plModule()->listPackages())->toThrow(RuntimeException::class, __('panelica_license.error.no_account'));
});

test('the product form lists the plans without a server record, and says why when it cannot', function () {
    $central = plCentral();
    plFake($central);
    plConnect();
    $staff = plStaff(['list_products', 'manage_products']);

    $this->actingAs($staff, 'admin')->getJson(route('admin.products.packages', ['module' => 'panelicalicense']))
        ->assertOk()->assertJsonPath('packages.0.id', PL_PLAN)->assertJsonPath('error', null);

    PartnerAccount::forget();
    $this->actingAs($staff, 'admin')->getJson(route('admin.products.packages', ['module' => 'panelicalicense']))
        ->assertOk()->assertJsonPath('packages', [])->assertJsonPath('error', __('panelica_license.error.no_account'));
});

test('a paid order hands out an install link and buys nothing', function () {
    $central = plCentral();
    $service = plWaiting($central);

    expect($service->module_data['install_ref'])->toMatch('/^[A-Za-z0-9]{32}$/')
        ->and(collect($central['calls'])->pluck(0)->unique()->values()->all())->toBe(['GET'])
        ->and(plModule()->installCommand($service))->toBe('curl -sSL '.url('/setup/'.$service->module_data['install_ref']).' | bash');
});

test('Create again keeps the same link and never buys a second license', function () {
    $central = plCentral();
    $service = plWaiting($central);
    $ref = $service->module_data['install_ref'];

    plModule()->create($service->fresh());
    expect($service->fresh()->module_data['install_ref'])->toBe($ref);

    $service->forceFill(['module_data' => $service->fresh()->module_data + ['license_id' => PL_LICENSE]])->save();
    $central['calls'] = [];
    expect(plModule()->create($service->fresh())['success'])->toBeTrue()
        ->and($central['calls'])->toBe([]);
});

test('a period Central does not sell is refused before anything is asked of Central', function () {
    $central = plCentral();
    plFake($central);
    $service = plService(['billing_cycle' => 'Quarterly']);

    $result = plModule()->create($service);

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('Quarterly')
        ->and($central['calls'])->toBe([]);
});

test('a partner account Central refuses is not handed to the customer as a working command', function () {
    $central = plCentral(['link' => null]);
    plFake($central);
    $service = plService(['status' => 'pending']);

    $result = app(ProvisioningService::class)->createAccount($service);

    expect($result['success'])->toBeFalse()
        ->and($service->fresh()->module_data['install_ref'] ?? null)->toBeNull()
        ->and(ModuleQueue::where('service_id', $service->id)->where('action', 'create')->where('status', 'pending')->exists())->toBeTrue();
});

// --- the customer's command --------------------------------------------------

test('the customer\'s command answers with Central\'s script for this order, fetched at this site', function () {
    $central = plCentral();
    $service = plWaiting($central);
    $ref = $service->module_data['install_ref'];

    $response = $this->get('/setup/'.$ref);

    $response->assertOk()->assertHeader('Content-Type', 'text/plain; charset=utf-8');
    expect($response->getContent())->toStartWith('#!/usr/bin/env bash')
        ->and($response->getContent())->toContain('/install/acme/'.$ref)
        ->and($service->fresh()->module_data['script_fetched_at'] ?? null)->not->toBeNull()
        ->and($response->headers->getCookies())->toBe([]);
});

test('a link that is not an order waiting for its server stops the install and says why', function (string $case) {
    $central = plCentral();
    $service = plWaiting($central);
    $ref = $service->module_data['install_ref'];

    match ($case) {
        'unknown' => $ref = str_repeat('x', 32),
        'suspended' => $service->forceFill(['status' => 'suspended'])->save(),
        'licensed' => $service->forceFill(['module_data' => $service->module_data + ['license_id' => PL_LICENSE]])->save(),
        'ended' => $service->forceFill(['module_data' => $service->module_data + ['ended' => true]])->save(),
        'code disabled' => $central['script'] = 302,
    };
    $central['calls'] = [];

    $body = $this->get('/setup/'.$ref)->assertOk()->getContent();

    expect($body)->toStartWith("#!/usr/bin/env bash\nprintf")
        ->and($body)->toContain('exit 1')
        ->and($body)->not->toContain('panelica_installer');
    if ($case !== 'code disabled') {
        expect(plCalls($central, 'GET', '/'.$ref))->toBe([]);
    }
})->with(['unknown', 'suspended', 'licensed', 'ended', 'code disabled']);

test('a refusal is a script bash can run even when the message has a quote in it', function () {
    app()->setLocale('en');
    $central = plCentral();
    plFake($central);
    $body = $this->get('/setup/'.str_repeat('y', 32))->getContent();
    $script = tempnam(sys_get_temp_dir(), 'pl');
    file_put_contents($script, $body);

    exec('bash '.escapeshellarg($script).' 2>&1', $out, $code);
    @unlink($script);

    expect($code)->toBe(1)->and(implode("\n", $out))->toBe(__('panelica_license.script.unknown'));
});

test('a new link retires the old one at once', function () {
    $central = plCentral();
    $service = plWaiting($central);
    $old = $service->module_data['install_ref'];

    expect(plModule()->newInstallLink($service)['success'])->toBeTrue();
    $new = $service->fresh()->module_data['install_ref'];

    expect($new)->not->toBe($old);
    expect($this->get('/setup/'.$old)->getContent())->toContain('exit 1');
    expect($this->get('/setup/'.$new)->getContent())->toContain('panelica_installer');
});

// --- finding the server and buying its license ---------------------------------

test('nothing seen yet: the service keeps waiting and nothing is bought', function () {
    $central = plCentral();
    $service = plWaiting($central);

    $result = plModule()->checkInstall($service);

    expect($result['success'])->toBeTrue()
        ->and(plCalls($central, 'POST', '/convert'))->toBe([])
        ->and(plCalls($central, 'GET', '/installs')[0][2]['ref'])->toBe($service->module_data['install_ref']);
});

test('the server shows up: the plan the customer paid for is bought once, from the trial', function () {
    $central = plCentral();
    $service = plWaiting($central);
    $central['installs'] = [plInstall($service)];

    $result = plModule()->checkInstall($service->fresh());
    $data = $service->fresh()->module_data;
    $convert = plCalls($central, 'POST', '/licenses/'.PL_LICENSE.'/convert');

    expect($result['success'])->toBeTrue()
        ->and($convert)->toHaveCount(1)
        ->and($convert[0][3])->toBe(['plan_id' => PL_PLAN, 'billing_cycle' => 'yearly'])
        ->and($convert[0][4])->toBe(plSite().'-svc-'.$service->id.'-convert-'.PL_LICENSE)
        ->and($data['license_id'])->toBe(PL_LICENSE)
        ->and($data['license_key'])->toBe('PNLC-AAAA-BBBB-CCCC-DDDD')
        ->and($data['license_server_hostname'])->toBe('srv1.customer.test');

    // Later runs only read the license back.
    plModule()->checkInstall($service->fresh());
    expect(plCalls($central, 'POST', '/convert'))->toHaveCount(1);
});

test('a link handed out before payment buys nothing until the order is paid', function () {
    $central = plCentral();
    plFake($central);
    $service = plService();
    $invoice = Invoice::factory()->create(['client_id' => $service->client_id, 'status' => 'unpaid', 'total' => 120]);
    $order = Order::factory()->create(['client_id' => $service->client_id, 'invoice_id' => $invoice->id]);
    $service->forceFill(['order_id' => $order->id])->save();
    plModule()->create($service->fresh());
    $service = $service->fresh();
    $central['installs'] = [plInstall($service)];

    expect(plModule()->checkInstall($service)['message'])->toBe(__('panelica_license.result.unpaid'))
        ->and(plCalls($central, 'POST', '/convert'))->toBe([]);

    $invoice->forceFill(['status' => 'paid'])->save();
    plModule()->checkInstall($service->fresh());

    expect(plCalls($central, 'POST', '/convert'))->toHaveCount(1);
});

test('an install of another order is never taken, even from a Central that ignores the filter', function () {
    $central = plCentral();
    $service = plWaiting($central);
    $other = plInstall($service, ['id' => 'someone-else', 'ref' => str_repeat('Z', 32)]);
    $central['filter'] = false;
    $central['installs'] = [$other, array_diff_key($other, ['ref' => 1])];

    $result = plModule()->checkInstall($service->fresh());

    expect($result['message'])->toBe(__('panelica_license.result.not_seen'))
        ->and(plCalls($central, 'GET', '/installs'))->toHaveCount(1)
        ->and(plCalls($central, 'POST', '/convert'))->toBe([])
        ->and($service->fresh()->module_data['license_id'] ?? null)->toBeNull();
});

test('a monthly service buys a monthly license', function () {
    $central = plCentral();
    $service = plWaiting($central, ['billing_cycle' => 'Monthly']);
    $central['installs'] = [plInstall($service)];

    plModule()->checkInstall($service->fresh());

    expect(plCalls($central, 'POST', '/convert')[0][3]['billing_cycle'])->toBe('monthly');
});

test('two servers ran the same link: the first one gets the license, the other stays a trial', function () {
    $central = plCentral();
    $service = plWaiting($central);
    $central['installs'] = [
        plInstall($service, ['id' => 'second', 'hostname' => 'second.test', 'matched_at' => '2026-10-21T11:00:00+00:00', 'license' => ['id' => 'other-license', 'status' => 'active', 'is_trial' => true, 'convertible' => true]]),
        plInstall($service),
    ];

    plModule()->checkInstall($service->fresh());

    expect(plCalls($central, 'POST', '/licenses/'.PL_LICENSE.'/convert'))->toHaveCount(1)
        ->and(plCalls($central, 'POST', '/licenses/other-license/convert'))->toBe([])
        ->and($service->fresh()->module_data['installs_seen'])->toBe(2);
});

test('a purchase whose answer was lost is taken over, not bought twice', function () {
    $central = plCentral();
    $service = plWaiting($central);
    $central['installs'] = [plInstall($service, [
        'converted_at' => '2026-10-21T09:20:00+00:00',
        'license' => ['id' => PL_LICENSE, 'status' => 'active', 'is_trial' => false, 'convertible' => false],
    ])];

    plModule()->checkInstall($service->fresh());

    expect(plCalls($central, 'POST', '/convert'))->toBe([])
        ->and($service->fresh()->module_data['license_id'])->toBe(PL_LICENSE)
        ->and($service->fresh()->module_data['license_key'])->toBe('PNLC-AAAA-BBBB-CCCC-DDDD');
});

test('a short partner balance is told to staff once, and the customer is not shown the partner\'s money', function () {
    $central = plCentral(['convert' => 402]);
    $service = plWaiting($central);
    $central['installs'] = [plInstall($service)];
    $raised = [];
    add_hook('ModuleActionFailed', 1, function ($vars) use (&$raised) {
        $raised[] = $vars['error'];
    });

    plModule()->checkInstall($service->fresh());
    plModule()->checkInstall($service->fresh());

    expect($raised)->toHaveCount(1)
        ->and($raised[0])->toContain('balance')
        ->and($service->fresh()->module_data['license_id'] ?? null)->toBeNull();

    $page = $this->actingAs(plCustomer($service))->get(route('client.services.show', $service))->assertOk()->getContent();
    expect($page)->toContain(e(__('panelica_license.client.needs_help')))
        ->and($page)->not->toContain('Insufficient balance')
        ->and($page)->not->toContain('$3.00');

    // Topped up: the next run buys it.
    $central['convert'] = 200;
    plModule()->checkInstall($service->fresh());
    expect($service->fresh()->module_data['license_id'])->toBe(PL_LICENSE)
        ->and($service->fresh()->module_data['last_error'])->toBeNull();
});

test('a server Central could not tie to the order goes to staff with Central\'s reason', function () {
    $central = plCentral();
    $service = plWaiting($central);
    $central['installs'] = [plInstall($service, ['status' => 'not_linked', 'note' => 'The email used for the trial already has a Panelica account.', 'license' => null])];

    $result = plModule()->checkInstall($service->fresh());

    expect($result['success'])->toBeFalse()
        ->and($service->fresh()->module_data['last_error'])->toContain('already has a Panelica account')
        ->and(plCalls($central, 'POST', '/convert'))->toBe([]);

    $staff = $this->actingAs(plStaff(), 'admin')->get(route('admin.services.show', $service))->assertOk()->getContent();
    expect($staff)->toContain('already has a Panelica account');
});

test('the sync looks only at services still waiting for their server', function () {
    $central = plCentral();
    $waiting = plWaiting($central);
    $licensed = plService();
    $licensed->forceFill(['module_data' => ['install_ref' => str_repeat('L', 32), 'license_id' => PL_LICENSE]])->save();
    $central['installs'] = [plInstall($waiting)];
    $central['calls'] = [];

    Artisan::call('pnlcs:panelica-licenses');

    expect(collect(plCalls($central, 'GET', '/installs'))->pluck(2)->pluck('ref')->all())->toBe([$waiting->module_data['install_ref']])
        ->and($waiting->fresh()->module_data['license_id'])->toBe(PL_LICENSE);
});

test('the sync costs nothing on a site that does not sell licenses', function () {
    $central = plCentral();
    plFake($central);

    Artisan::call('pnlcs:panelica-licenses');

    expect($central['calls'])->toBe([]);
});

// --- the life of a bought license ------------------------------------------------

function plLicensed(array &$central, array $attrs = []): Service
{
    $service = plWaiting($central, $attrs);
    $central['installs'] = [plInstall($service)];
    plModule()->checkInstall($service->fresh());

    return $service->fresh();
}

test('a paid renewal buys the period once at Central, keyed by the paid period', function () {
    $central = plCentral();
    $service = plLicensed($central);
    $invoice = Invoice::factory()->create(['client_id' => $service->client_id, 'status' => 'paid', 'total' => 120]);
    InvoiceItem::create(['invoice_id' => $invoice->id, 'client_id' => $service->client_id, 'type' => 'Hosting', 'rel_id' => $service->id, 'description' => 'Renewal', 'amount' => 120]);

    app(RenewOnPaymentListener::class)->handleInvoicePaid(new InvoicePaid($invoice->fresh()));
    $renew = plCalls($central, 'POST', '/renew');

    expect($renew)->toHaveCount(1)
        ->and($renew[0][4])->toBe(plSite().'-svc-'.$service->id.'-renew-2028-10-21')
        ->and($service->fresh()->module_data['license_expires_at'])->toBe('2028-10-21T10:00:00+00:00');
});

test('a renewal Central refuses is queued, and goes through once the balance is topped up', function () {
    $central = plCentral(['renew' => 402]);
    $service = plLicensed($central);
    $invoice = Invoice::factory()->create(['client_id' => $service->client_id, 'status' => 'paid', 'total' => 120]);
    InvoiceItem::create(['invoice_id' => $invoice->id, 'client_id' => $service->client_id, 'type' => 'Hosting', 'rel_id' => $service->id, 'description' => 'Renewal', 'amount' => 120]);

    app(RenewOnPaymentListener::class)->handleInvoicePaid(new InvoicePaid($invoice->fresh()));
    $entry = ModuleQueue::where('service_id', $service->id)->where('action', 'renew')->first();
    expect($entry?->status)->toBe('pending');

    $central['renew'] = 200;
    $entry->update(['next_attempt_at' => now()->subMinute()]);
    Artisan::call('pnlcs:module-queue');

    $renew = plCalls($central, 'POST', '/renew');
    expect($entry->fresh()->status)->toBe('completed')
        ->and($renew)->toHaveCount(2)
        ->and($renew[0][4])->toBe($renew[1][4]);
});

test('staff can buy a waiting paid period at once, with the queue\'s own key, and only while one waits', function () {
    $central = plCentral(['renew' => 402]);
    $service = plLicensed($central);
    expect(plModule()->customFunctions($service->fresh()))->not->toHaveKey('retryRenewal');

    $invoice = Invoice::factory()->create(['client_id' => $service->client_id, 'status' => 'paid', 'total' => 120]);
    InvoiceItem::create(['invoice_id' => $invoice->id, 'client_id' => $service->client_id, 'type' => 'Hosting', 'rel_id' => $service->id, 'description' => 'Renewal', 'amount' => 120]);
    app(RenewOnPaymentListener::class)->handleInvoicePaid(new InvoicePaid($invoice->fresh()));
    $entry = ModuleQueue::where('service_id', $service->id)->where('action', 'renew')->firstOrFail();
    $entry->update(['status' => 'failed']);

    $page = $this->actingAs(plStaff(), 'admin')->get(route('admin.services.show', $service))->assertOk()->getContent();
    expect($page)->toContain(route('admin.services.module-action', [$service, 'retryRenewal']))
        ->and($page)->toContain(__('panelica_license.admin.renewal_waiting'));

    $central['renew'] = 200;
    $this->actingAs(plStaff(), 'admin')->post(route('admin.services.module-action', [$service, 'retryRenewal']))
        ->assertRedirect()->assertSessionHas('success', __('panelica_license.result.renewed'));

    $renew = plCalls($central, 'POST', '/renew');
    expect($entry->fresh()->status)->toBe('completed')
        ->and($renew)->toHaveCount(2)
        ->and($renew[1][4])->toBe($renew[0][4])
        ->and(plModule()->customFunctions($service->fresh()))->not->toHaveKey('retryRenewal');
});

test('an overdue service is suspended at Central, and its renewal lifts that suspension first', function () {
    $central = plCentral();
    $service = plLicensed($central);

    expect(app(ProvisioningService::class)->suspendAccount($service->fresh(), 'Overdue')['success'])->toBeTrue()
        ->and($central['license']['status'])->toBe('suspended')
        ->and($service->fresh()->module_data['suspended_here'])->toBeTrue();

    plModule()->renew($service->fresh());

    $order = collect($central['calls'])->filter(fn ($c) => in_array($c[0], ['PATCH', 'POST'], true))->map(fn ($c) => basename($c[1]))->values()->all();
    expect(array_slice($order, -2))->toBe(['activate', 'renew'])
        ->and($service->fresh()->module_data['suspended_here'])->toBeFalse();

    // The unsuspend that follows payment finds it active and leaves it so.
    expect(app(ProvisioningService::class)->unsuspendAccount($service->fresh())['success'])->toBeTrue();
});

test('terminating ends the license at Central and the link with it', function () {
    $central = plCentral();
    $service = plLicensed($central);

    expect(app(ProvisioningService::class)->terminateAccount($service->fresh())['success'])->toBeTrue()
        ->and($central['license']['status'])->toBe('suspended')
        ->and($service->fresh()->module_data['ended'])->toBeTrue()
        ->and(plModule()->renew($service->fresh())['success'])->toBeTrue();

    expect(plCalls($central, 'POST', '/renew'))->toBe([])
        ->and(plModule()->unsuspend($service->fresh())['success'])->toBeFalse();
});

test('a plan change Central refuses after purchase is told to staff, not only logged', function () {
    $central = plCentral();
    $service = plLicensed($central);
    $raised = [];
    add_hook('ModuleActionFailed', 1, function ($vars) use (&$raised) {
        $raised[] = $vars['error'];
    });

    $result = plModule()->changePackage($service->fresh(), ['config_options' => ['package_name' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee']]);

    expect($result['success'])->toBeFalse()
        ->and(plCalls($central, 'POST', '/change-plan'))->toHaveCount(1)
        ->and($raised)->toHaveCount(1)
        ->and($service->fresh()->module_data['last_error'])->toContain('old plan');
});

test('a renewal or suspension before anything was bought asks nothing of Central', function () {
    $central = plCentral();
    $service = plWaiting($central);
    $central['calls'] = [];

    expect(plModule()->renew($service)['success'])->toBeTrue()
        ->and(plModule()->suspend($service)['success'])->toBeTrue()
        ->and($central['calls'])->toBe([]);
});

test('the partner API key never reaches a message', function () {
    $central = plCentral(['convert' => 500]);
    $service = plWaiting($central);
    $central['installs'] = [plInstall($service)];

    $result = plModule()->checkInstall($service->fresh());

    expect($result['message'])->not->toContain('rk_live_secret_partner_key')
        ->and(json_encode($service->fresh()->module_data))->not->toContain('rk_live_secret_partner_key');
});

// --- screens and mail ------------------------------------------------------------

test('the customer sees their command at this site and none of the partner account\'s server details', function () {
    $central = plCentral();
    $service = plWaiting($central);

    $page = $this->actingAs(plCustomer($service))->get(route('client.services.show', $service))->assertOk()->getContent();

    expect($page)->toContain(e(plModule()->installCommand($service)))
        ->and($page)->toContain(__('panelica_license.state.waiting'))
        ->and($page)->not->toContain('www.panelica.com')
        ->and($page)->not->toContain(__('client.services.server_info'));
});

test('once bought the customer sees the key and their own server', function () {
    $central = plCentral();
    $service = plLicensed($central);

    $page = $this->actingAs(plCustomer($service))->get(route('client.services.show', $service))->assertOk()->getContent();

    expect($page)->toContain('PNLC-AAAA-BBBB-CCCC-DDDD')
        ->and($page)->toContain('srv1.customer.test')
        ->and($page)->not->toContain('/setup/');
});

test('the customer may look again and replace their link, but not run a staff function or touch another account', function () {
    $central = plCentral();
    $service = plWaiting($central);
    $user = plCustomer($service);
    $other = plService();
    plModule()->create($other);

    $this->actingAs($user)->post(route('client.services.module-function', [$service, 'checkNow']))->assertRedirect();
    $this->actingAs($user)->post(route('client.services.module-function', [$service, 'newInstallLink']))->assertRedirect();
    $this->actingAs($user)->post(route('client.services.module-function', [$service, 'refreshLicense']))->assertNotFound();
    $this->actingAs($user)->post(route('client.services.module-function', [$service, 'terminate']))->assertNotFound();
    $this->actingAs($user)->post(route('client.services.module-function', [$other, 'checkNow']))->assertForbidden();

    expect($service->fresh()->module_data['install_ref'])->not->toBe($service->module_data['install_ref']);
});

test('staff see the license section, its buttons, and no Create or password change once the link is out', function () {
    $central = plCentral();
    $service = plWaiting($central);

    $page = $this->actingAs(plStaff(), 'admin')->get(route('admin.services.show', $service))->assertOk()->getContent();

    expect($page)->toContain(__('panelica_license.admin.title'))
        ->and($page)->toContain(e(plModule()->installCommand($service)))
        ->and($page)->toContain(route('admin.services.module-action', [$service, 'checkNow']))
        ->and($page)->not->toContain(route('admin.services.module-action', [$service, 'create']))
        ->and($page)->not->toContain(route('admin.services.module-action', [$service, 'changepassword']));

    $central['installs'] = [plInstall($service)];
    $this->actingAs(plStaff(), 'admin')->post(route('admin.services.module-action', [$service, 'checkNow']))
        ->assertRedirect()->assertSessionHas('success', __('panelica_license.result.licensed'));
});

test('staff can tie an existing license of the partner account to a service', function () {
    $central = plCentral();
    $service = plWaiting($central);

    $this->actingAs(plStaff(), 'admin')->post(route('admin.services.panelica-license.attach', $service), ['license_id' => PL_LICENSE])
        ->assertRedirect()->assertSessionHas('success');

    expect($service->fresh()->module_data['license_key'])->toBe('PNLC-AAAA-BBBB-CCCC-DDDD');

    $this->actingAs(Admin::factory()->create(['role_id' => AdminRole::factory()->create(['name' => 'V'.uniqid(), 'permissions' => ['view_services']])->id]), 'admin')
        ->post(route('admin.services.panelica-license.attach', $service), ['license_id' => PL_LICENSE])->assertForbidden();
});

test('the welcome mail can carry the install command and, later, the key', function () {
    $central = plCentral();
    $service = plWaiting($central);
    $fields = app(EmailTemplateService::class)->varsFor(['service' => $service]);

    expect($fields['service_install_command'] ?? null)->toBe(plModule()->installCommand($service))
        ->and($fields['service_license_key'] ?? null)->toBe('');
});

// --- the Panelica Licenses page ------------------------------------------------------

test('the Panelica Licenses page is in the top menu for staff who manage products, and only for them', function () {
    $menu = route('admin.panelica-licenses.index');

    expect($this->actingAs(plStaff(['view_services', 'manage_products']), 'admin')->get(route('admin.dashboard'))->getContent())->toContain($menu);
    expect($this->actingAs(plStaff(['view_services']), 'admin')->get(route('admin.dashboard'))->getContent())->not->toContain($menu);
    $this->actingAs(plStaff(['view_services']), 'admin')->get($menu)->assertForbidden();
});

test('not connected: the page says what it is and asks for the partner key', function () {
    $central = plCentral();
    plFake($central);

    $page = $this->actingAs(plStaff(['manage_products']), 'admin')->get(route('admin.panelica-licenses.index'))->assertOk()->getContent();

    expect($page)->toContain(e(__('panelica_license.page.hero_title')))
        ->and($page)->toContain('name="api_key"')
        ->and($page)->toContain('https://www.panelica.com/partnership')
        ->and($central['calls'])->toBe([]);
});

test('a key Central refuses, or an account that is not active, is not kept', function () {
    $central = plCentral();
    Http::fake(['*/api/reseller/v1/me' => Http::sequence()
        ->push(['success' => false, 'error' => 'Invalid or revoked API key.', 'code' => 401], 401)
        ->push(['success' => true, 'data' => ['company_name' => 'Acme', 'status' => 'pending']], 200)]);
    $staff = plStaff(['manage_products']);

    $this->actingAs($staff, 'admin')->post(route('admin.panelica-licenses.connect'), ['api_key' => 'rk_wrong'])
        ->assertSessionHas('error', __('panelica_license.page.connect_failed', ['error' => 'Invalid or revoked API key.']));
    $this->actingAs($staff, 'admin')->post(route('admin.panelica-licenses.connect'), ['api_key' => 'rk_pending'])
        ->assertSessionHas('error', __('panelica_license.page.account_not_active', ['status' => 'pending']));
    $this->actingAs($staff, 'admin')->post(route('admin.panelica-licenses.connect'), ['api_key' => 'not-a-partner-key'])
        ->assertSessionHasErrors('api_key');

    expect(PartnerAccount::connected())->toBeFalse();
});

test('connected: the account, the plans with cost and list price, and a product made in one click', function () {
    $central = plCentral();
    plFake($central);
    $staff = plStaff(['manage_products', 'list_products']);
    Currency::query()->update(['is_default' => false]);
    $usd = Currency::firstOrCreate(['code' => 'USD'], ['prefix' => '$', 'suffix' => '', 'format' => 1, 'rate' => 1]);
    $usd->forceFill(['is_default' => true, 'rate' => 1])->save();
    $eur = Currency::updateOrCreate(['code' => 'EUR'], ['prefix' => '€', 'suffix' => '', 'format' => 1, 'rate' => 0.5, 'is_default' => false]);

    $this->actingAs($staff, 'admin')->post(route('admin.panelica-licenses.connect'), ['api_key' => 'rk_live_secret_partner_key'])
        ->assertRedirect(route('admin.panelica-licenses.index'))
        ->assertSessionHas('success', __('panelica_license.page.connected', ['name' => 'Acme Hosting']));
    expect(PartnerAccount::apiKey())->toBe('rk_live_secret_partner_key');

    $page = $this->actingAs($staff, 'admin')->get(route('admin.panelica-licenses.index'))->assertOk()->getContent();
    expect($page)->toContain('Acme Hosting')
        ->and($page)->toContain('Business')
        ->and($page)->toContain(e(__('panelica_license.page.per_month', ['price' => '$8.00'])))
        ->and($page)->toContain(e(__('panelica_license.page.create_product')));

    $this->actingAs($staff, 'admin')->post(route('admin.panelica-licenses.products.store'), [
        'plan_id' => PL_PLAN, 'name' => 'Panelica Business', 'monthly' => '12.00', 'annually' => '120.00',
    ])->assertSessionHas('success');

    $product = Product::where('name', 'Panelica Business')->firstOrFail();
    expect($product->server_type)->toBe('panelicalicense')
        ->and($product->config_options['package_name'])->toBe(PL_PLAN)
        ->and($product->auto_setup)->toBe('payment')
        ->and($product->group->slug)->toBe('panelica-licenses')
        ->and($product->priceFor('monthly', $usd->id))->toBe(12.0)
        ->and($product->priceFor('annually', $eur->id))->toBe(60.0)
        ->and($product->priceFor('quarterly', $usd->id))->toBeNull();

    $page = $this->actingAs($staff, 'admin')->get(route('admin.panelica-licenses.index'))->getContent();
    expect($page)->toContain(route('admin.products.edit', $product))
        ->and($page)->toContain(e(__('panelica_license.page.per_month', ['price' => $usd->formatAmount(4.0)])));
});

test('a product needs a price and a plan Central lists', function () {
    $central = plCentral();
    plFake($central);
    plConnect();
    $staff = plStaff(['manage_products']);

    $this->actingAs($staff, 'admin')->post(route('admin.panelica-licenses.products.store'), ['plan_id' => PL_PLAN, 'name' => 'X'])
        ->assertSessionHas('error', __('panelica_license.page.price_needed'));
    $this->actingAs($staff, 'admin')->post(route('admin.panelica-licenses.products.store'), ['plan_id' => 'no-such-plan', 'name' => 'X', 'monthly' => 5])
        ->assertSessionHas('error', __('panelica_license.page.plan_unknown'));

    expect(Product::where('server_type', 'panelicalicense')->where('name', 'X')->exists())->toBeFalse();
});

test('the sales list shows what was sold, and disconnecting stops buying', function () {
    $central = plCentral();
    $service = plLicensed($central);
    $staff = plStaff(['manage_products']);

    $page = $this->actingAs($staff, 'admin')->get(route('admin.panelica-licenses.index'))->getContent();
    expect($page)->toContain('PNLC-AAAA-BBBB-CCCC-DDDD')
        ->and($page)->toContain(route('admin.services.show', $service));

    $this->actingAs($staff, 'admin')->post(route('admin.panelica-licenses.disconnect'))->assertSessionHas('success');
    $central['calls'] = [];
    Artisan::call('pnlcs:panelica-licenses');

    expect(PartnerAccount::connected())->toBeFalse()
        ->and($central['calls'])->toBe([])
        ->and(plModule()->renew($service->fresh())['message'])->toBe(__('panelica_license.error.no_account'));
});

test('licenses already bought are read back from Central, an hour apart', function () {
    $central = plCentral();
    $service = plLicensed($central);
    $central['license']['expires_at'] = '2028-01-01T00:00:00+00:00';
    $central['calls'] = [];

    Artisan::call('pnlcs:panelica-licenses');
    expect(plCalls($central, 'GET', '/licenses/'.PL_LICENSE))->toBe([]);

    $service->forceFill(['module_data' => array_merge($service->fresh()->module_data, ['checked_at' => now()->subHours(2)->toIso8601String()])])->save();
    Artisan::call('pnlcs:panelica-licenses');

    expect(plCalls($central, 'GET', '/licenses/'.PL_LICENSE))->toHaveCount(1)
        ->and($service->fresh()->module_data['license_expires_at'])->toBe('2028-01-01T00:00:00+00:00');
});
