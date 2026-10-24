<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Language;
use App\Models\Setting;

/**
 * The default language belongs to the languages screen, which stores it under
 * the "language" group. General Settings shows the same choice but read only
 * its own group, so it found no value, selected no option, and the browser
 * posted the first language in the list: the first save of General Settings
 * changed the shop's language.
 */
function generalLanguageAdmin(): Admin
{
    return Admin::factory()->create([
        'role_id' => AdminRole::factory()->fullAdmin()->create()->id,
    ]);
}

function generalLanguageShop(): void
{
    foreach ([['en', 'English', false, 1], ['tr', 'Türkçe', true, 2]] as [$code, $name, $default, $sort]) {
        Language::updateOrCreate(['code' => $code], [
            'name' => $name, 'native_name' => $name, 'direction' => 'ltr',
            'is_active' => true, 'is_default' => $default, 'sort_order' => $sort,
        ]);
    }
    Setting::set('DefaultLanguage', 'tr', 'language');
}

test('general settings selects the default language the languages screen stored', function () {
    generalLanguageShop();

    $html = $this->actingAs(generalLanguageAdmin(), 'admin')
        ->get(route('admin.settings.general'))->assertOk()->getContent();

    preg_match('#<select name="DefaultLanguage"[^>]*>(.*?)</select>#s', $html, $select);
    expect($select[1] ?? '')->toMatch('#<option value="tr"\s+selected#');
});

test('saving general settings with the language unchanged leaves it where it is', function () {
    generalLanguageShop();

    $this->actingAs(generalLanguageAdmin(), 'admin')
        ->post(route('admin.settings.general.update'), ['DefaultLanguage' => 'tr', 'CompanyName' => 'Shop'])
        ->assertRedirect();

    $row = Setting::where('setting', 'DefaultLanguage')->first();
    expect($row->value)->toBe('tr')
        ->and($row->group)->toBe('language')
        ->and(Language::where('code', 'tr')->value('is_default'))->toBeTrue();
});

test('choosing another language on general settings changes it the way the languages screen does', function () {
    generalLanguageShop();

    $this->actingAs(generalLanguageAdmin(), 'admin')
        ->post(route('admin.settings.general.update'), ['DefaultLanguage' => 'en'])
        ->assertRedirect();

    expect(Setting::get('DefaultLanguage'))->toBe('en')
        ->and(Setting::where('setting', 'DefaultLanguage')->value('group'))->toBe('language')
        ->and(Language::where('code', 'en')->value('is_default'))->toBeTrue()
        ->and(Language::where('code', 'tr')->value('is_default'))->toBeFalse();
});

test('a language that cannot be the default is refused', function () {
    generalLanguageShop();
    Language::updateOrCreate(['code' => 'fr'], [
        'name' => 'French', 'native_name' => 'Français', 'direction' => 'ltr',
        'is_active' => false, 'is_default' => false, 'sort_order' => 3, 'translation_progress' => 10,
    ]);

    $this->actingAs(generalLanguageAdmin(), 'admin')
        ->post(route('admin.settings.general.update'), ['DefaultLanguage' => 'fr'])
        ->assertSessionHasErrors('DefaultLanguage');

    expect(Setting::get('DefaultLanguage'))->toBe('tr');
});
