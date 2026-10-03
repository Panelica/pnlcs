<?php

use App\Console\Commands\RegistrarBalanceCheckCommand;
use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\RegistrarSettings;
use App\Models\Setting;
use App\Widgets\RegistrarBalanceWidget;
use Illuminate\Support\Facades\Http;

/*
 * The registrar balance on the dashboard.
 *
 * pnlcs:registrar-balance read the balance twice a day and only mailed when it
 * was low; the figure itself was shown nowhere, and with no mail set up the
 * warning reached nobody.
 */

function rbConfigure(): void
{
    RegistrarSettings::updateOrCreate(['registrar' => 'domainnameapi', 'setting' => 'reseller_id'], ['value' => 'reseller-1']);
    RegistrarSettings::updateOrCreate(['registrar' => 'domainnameapi', 'setting' => 'api_key'], ['value' => 'key-1']);
    Setting::set('RegistrarBalanceThreshold', '1000', 'general');
    Setting::set('RegistrarBalanceCurrency', 'TRY', 'general');
}

it('keeps the last reading and shows it on the dashboard widget', function () {
    rbConfigure();
    Http::fake(['*deposit/accounts/me*' => Http::response(['tryBalance' => 2500.5, 'usdBalance' => 72.1])]);

    test()->artisan('pnlcs:registrar-balance')->assertSuccessful();

    $last = RegistrarBalanceCheckCommand::last();
    expect($last['ok'])->toBeTrue()->and((float) $last['amount'])->toBe(2500.5)->and((float) $last['usd'])->toBe(72.1);

    $html = (new RegistrarBalanceWidget)->render((new RegistrarBalanceWidget)->getData());
    expect($html)->toContain('2,500.50 TRY')->toContain('72.10 USD')->not->toContain(__('admin.dashboard.balance_low'));
});

it('marks a balance at or below the floor', function () {
    rbConfigure();
    Http::fake(['*deposit/accounts/me*' => Http::response(['tryBalance' => 400, 'usdBalance' => 11])]);

    test()->artisan('pnlcs:registrar-balance');

    expect((new RegistrarBalanceWidget)->render((new RegistrarBalanceWidget)->getData()))->toContain(__('admin.dashboard.balance_low'));
});

it('says when the balance could not be read', function () {
    rbConfigure();
    Http::fake(['*deposit/accounts/me*' => Http::response(['error' => ['message' => 'Unauthorized']], 401)]);

    test()->artisan('pnlcs:registrar-balance');

    expect(RegistrarBalanceCheckCommand::last()['ok'])->toBeFalse()
        ->and((new RegistrarBalanceWidget)->render((new RegistrarBalanceWidget)->getData()))->toContain(route('admin.config.registrar-balance.check'));
});

it('lets staff who manage registrars check now, and shows the widget on the dashboard', function () {
    rbConfigure();
    Http::fake(['*deposit/accounts/me*' => Http::response(['tryBalance' => 1500, 'usdBalance' => 43])]);
    $admin = Admin::factory()->create(['role_id' => AdminRole::factory()->create(['name' => 'R'.uniqid(), 'permissions' => ['manage_registrars']])->id]);

    test()->actingAs($admin, 'admin')->post(route('admin.config.registrar-balance.check'))->assertSessionHas('success');
    expect((float) RegistrarBalanceCheckCommand::last()['amount'])->toBe(1500.0);
    test()->actingAs($admin, 'admin')->get(route('admin.dashboard'))->assertOk()->assertSee('1,500.00 TRY');

    $other = Admin::factory()->create(['role_id' => AdminRole::factory()->create(['name' => 'R'.uniqid(), 'permissions' => ['list_clients']])->id]);
    test()->actingAs($other, 'admin')->post(route('admin.config.registrar-balance.check'))->assertForbidden();
});
