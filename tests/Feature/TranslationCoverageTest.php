<?php

/*
 * What counts as translated, for the progress under Setup > Languages and for
 * "translate missing".
 *
 * Reported by an operator: German showed "74% translated" while almost every
 * German text on his install was English, and "translate missing" found
 * nearly nothing to translate - any non-empty text counted, English or not.
 */

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\DynamicTranslation;
use App\Models\Language;
use App\Models\Setting;
use App\Services\AiTranslationService;
use App\Translation\OfficialTranslationRepository;
use App\Translation\TranslationCoverage;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;

function coverageLanguage(string $code): Language
{
    return Language::updateOrCreate(['code' => $code], ['name' => strtoupper($code), 'native_name' => $code, 'is_active' => true]);
}

function aTranslatedGermanKey(): array
{
    $en = app(OfficialTranslationRepository::class)->forLocale('en');
    $de = app(OfficialTranslationRepository::class)->forLocale('de');
    foreach ($en['client'] as $key => $english) {
        if (($de['client'][$key] ?? $english) !== $english && ! TranslationCoverage::hasNoWords($english)) {
            return ['client', $key, $english];
        }
    }
    throw new RuntimeException('no translated German text found');
}

test('the shipped complete translations are complete, a stub that returns English is not', function () {
    $coverage = app(TranslationCoverage::class);

    foreach (['de', 'tr', 'pl', 'zh'] as $locale) {
        expect($coverage->percent($locale))->toBe(100.0);
    }
    // lang/fr returns the English files: counted as English now, not as French.
    expect($coverage->percent('fr'))->toBeLessThan(50.0);
});

test('a German text that is the English one counts as missing, and is what "translate missing" sends', function () {
    [$group, $key, $english] = aTranslatedGermanKey();
    // An English export imported over German: stored as the operator's text.
    DynamicTranslation::create(['language' => 'de', 'group' => $group, 'key' => $key,
        'value' => $english, 'is_auto_translated' => false, 'is_reviewed' => true]);

    $coverage = app(TranslationCoverage::class);
    expect($coverage->translated('de'))->not->toHaveKey("{$group}.{$key}")
        ->and($coverage->percent('de'))->toBeLessThan(100.0)
        ->and(app(AiTranslationService::class)->candidates('de', 'missing'))->toHaveKey("{$group}.{$key}");
});

test('a German text in German is never sent again', function () {
    [$group, $key] = aTranslatedGermanKey();

    expect(app(AiTranslationService::class)->candidates('de', 'missing'))->not->toHaveKey("{$group}.{$key}")
        ->and(app(TranslationCoverage::class)->translated('de'))->toHaveKey("{$group}.{$key}");
});

test('a text with no words to translate counts whatever it says', function () {
    expect(TranslationCoverage::hasNoWords('SSL'))->toBeTrue()
        ->and(TranslationCoverage::hasNoWords(':count'))->toBeTrue()
        ->and(TranslationCoverage::hasNoWords('<b>API</b> :name'))->toBeTrue()
        ->and(TranslationCoverage::hasNoWords('Status'))->toBeFalse()
        ->and(TranslationCoverage::hasNoWords('Ä'))->toBeTrue();
});

test('the English text given back by machine translation is kept, and not asked about again', function () {
    coverageLanguage('fr');
    Setting::set('OpenAIApiKey', 'sk-test-key', 'general');
    $missing = app(AiTranslationService::class)->candidates('fr', 'missing');
    $first = array_slice($missing, 0, AiTranslationService::BATCH_SIZE, true);

    // The model answers each text with the English one, as it does for names.
    Http::fake(['api.openai.com/*' => function (ClientRequest $request) {
        $items = json_decode($request->data()['messages'][1]['content'], true);

        return Http::response(['choices' => [['message' => ['content' => json_encode($items)]]]]);
    }]);
    $admin = Admin::factory()->create(['role_id' => AdminRole::factory()->fullAdmin()->create()->id]);
    $this->actingAs($admin, 'admin')
        ->postJson(route('admin.config.languages.ai-translate-batch', 'fr'), ['mode' => 'missing'])->assertOk();

    $after = app(AiTranslationService::class)->candidates('fr', 'missing');
    expect(array_intersect_key($after, $first))->toBe([])
        ->and(count($after))->toBe(count($missing) - count($first))
        ->and(DynamicTranslation::where('language', 'fr')->where('is_auto_translated', true)->count())->toBe(count($first));
});

test('the languages page shows the corrected progress', function () {
    coverageLanguage('fr');
    $admin = Admin::factory()->create(['role_id' => AdminRole::factory()->fullAdmin()->create()->id]);

    $this->actingAs($admin, 'admin')->get(route('admin.config.languages.index'))->assertOk();

    expect((float) Language::where('code', 'fr')->value('translation_progress'))->toBe(app(TranslationCoverage::class)->percent('fr'))
        ->and((float) Language::where('code', 'fr')->value('translation_progress'))->toBeLessThan(50.0);
});
