<?php

use App\Models\Admin;
use App\Models\Setting;
use App\Providers\AddonServiceProvider;
use App\Services\AddonManager;
use Modules\Addons\BankPayments\BankPaymentsModule;

beforeEach(fn () => ensureBankPaymentsTables());

function bootBankPaymentsAddon(): void
{
    Setting::set('addon_bank_payments_active', '1', 'addons');
    app()->forgetInstance(AddonManager::class);
    (new AddonServiceProvider(app()))->boot();
    app('router')->getRoutes()->refreshNameLookups();
}

it('discovers the addon through the addon manager', function () {
    app()->forgetInstance(AddonManager::class);
    $manager = app(AddonManager::class);

    expect($manager->find('bank_payments'))->toBeInstanceOf(BankPaymentsModule::class);
});

it('can be activated once its tables exist', function () {
    Setting::set('addon_bank_payments_active', '0', 'addons');
    app()->forgetInstance(AddonManager::class);

    $result = app(AddonManager::class)->activate('bank_payments');

    expect($result['success'])->toBeTrue()
        ->and(app(AddonManager::class)->isActive('bank_payments'))->toBeTrue();
});

it('provides its admin page and billing link while active', function () {
    bootBankPaymentsAddon();
    app()->setLocale('pl');

    $admin = Admin::factory()->create(['language' => 'pl']);

    $response = $this->actingAs($admin, 'admin')
        ->get(route('admin.bank-payments.index'));

    $response->assertOk()
        ->assertSee('Płatności bankowe')
        ->assertSee('href="'.route('admin.bank-payments.index').'"', false);

    $module = app(AddonManager::class)->find('bank_payments');
    $items = app(AddonManager::class)->getAdminMenuItems();

    expect($module->sidebar())->toBe([])
        ->and($items)->toHaveKey('billing')
        ->and($items)->not->toHaveKey('extensions')
        ->and($items['billing'][0]['url'])->toBe(route('admin.bank-payments.index'));
});
