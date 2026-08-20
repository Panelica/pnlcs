<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Setting;

/*
 * The "finish setting up" checklist on the admin dashboard.
 *
 * Reported from a live install on 2026-08-20: an operator uploaded a logo and
 * the counter stayed at "0 of 5", while every line in the list appeared to
 * carry a tick. Both halves of that were real. The branding step needs a
 * company name as well as a logo - saved by a different form on the same
 * screen - and the tick character was printed for every step and merely hidden
 * with color:transparent, so copied text and screen readers announced five
 * completed steps next to a counter reading zero.
 */

function checklistAdmin(): Admin
{
    return Admin::factory()->create([
        'role_id' => AdminRole::factory()->fullAdmin()->create()->id,
    ]);
}

function dashboardChecklist(): array
{
    $response = test()->actingAs(checklistAdmin(), 'admin')->get(route('admin.dashboard'));
    $response->assertOk();

    return $response->viewData('setup');
}

test('a fresh installation has nothing ticked', function () {
    Setting::set('whitelabel_company_name', '', 'whitelabel');
    Setting::set('CompanyName', '', 'general');
    Setting::set('custom_logo_path', '', 'appearance');

    $setup = dashboardChecklist();

    expect($setup['done'])->toBe(0)
        ->and($setup['total'])->toBe(5)
        ->and($setup['complete'])->toBeFalse();
});

test('a logo on its own does not finish the branding step', function () {
    Setting::set('whitelabel_company_name', '', 'whitelabel');
    Setting::set('CompanyName', '', 'general');
    Setting::set('custom_logo_path', '/branding/logo_123.png', 'appearance');

    $setup = dashboardChecklist();
    $company = collect($setup['items'])->firstWhere('key', 'company');

    // This is the case that was reported. The step stays open, and it says the
    // name is what is missing rather than leaving the operator to guess.
    expect($company['done'])->toBeFalse()
        ->and($company['missing'])->toBe([__('admin.settings.company_name')])
        ->and($setup['done'])->toBe(0);
});

test('a name on its own does not finish it either, and says so', function () {
    Setting::set('whitelabel_company_name', 'MyHosting', 'whitelabel');
    Setting::set('CompanyName', '', 'general');
    Setting::set('custom_logo_path', '', 'appearance');

    $company = collect(dashboardChecklist()['items'])->firstWhere('key', 'company');

    expect($company['done'])->toBeFalse()
        ->and($company['missing'])->toBe([__('admin.settings.logo')]);
});

test('both together finish the branding step', function () {
    Setting::set('whitelabel_company_name', 'MyHosting', 'whitelabel');
    Setting::set('CompanyName', '', 'general');
    Setting::set('custom_logo_path', '/branding/logo_123.png', 'appearance');

    $setup = dashboardChecklist();
    $company = collect($setup['items'])->firstWhere('key', 'company');

    expect($company['done'])->toBeTrue()
        ->and($company['missing'])->toBe([])
        ->and($setup['done'])->toBe(1);
});

test('an unfinished step carries no tick in the markup', function () {
    Setting::set('whitelabel_company_name', '', 'whitelabel');
    Setting::set('CompanyName', '', 'general');
    Setting::set('custom_logo_path', '', 'appearance');

    $html = $this->actingAs(checklistAdmin(), 'admin')
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->getContent();

    // Nothing is done, so the tick character must not appear in the list at
    // all - not printed and hidden, simply absent.
    expect($html)->not->toContain('color:transparent;">&#10003;')
        ->and(substr_count($html, '&#10003;'))->toBe(0);
});

test('every step points at a page that exists', function () {
    foreach (dashboardChecklist()['items'] as $item) {
        // A checklist that links nowhere is worse than no checklist.
        expect(fn () => route($item['route']))->not->toThrow(Exception::class);
    }
});

test('the name on the general settings screen counts too', function () {
    // company_name() reads the white-label override first and falls back to the
    // general CompanyName, so both are the company's name as far as the rest of
    // the product is concerned. The checklist used to accept only the override,
    // which meant filling in the obvious field changed nothing on the dashboard.
    Setting::set('whitelabel_company_name', '', 'whitelabel');
    Setting::set('CompanyName', 'MyHosting', 'general');
    Setting::set('custom_logo_path', '/branding/logo_123.png', 'appearance');

    $company = collect(dashboardChecklist()['items'])->firstWhere('key', 'company');

    expect($company['done'])->toBeTrue()
        ->and($company['missing'])->toBe([]);
});

test('the step links at the screen that owns the missing half', function () {
    Setting::set('whitelabel_company_name', '', 'whitelabel');
    Setting::set('CompanyName', '', 'general');
    Setting::set('custom_logo_path', '', 'appearance');

    // No name yet: the name lives in the general settings.
    $company = collect(dashboardChecklist()['items'])->firstWhere('key', 'company');
    expect($company['route'])->toBe('admin.settings.general');

    // Name set, logo missing: the logo lives in appearance.
    Setting::set('CompanyName', 'MyHosting', 'general');
    $company = collect(dashboardChecklist()['items'])->firstWhere('key', 'company');
    expect($company['route'])->toBe('admin.settings.appearance');
});

test('the company name is not duplicated onto a third screen', function () {
    // There are two settings and one resolver already. Adding another field
    // that writes a third value would be one more place for them to disagree.
    $appearance = file_get_contents(resource_path('views/admin/settings/appearance.blade.php'));

    expect(substr_count($appearance, 'name="company_name"'))->toBe(1);
});
