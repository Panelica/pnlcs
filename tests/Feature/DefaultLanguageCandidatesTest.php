<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Language;
use App\Models\Setting;

/*
 * The default-language list offered only languages already switched on.
 *
 * A fresh install switches on English alone, so Turkish, Polish and Chinese —
 * all fully translated — never appeared in the list, and an operator had to
 * find the switch on another tab first. Measured on billing.panelica.com: en
 * active, tr/pl/zh at 100% and inactive, absent from the select.
 */

function defaultLanguageAdmin(): Admin
{
    return Admin::factory()->create([
        'role_id' => AdminRole::factory()->fullAdmin()->create()->id,
    ]);
}

function seedLanguages(): void
{
    foreach ([
        ['en', 'English', 'English', true, true, 1],
        ['tr', 'Turkish', 'Türkçe', false, false, 2],
        ['fr', 'French', 'Français', false, false, 3],
    ] as [$code, $name, $native, $active, $default, $sort]) {
        Language::updateOrCreate(['code' => $code], [
            'name' => $name, 'native_name' => $native, 'direction' => 'ltr',
            'is_active' => $active, 'is_default' => $default, 'sort_order' => $sort,
        ]);
    }
}

/** The option values inside the default-language select, nothing else on the page. */
function defaultSelectCodes(string $html): array
{
    preg_match('#<select name="code"[^>]*>(.*?)</select>#s', $html, $select);
    preg_match_all('#<option value="([^"]+)"#', $select[1] ?? '', $codes);

    return $codes[1];
}

test('a fully translated language is offered as default while switched off', function () {
    seedLanguages();

    $html = $this->actingAs(defaultLanguageAdmin(), 'admin')
        ->get(route('admin.config.languages.index'))
        ->assertOk()
        ->getContent();

    // The page computes progress from the shipped files, so these are the
    // real numbers: Turkish is complete, French is not.
    expect((float) Language::where('code', 'tr')->value('translation_progress'))->toBe(100.0)
        ->and((float) Language::where('code', 'fr')->value('translation_progress'))->toBeLessThan(100.0);

    $codes = defaultSelectCodes($html);
    expect($codes)->toContain('en')
        ->and($codes)->toContain('tr')
        ->and($codes)->not->toContain('fr');
});

test('choosing it makes it the default and switches it on', function () {
    seedLanguages();
    $admin = defaultLanguageAdmin();
    $this->actingAs($admin, 'admin')->get(route('admin.config.languages.index'));

    $this->actingAs($admin, 'admin')
        ->post(route('admin.config.languages.set-default'), ['code' => 'tr'])
        ->assertSessionHasNoErrors();

    $tr = Language::where('code', 'tr')->first();
    expect($tr->is_default)->toBeTrue()
        ->and($tr->is_active)->toBeTrue()
        ->and(Language::where('code', 'en')->value('is_default'))->toBeFalse()
        ->and(Setting::get('DefaultLanguage'))->toBe('tr');
});

test('an unfinished, switched-off language cannot be posted as default', function () {
    seedLanguages();
    $admin = defaultLanguageAdmin();
    $this->actingAs($admin, 'admin')->get(route('admin.config.languages.index'));

    $this->actingAs($admin, 'admin')
        ->post(route('admin.config.languages.set-default'), ['code' => 'fr'])
        ->assertSessionHasErrors('code');

    expect(Language::where('code', 'fr')->value('is_default'))->toBeFalse()
        ->and(Language::where('code', 'en')->value('is_default'))->toBeTrue();
});
