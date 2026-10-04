<?php

use App\Console\Commands\RegistrarBalanceCheckCommand;
use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\RegistrarSettings;
use App\Models\Setting;
use App\Widgets\RegistrarBalanceWidget;
use Illuminate\Support\Facades\Http;

/*
 * The registrar balance watch, switched off and read back.
 *
 * Saving the module field blank switched the watch off, as the hint said, but
 * the widget then read "the registrar is not set up yet" - on an install whose
 * registrar was set up and selling. The field was free text with
 * "domainnameapi" as its placeholder, so a blank field looked filled in. And a
 * registrar's second wallet showed as "· 0.00 USD", read as the balance
 * converted to dollars.
 */

function rwConfigure(): void
{
    RegistrarSettings::updateOrCreate(['registrar' => 'domainnameapi', 'setting' => 'reseller_id'], ['value' => 'reseller-1']);
    RegistrarSettings::updateOrCreate(['registrar' => 'domainnameapi', 'setting' => 'api_key'], ['value' => 'key-1']);
    Setting::set('RegistrarBalanceThreshold', '1000', 'general');
    Setting::set('RegistrarBalanceCurrency', 'TRY', 'general');
}

it('says the watch is off, not that the registrar is not set up', function () {
    rwConfigure();
    Setting::set('BalanceWatchRegistrar', null, 'general');
    Http::fake();

    test()->artisan('pnlcs:registrar-balance')->assertSuccessful();
    Http::assertNothingSent();

    $html = (new RegistrarBalanceWidget)->render((new RegistrarBalanceWidget)->getData());
    expect($html)->toContain(e(__('admin.dashboard.balance_watch_off')))
        ->not->toContain(e(__('admin.dashboard.balance_not_configured')))
        ->toContain(route('admin.settings.general').'#settings-registrar_balance');
});

it('offers the registrars that report a balance, and Off, on the settings screen', function () {
    Setting::set('BalanceWatchRegistrar', null, 'general');
    $admin = Admin::factory()->create(['role_id' => AdminRole::factory()->create(['name' => 'R'.uniqid(), 'permissions' => ['manage_settings']])->id]);

    expect(RegistrarBalanceCheckCommand::watchable())->toContain('domainnameapi');

    $html = test()->actingAs($admin, 'admin')->get(route('admin.settings.general'))->assertOk()->getContent();
    expect($html)->toMatch('#<select name="BalanceWatchRegistrar"[^>]*>\s*<option value="">'.preg_quote(e(__('admin.settings.registrar_watch_off')), '#').'</option>#')
        ->toContain('<option value="domainnameapi"');
});

it('names a second wallet as a wallet, and leaves it out while empty', function () {
    rwConfigure();
    Setting::set('BalanceWatchRegistrar', 'domainnameapi', 'general');

    Http::fake(['*deposit/accounts/me*' => Http::sequence()
        ->push(['tryBalance' => 2500, 'usdBalance' => 0])
        ->push(['tryBalance' => 2500, 'usdBalance' => 12.5])]);
    test()->artisan('pnlcs:registrar-balance')->assertSuccessful();
    $html = (new RegistrarBalanceWidget)->render((new RegistrarBalanceWidget)->getData());
    expect($html)->toContain('2,500.00 TRY')->not->toContain('USD');

    test()->artisan('pnlcs:registrar-balance')->assertSuccessful();
    $html = (new RegistrarBalanceWidget)->render((new RegistrarBalanceWidget)->getData());
    expect($html)->toContain(e(__('admin.dashboard.balance_usd_wallet', ['amount' => '12.50'])));
});
