<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Services\AdminSettingsIndex;

/*
 * The admin bar's search also finds settings sections and fields (and, in
 * the browser, the menu's pages). Settings > General has eighteen sections
 * and some sixty fields; the only way to one was to scroll.
 */

function aspAdmin(array $permissions): Admin
{
    return Admin::factory()->create(['role_id' => AdminRole::factory()->create(['name' => 'R'.uniqid(), 'permissions' => $permissions])->id]);
}

function aspSettings(Admin $admin, string $q): array
{
    $groups = test()->actingAs($admin, 'admin')->getJson(route('admin.search', ['q' => $q]))->assertOk()->json('groups');

    return collect($groups)->firstWhere('type', 'settings')['items'] ?? [];
}

it('finds a settings section by its name and links to it', function () {
    $items = aspSettings(aspAdmin(['manage_settings']), 'reCAPTCHA');

    expect($items)->not->toBeEmpty()
        ->and($items[0]['url'])->toBe(route('admin.settings.general').'#settings-recaptcha');
});

it('finds a section by one of its fields', function () {
    $items = aspSettings(aspAdmin(['manage_settings']), __('admin.settings.registrar_watch_module'));

    expect(collect($items)->pluck('url')->all())->toContain(route('admin.settings.general').'#settings-registrar_balance');
});

it('answers in the admin\'s language', function () {
    app()->setLocale('tr');
    $items = app(AdminSettingsIndex::class)->search(__('admin.settings.late_fees'));

    expect($items[0]['title'])->toContain(__('admin.settings.late_fees'));
});

it('shows settings only to an admin who may change them', function () {
    expect(aspSettings(aspAdmin(['view_clients']), 'reCAPTCHA'))->toBe([]);
});

it('knows every section of the settings page, each with an anchor', function () {
    $view = file_get_contents(resource_path('views/admin/settings/general.blade.php'));
    $headers = substr_count($view, '<div class="card-header"><strong>{{ __(\'admin.settings.');

    $cards = app(AdminSettingsIndex::class)->cards();
    expect(count($cards))->toBe($headers);

    foreach ($cards as $card) {
        expect($view)->toContain('id="'.$card['anchor'].'"');
    }
});

it('carries the menu search into every admin page', function () {
    test()->actingAs(aspAdmin([]), 'admin')->get(route('admin.dashboard'))
        ->assertOk()->assertSee('navbar-collapse .dropdown-menu a[href]', false)->assertSee(json_encode(__('admin.search.pages')), false);
});
