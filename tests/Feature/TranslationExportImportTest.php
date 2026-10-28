<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\DynamicTranslation;
use App\Models\Language;
use App\Translation\OfficialTranslationRepository;
use App\Translation\TranslationCoverage;
use Illuminate\Http\UploadedFile;

/*
 * #324. A language is completed through Export and Import: the file has every
 * text English has, the ones the language lacks left empty; Import leaves out
 * keys PNLCS does not have and says which; a text kept the same as English on
 * purpose (a brand name) can be marked so, and then counts as translated.
 */

function teiAdmin(): Admin
{
    return Admin::factory()->create(['role_id' => AdminRole::factory()->fullAdmin()->create()->id]);
}

function teiFrench(): Language
{
    return Language::updateOrCreate(['code' => 'fr'], ['name' => 'French', 'native_name' => 'Français', 'is_active' => true]);
}

/** @return array<string, string> "group.key" => text */
function teiFlat(array $groups): array
{
    $flat = [];
    foreach ($groups as $group => $keys) {
        foreach ((array) $keys as $key => $value) {
            $flat[$group.'.'.$key] = $value;
        }
    }

    return $flat;
}

/** A key English has and the language's own files do not. */
function teiMissingKey(string $locale): array
{
    $own = teiFlat(app(OfficialTranslationRepository::class)->forLocale($locale));
    foreach (teiFlat(app(OfficialTranslationRepository::class)->forLocale('en')) as $fullKey => $english) {
        if (! isset($own[$fullKey]) && is_string($english) && ! TranslationCoverage::hasNoWords($english)) {
            return [...explode('.', $fullKey, 2), $english];
        }
    }

    throw new RuntimeException("{$locale} has every English key");
}

function teiImport(Admin $admin, string $locale, array $data)
{
    $file = UploadedFile::fake()->createWithContent("{$locale}.json", json_encode($data, JSON_UNESCAPED_UNICODE));

    return test()->actingAs($admin, 'admin')->post(route('admin.config.languages.import', $locale), ['file' => $file]);
}

test('the export has every English key, with the ones the language lacks left empty', function () {
    teiFrench();
    [$group, $key] = teiMissingKey('fr');

    $data = json_decode($this->actingAs(teiAdmin(), 'admin')
        ->get(route('admin.config.languages.export', 'fr'))->assertOk()->getContent(), true);

    expect(array_keys(teiFlat($data)))->toEqualCanonicalizing(array_keys(teiFlat(app(OfficialTranslationRepository::class)->forLocale('en'))))
        ->and($data[$group][$key])->toBe('');
});

test('a filled-in export imported back completes the language', function () {
    teiFrench();
    $admin = teiAdmin();
    [$group, $key] = teiMissingKey('fr');
    $data = json_decode($this->actingAs($admin, 'admin')->get(route('admin.config.languages.export', 'fr'))->getContent(), true);

    $data[$group][$key] = 'Texte en français';
    teiImport($admin, 'fr', $data)->assertRedirect()->assertSessionHas('success');

    expect(isset(app(TranslationCoverage::class)->translated('fr')[$group.'.'.$key]))->toBeTrue();
});

test('"export missing" gives only what is not yet translated', function () {
    teiFrench();
    $admin = teiAdmin();
    [$group, $key] = teiMissingKey('fr');
    DynamicTranslation::create(['language' => 'fr', 'group' => $group, 'key' => $key, 'value' => 'Déjà traduit', 'is_reviewed' => true]);

    $response = $this->actingAs($admin, 'admin')->get(route('admin.config.languages.export', ['fr', 'missing' => 1]))->assertOk();
    $flat = teiFlat(json_decode($response->getContent(), true));
    $translated = app(TranslationCoverage::class)->translated('fr');

    expect($response->headers->get('Content-Disposition'))->toContain('fr-missing.json')
        ->and($flat)->not->toBeEmpty()->not->toHaveKey($group.'.'.$key)
        ->and(array_intersect_key($flat, $translated))->toBe([]);
});

test('import leaves out keys PNLCS does not have and names them', function () {
    teiFrench();
    $admin = teiAdmin();
    [$group, $key] = teiMissingKey('fr');

    teiImport($admin, 'fr', [$group => [$key => 'Bonjour', 'no.such.key' => 'Rien'], 'nogroup' => ['x' => 'y']])
        ->assertRedirect()
        ->assertSessionHas('success', fn ($message) => str_contains($message, '2 keys PNLCS does not have') && str_contains($message, 'nogroup.x'));

    expect(DynamicTranslation::where(['language' => 'fr', 'group' => $group, 'key' => $key])->value('value'))->toBe('Bonjour')
        ->and(DynamicTranslation::where('language', 'fr')->whereIn('key', ['no.such.key', 'x'])->exists())->toBeFalse();
});

test('a text kept the same as English on purpose counts as translated, and travels through the file', function () {
    teiFrench();
    $admin = teiAdmin();
    [$group, $key, $english] = teiMissingKey('fr');

    $this->actingAs($admin, 'admin')->post(route('admin.config.languages.bulk-save', 'fr'), ['translations' => [
        ['group' => $group, 'key' => $key, 'value' => '', 'same' => '1'],
    ]])->assertRedirect();

    $row = DynamicTranslation::where(['language' => 'fr', 'group' => $group, 'key' => $key])->first();
    expect($row->value)->toBe($english)
        ->and($row->same_as_english)->toBeTrue()
        ->and(isset(app(TranslationCoverage::class)->translated('fr')[$group.'.'.$key]))->toBeTrue();

    $this->actingAs($admin, 'admin')->get(route('admin.config.languages.translations', ['fr', 'group' => $group, 'search' => $key]))
        ->assertOk()->assertSee('name="translations[0][same]" value="1" checked', false);

    $data = json_decode($this->actingAs($admin, 'admin')->get(route('admin.config.languages.export', 'fr'))->getContent(), true);
    expect($data['_same_as_english'])->toBe([$group.'.'.$key]);

    // A fresh install takes the mark from the file.
    DynamicTranslation::where(['language' => 'fr', 'group' => $group, 'key' => $key])->delete();
    teiImport($admin, 'fr', $data)->assertRedirect();
    expect(DynamicTranslation::where(['language' => 'fr', 'group' => $group, 'key' => $key])->value('same_as_english'))->toBeTrue();

    // Unticked, the text is the English one again and no longer counts.
    $this->actingAs($admin, 'admin')->post(route('admin.config.languages.bulk-save', 'fr'), ['translations' => [
        ['group' => $group, 'key' => $key, 'value' => $english],
    ]]);
    expect(isset(app(TranslationCoverage::class)->translated('fr')[$group.'.'.$key]))->toBeFalse();
});

test('a text that lives only in the database, an addon\'s own, is exported and imported as before', function () {
    teiFrench();
    $admin = teiAdmin();
    DynamicTranslation::create(['language' => 'en', 'group' => 'teiaddon', 'key' => 'title', 'value' => 'My addon', 'is_reviewed' => true]);
    DynamicTranslation::create(['language' => 'fr', 'group' => 'teiaddon', 'key' => 'title', 'value' => 'Mon module', 'is_reviewed' => true]);

    $data = json_decode($this->actingAs($admin, 'admin')->get(route('admin.config.languages.export', 'fr'))->getContent(), true);
    expect($data['teiaddon']['title'])->toBe('Mon module');

    $data['teiaddon']['title'] = 'Mon extension';
    teiImport($admin, 'fr', $data)->assertSessionHas('success', fn ($message) => ! str_contains($message, 'teiaddon'));
    expect(DynamicTranslation::where(['language' => 'fr', 'group' => 'teiaddon', 'key' => 'title'])->value('value'))->toBe('Mon extension');
});
