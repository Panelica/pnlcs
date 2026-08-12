<?php

use App\Http\Controllers\Admin\TranslationController;
use App\Http\Middleware\RedirectToInstaller;
use App\Models\Admin;
use App\Models\DynamicTranslation;
use App\Models\Language;
use App\Models\Setting;
use App\Translation\OfficialTranslationRepository;

beforeEach(function () {
    $this->withoutMiddleware(RedirectToInstaller::class);
});

function configuredLanguage(string $code, bool $active, bool $default = false): Language
{
    return Language::updateOrCreate(['code' => $code], [
        'name' => $code === 'zh' ? 'Simplified Chinese' : 'English',
        'native_name' => $code === 'zh' ? '简体中文' : 'English',
        'flag_code' => $code === 'zh' ? 'cn' : 'gb',
        'direction' => 'ltr',
        'is_active' => $active,
        'is_default' => $default,
        'sort_order' => $code === 'en' ? 1 : 2,
    ]);
}

test('an active simplified Chinese locale can be selected and persisted', function () {
    configuredLanguage('en', true, true);
    configuredLanguage('zh', true);
    Setting::set('DefaultLanguage', 'en', 'language');

    $this->get(route('client.login', ['lang' => 'zh']))
        ->assertOk()
        ->assertSee('lang="zh"', false)
        ->assertSee('登录')
        ->assertSessionHas('locale', 'zh')
        ->assertCookie('pnlcs_locale', 'zh');
});

test('simplified Chinese remains unavailable until an administrator enables it', function () {
    configuredLanguage('en', true, true);
    configuredLanguage('zh', false);
    Setting::set('DefaultLanguage', 'en', 'language');

    $this->get(route('client.login', ['lang' => 'zh']))
        ->assertOk()
        ->assertSee('lang="en"', false)
        ->assertSessionHas('locale', 'en');
});

test('an administrator can save an active personal language', function () {
    configuredLanguage('en', true, true);
    configuredLanguage('zh', true);
    $admin = Admin::factory()->create(['language' => 'en']);

    $this->actingAs($admin, 'admin')->post(route('admin.my-account.update'), [
        'first_name' => $admin->first_name,
        'last_name' => $admin->last_name,
        'email' => $admin->email,
        'signature' => '',
        'language' => 'zh',
    ])->assertRedirect();

    expect($admin->fresh()->language)->toBe('zh');
    $this->assertEquals('zh', session('locale'));
});

test('official files provide the translation-management baseline', function () {
    $repository = app(OfficialTranslationRepository::class);
    $english = $repository->forLocale('en');
    $chinese = $repository->forLocale('zh');

    expect($chinese)->toHaveKeys(array_keys($english))
        ->and($repository->count('zh'))->toBe($repository->count('en'))
        ->and($chinese['auth']['login.title'])->toBe('登录');
});

test('export preserves existing database translations as overrides and additional keys', function () {
    DynamicTranslation::create([
        'language' => 'zh',
        'group' => 'auth',
        'key' => 'login.title',
        'value' => '现有登录翻译',
    ]);
    DynamicTranslation::create([
        'language' => 'zh',
        'group' => 'custom',
        'key' => 'database_only',
        'value' => '现有自定义翻译',
    ]);

    $response = app(TranslationController::class)->export(
        'zh',
        app(OfficialTranslationRepository::class),
    );
    $translations = $response->getData(true);

    expect($translations['auth']['login.title'])->toBe('现有登录翻译')
        ->and($translations['custom']['database_only'])->toBe('现有自定义翻译')
        ->and($translations['auth'])->toHaveKey('login.email');
});
