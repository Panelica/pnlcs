<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Setting;
use App\Services\ThemeService;

/*
 * The custom theme's tokens are printed as CSS custom properties in a <style>
 * block on every layout. Only colour tokens were checked, and only to lower-case
 * a valid hex; any other value - and an invalid colour - was saved and printed
 * as typed, so one stray character could break the styling of every page.
 */

function themeStaff(): Admin
{
    return Admin::factory()->create(['role_id' => AdminRole::factory()->create(['name' => 'Look '.uniqid(), 'permissions' => ['manage_settings']])->id]);
}

/** @param array<string, string> $over */
function customTokens(array $over = []): array
{
    return ['preset' => 'custom', 'colors' => array_merge(ThemeService::getPresets()['starter']['colors'], $over)];
}

test('every built-in preset value is a valid token', function () {
    foreach (ThemeService::getPresets() as $name => $preset) {
        foreach ($preset['colors'] as $key => $value) {
            expect(ThemeService::isValidTokenValue($key, $value))->toBeTrue("{$name}.{$key} = {$value}");
        }
    }
});

test('a custom theme with ordinary values is saved and printed', function () {
    $this->actingAs(themeStaff(), 'admin')
        ->post(route('admin.settings.appearance.update'), customTokens([
            'primary' => '#AA3366', 'card_shadow' => 'rgba(0, 0, 0, 0.1)', 'font_family' => "'Inter', sans-serif",
            'shadow_md' => '0 4px 15px rgba(0,0,0,0.12)', 'radius_md' => '10px',
        ]))
        ->assertSessionHasNoErrors();

    $css = ThemeService::generateCssVariables(json_decode(Setting::get('active_theme'), true));
    expect($css)->toContain('--theme-primary: #aa3366;')
        ->and($css)->toContain("--theme-font-family: 'Inter', sans-serif;")
        ->and($css)->toContain('--theme-shadow-md: 0 4px 15px rgba(0,0,0,0.12);');
});

test('a value that is not one CSS value is refused and nothing is saved', function (string $key, string $value) {
    Setting::set('active_theme', json_encode(ThemeService::getPresets()['starter']['colors']), 'appearance');

    $this->actingAs(themeStaff(), 'admin')
        ->from(route('admin.settings.appearance'))
        ->post(route('admin.settings.appearance.update'), customTokens([$key => $value]))
        ->assertSessionHasErrors("colors.{$key}");

    expect(json_decode(Setting::get('active_theme'), true)[$key])->toBe(ThemeService::getPresets()['starter']['colors'][$key]);
})->with([
    'colour that is not a colour' => ['primary', 'red; color: blue'],
    'declaration end' => ['radius_md', '4px; }'],
    'closing the style block' => ['font_family', 'Inter</style>'],
    'loading a file' => ['shadow_sm', 'url(https://example.com/x.png)'],
    'comment' => ['spacing_md', '8px /* x */'],
    'backslash' => ['font_family', 'Inter\\'],
    'bracket left open' => ['shadow_md', '0 4px 15px rgba(0,0,0,0.12'],
    'bracket closed first' => ['radius_md', '4px) ('],
    'quote left open' => ['font_family', "'Inter, sans-serif"],
    'colour function that is not one' => ['primary', 'expression(alert(1))'],
]);

/*
 * The check also runs when the tokens are printed, so a value it refuses
 * disappears from every page of a site that saved it before. Values that work
 * in CSS stay.
 */
test('a value that works in CSS is accepted, saved and printed', function (string $key, string $value) {
    expect(ThemeService::isValidTokenValue($key, $value))->toBeTrue();

    $this->actingAs(themeStaff(), 'admin')
        ->post(route('admin.settings.appearance.update'), customTokens([$key => $value]))
        ->assertSessionHasNoErrors();

    expect(ThemeService::generateCssVariables(json_decode(Setting::get('active_theme'), true)))
        ->toContain('--theme-'.str_replace('_', '-', $key).': '.$value.';');
})->with([
    'calc() with a division' => ['radius_md', 'calc(100% / 3)'],
    'calc() with a product' => ['spacing_md', 'calc(1rem * 2)'],
    'font named in Chinese' => ['font_family', "'微软雅黑', sans-serif"],
    'font name containing "import"' => ['font_family', "'Important Sans', serif"],
    'oklch() colour' => ['primary', 'oklch(0.6 0.15 250)'],
    'hsl() with an angle' => ['primary', 'hsl(210deg 50% 40%)'],
    'color-mix()' => ['primary', 'color-mix(in srgb, #2563eb 80%, white)'],
    'var()' => ['primary', 'var(--brand)'],
]);

test('values stored before the check are left out of the page, not printed', function () {
    $css = ThemeService::generateCssVariables(['primary' => '#123456', 'radius_md' => '4px;}body{display:none', 'font_family' => 'Inter</style>']);

    expect($css)->toContain('--theme-primary: #123456;')
        ->and($css)->not->toContain('display:none')
        ->and($css)->not->toContain('</style>');
});
