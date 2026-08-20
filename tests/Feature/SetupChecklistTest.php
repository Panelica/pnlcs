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

test('a logo is all the branding step asks for', function () {
    Setting::set('whitelabel_company_name', '', 'whitelabel');
    Setting::set('CompanyName', '', 'general');
    Setting::set('custom_logo_path', '/branding/logo_123.png', 'appearance');

    // This step once demanded the company name too, but the seeder gives every
    // installation a CompanyName and the install wizard asks for a name
    // besides - the name half was always satisfied, so it only ever confused.
    $setup = dashboardChecklist();
    $company = collect($setup['items'])->firstWhere('key', 'company');

    expect($company['done'])->toBeTrue()
        ->and($setup['done'])->toBe(1);
});

test('no logo means the step stays open, whatever the name says', function () {
    Setting::set('whitelabel_company_name', 'MyHosting', 'whitelabel');
    Setting::set('CompanyName', 'MyHosting Ltd', 'general');
    Setting::set('custom_logo_path', '', 'appearance');

    $company = collect(dashboardChecklist()['items'])->firstWhere('key', 'company');

    expect($company['done'])->toBeFalse()
        ->and($company['route'])->toBe('admin.settings.appearance');
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



test('the company name is not duplicated onto a third screen', function () {
    // There are two settings and one resolver already. Adding another field
    // that writes a third value would be one more place for them to disagree.
    $appearance = file_get_contents(resource_path('views/admin/settings/appearance.blade.php'));

    expect(substr_count($appearance, 'name="company_name"'))->toBe(1);
});
