<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DynamicTranslation;
use App\Models\Language;
use App\Models\Setting;
use App\Services\AiTranslationService;
use App\Translation\OfficialTranslationRepository;
use App\Translation\TranslationCoverage;
use App\Translation\TranslationCacheManager;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;

class TranslationController extends Controller
{
    /** Where an exported file lists the texts kept the same as English on purpose (#324). */
    public const SAME_AS_ENGLISH = '_same_as_english';

    public function index(TranslationCoverage $coverage)
    {
        $languages = Language::orderBy('sort_order')->get();

        // A text counts as translated only when it is not simply the English
        // one (TranslationCoverage): a language full of English used to show
        // a high percentage.
        $totalKeys = count($coverage->english());
        foreach ($languages as $lang) {
            $lang->translation_progress = $lang->code === 'en' ? 100 : $coverage->percent($lang->code);
            $lang->save();
        }

        $defaultCandidates = Language::eligibleAsDefault()->orderBy('sort_order')->get();

        return view('admin.config.languages.index', compact('languages', 'totalKeys', 'defaultCandidates'));
    }

    public function toggle(Language $language)
    {
        if ($language->is_default && $language->is_active) {
            return back()->with('error', __('messages.error.cannot_delete_default', ['item' => 'language']));
        }
        $language->update(['is_active' => ! $language->is_active]);
        TranslationCacheManager::flush();

        return back()->with('success', $language->is_active ? __('messages.success.enabled', ['item' => $language->name]) : __('messages.success.disabled', ['item' => $language->name]));
    }

    public function setDefault(Request $request)
    {
        $request->validate(['code' => ['required', 'string', \Illuminate\Validation\Rule::in(
            Language::eligibleAsDefault()->pluck('code')->all()
        )]]);
        Language::where('is_default', true)->update(['is_default' => false]);
        Language::where('code', $request->code)->update(['is_default' => true, 'is_active' => true]);
        Setting::set('DefaultLanguage', $request->code, 'language');
        TranslationCacheManager::flush();

        return back()->with('success', __('messages.success.settings_saved'));
    }

    /**
     * Language in the address (App\Support\LocaleUrl): the default language
     * keeps today's addresses, every other active language gets its code in
     * front (/en/client/store).
     */
    public function updateUrlSetting(Request $request)
    {
        Setting::set(\App\Support\LocaleUrl::SETTING, $request->boolean('locale_urls') ? '1' : '0', 'language');
        \App\Support\LocaleUrl::forget();

        return back()->with('success', __('messages.success.settings_saved'));
    }

    public function translations(string $locale, OfficialTranslationRepository $officialTranslations, AiTranslationService $ai)
    {
        $language = Language::where('code', $locale)->firstOrFail();

        $english = $officialTranslations->forLocale('en');
        $rows = collect();
        foreach ($english as $group => $keys) {
            foreach ($keys as $key => $value) {
                $rows->push((object) compact('group', 'key', 'value'));
            }
        }

        if ($group = request('group')) {
            $rows = $rows->where('group', $group);
        }
        if ($search = request('search')) {
            $needle = mb_strtolower($search);
            $rows = $rows->filter(fn ($row) => str_contains(mb_strtolower($row->key.' '.$row->value), $needle));
        }

        $rows = $rows->sortBy(fn ($row) => $row->group."\0".$row->key)->values();
        $page = max(1, (int) request('page', 1));
        $englishKeys = new LengthAwarePaginator(
            $rows->forPage($page, 50)->values(),
            $rows->count(),
            50,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );

        $targetTranslations = [];
        foreach ($officialTranslations->forLocale($locale) as $group => $keys) {
            foreach ($keys as $key => $value) {
                $targetTranslations[$group.'.'.$key] = $value;
            }
        }

        // Database values are optional site-specific overrides.
        $overrides = DynamicTranslation::where('language', $locale)
            ->whereNotNull('value')->where('value', '!=', '')
            ->get(['group', 'key', 'value', 'is_auto_translated', 'same_as_english']);
        $autoTranslated = [];
        $sameAsEnglish = [];
        foreach ($overrides as $row) {
            $targetTranslations[$row->group.'.'.$row->key] = $row->value;
            if ($row->is_auto_translated) {
                $autoTranslated[$row->group.'.'.$row->key] = true;
            }
            if ($row->same_as_english) {
                $sameAsEnglish[$row->group.'.'.$row->key] = true;
            }
        }

        // What each "Translate with AI" mode would work through, shown before
        // it starts so the operator knows the size (and the cost) of the run.
        $aiCounts = $locale === 'en' ? [] : [
            'missing' => count($ai->candidates($locale, 'missing')),
            'all' => count($ai->candidates($locale, 'all')),
        ];
        $aiConfigured = $ai->configured();
        $aiModel = $ai->model();

        $groups = collect(array_keys($english))->sort()->values();

        $filter = request('filter', 'all');

        return view('admin.config.languages.translations', compact(
            'language', 'englishKeys', 'targetTranslations', 'groups', 'locale', 'filter',
            'autoTranslated', 'sameAsEnglish', 'aiCounts', 'aiConfigured', 'aiModel'
        ));
    }

    public function saveTranslation(Request $request, string $locale, OfficialTranslationRepository $officialTranslations)
    {
        $request->validate([
            'group' => 'required|string',
            'key' => 'required|string',
            'value' => 'nullable|string',
            'same' => 'nullable|boolean',
        ]);

        $this->storeOverride($locale, $request->group, $request->key, (string) $request->value, $officialTranslations->forLocale($locale),
            $request->boolean('same') ? $this->englishText($officialTranslations, $request->group, $request->key) : null);

        TranslationCacheManager::flushKey($locale, $request->group);

        if ($request->ajax()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', __('messages.success.saved'));
    }

    public function bulkSave(Request $request, string $locale, OfficialTranslationRepository $officialTranslations)
    {
        $translations = $request->input('translations', []);
        $official = $officialTranslations->forLocale($locale);
        $count = 0;

        foreach ($translations as $item) {
            if (empty($item['group']) || empty($item['key'])) {
                continue;
            }
            $same = ! empty($item['same']) ? $this->englishText($officialTranslations, $item['group'], $item['key']) : null;
            if ($this->storeOverride($locale, $item['group'], $item['key'], (string) ($item['value'] ?? ''), $official, $same)) {
                $count++;
            }
        }

        TranslationCacheManager::flushLocale($locale);

        return back()->with('success', __('admin.messages.translations_saved', ['count' => $count]));
    }

    /**
     * Keep a database override only where it says something the language
     * file does not.
     *
     * The editor posts every row on the page, changed or not, and each one
     * used to be written to the database. A Save pressed to fix one word
     * froze the other forty-nine as overrides of the files - and the files
     * were the part that kept improving. The broken Turkish of the first
     * release ("Geriup Kods" for "Backup Codes") survived on installs whose
     * operator had saved a page in it, long after lang/tr was rewritten,
     * because a database row always wins over the file.
     *
     * So a value equal to the shipped text, or empty, removes the override
     * and lets the file speak; only a real difference is stored.
     *
     * A text kept the same as English on purpose ($sameAsEnglish, the English
     * text) is stored as English and marked, so it counts as translated
     * (TranslationCoverage), even where the file says the same.
     *
     * @param  array<string, array<string, string>>  $official
     * @return bool whether an override was stored
     */
    private function storeOverride(string $locale, string $group, string $key, string $value, array $official, ?string $sameAsEnglish = null): bool
    {
        if ($sameAsEnglish !== null) {
            $row = DynamicTranslation::firstOrNew(['language' => $locale, 'group' => $group, 'key' => $key]);
            if ($row->exists && $row->same_as_english && $row->value === $sameAsEnglish) {
                return false;
            }
            $row->fill(['value' => $sameAsEnglish, 'is_auto_translated' => false, 'is_reviewed' => true, 'same_as_english' => true])->save();

            return true;
        }

        $shipped = $official[$group][$key] ?? null;

        if (trim($value) === '' || ($shipped !== null && $value === $shipped)) {
            DynamicTranslation::where(['language' => $locale, 'group' => $group, 'key' => $key])->delete();

            return false;
        }

        $row = DynamicTranslation::firstOrNew(['language' => $locale, 'group' => $group, 'key' => $key]);
        if ($row->exists && $row->value === $value && ! $row->same_as_english) {
            return false;
        }

        $row->fill(['value' => $value, 'is_auto_translated' => false, 'is_reviewed' => true, 'same_as_english' => false])->save();

        return true;
    }

    /** The English text of a key, or null when English has none (a key unknown to PNLCS). */
    private function englishText(OfficialTranslationRepository $officialTranslations, string $group, string $key): ?string
    {
        $english = $officialTranslations->forLocale('en')[$group][$key] ?? null;

        return is_string($english) && trim($english) !== '' ? $english : null;
    }

    /**
     * One batch of machine translation, asked for by the editor page in a
     * loop so the operator can watch it and stop it. See AiTranslationService.
     */
    public function aiTranslateBatch(Request $request, string $locale, AiTranslationService $ai)
    {
        $validated = $request->validate([
            'mode' => ['required', Rule::in(AiTranslationService::MODES)],
            'cursor' => ['nullable', 'string', 'max:255'],
        ]);

        // English is the source. Answered as JSON: the page reads the reply.
        $language = Language::where('code', $locale)->where('code', '!=', 'en')->first();
        if (! $language) {
            return response()->json(['message' => __('messages.error.not_found', ['item' => $locale])], 404);
        }

        if (! $ai->configured()) {
            return response()->json(['message' => __('admin.messages.openai_not_configured')], 422);
        }

        try {
            return response()->json($ai->translateNext($language, $validated['mode'], $validated['cursor'] ?? null));
        } catch (\Throwable $e) {
            \Log::warning("AI translation batch failed for {$locale}: ".$e->getMessage());

            return response()->json(['message' => __('admin.config.translations.ai_failed', ['error' => $e->getMessage()])], 502);
        }
    }

    /**
     * Every text English has, with what the language says for each: its
     * database override, else its file, else empty for the translator to
     * fill (#324). The file used to be built from the language's own files,
     * so a key they did not have yet was not in it, and a language could not
     * be completed through Export and Import. Texts that live only in the
     * database (an addon's own, seeded by its migration) follow, as before.
     *
     * "?missing=1" gives only the texts not yet in the language's own words
     * (TranslationCoverage). Texts kept the same as English on purpose are
     * listed under "_same_as_english", which Import reads back.
     */
    public function export(string $locale, OfficialTranslationRepository $officialTranslations, ?TranslationCoverage $coverage = null)
    {
        $own = $officialTranslations->forLocale($locale);
        $same = [];
        $databaseOnly = [];
        DynamicTranslation::where('language', $locale)
            ->whereNotNull('value')->where('value', '!=', '')
            ->orderBy('group')->orderBy('key')
            ->get(['group', 'key', 'value', 'same_as_english'])
            ->each(function ($row) use (&$own, &$same, &$databaseOnly) {
                $own[$row->group][$row->key] = $row->value;
                $databaseOnly[$row->group][$row->key] = $row->value;
                if ($row->same_as_english) {
                    $same[] = $row->group.'.'.$row->key;
                }
            });

        $missingOnly = request()->boolean('missing');
        $translated = $missingOnly ? ($coverage ?? app(TranslationCoverage::class))->translated($locale) : [];

        $translations = [];
        foreach ($officialTranslations->forLocale('en') as $group => $keys) {
            foreach ($keys as $key => $english) {
                if ($missingOnly && isset($translated[$group.'.'.$key])) {
                    continue;
                }
                $value = $own[$group][$key] ?? '';
                $translations[$group][$key] = is_string($value) ? $value : '';
            }
        }
        if (! $missingOnly) {
            foreach ($databaseOnly as $group => $keys) {
                foreach ($keys as $key => $value) {
                    $translations[$group][$key] ??= $value;
                }
            }
        }
        if ($same !== [] && ! $missingOnly) {
            sort($same);
            $translations[self::SAME_AS_ENGLISH] = $same;
        }

        $name = $missingOnly ? "{$locale}-missing.json" : "{$locale}.json";

        return response()->json($translations, 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            ->header('Content-Disposition', "attachment; filename=\"{$name}\"");
    }

    public function import(Request $request, string $locale, OfficialTranslationRepository $officialTranslations)
    {
        $request->validate(['file' => 'required|file|mimes:json,txt']);

        $content = file_get_contents($request->file('file')->getRealPath());
        $data = json_decode($content, true);

        if (! is_array($data)) {
            return back()->with('error', __('admin.messages.invalid_json'));
        }

        // An exported file carries every text, the shipped ones included;
        // only what differs from the language file becomes an override (see
        // storeOverride), so a round trip through Export and Import no longer
        // pins the whole language to the day it was exported.
        $official = $officialTranslations->forLocale($locale);
        $english = $officialTranslations->forLocale('en');
        // Known: what English ships, and what lives only in the database (an
        // addon's texts, in any language).
        $databaseKeys = DynamicTranslation::query()->select(['group', 'key'])->distinct()->get()
            ->mapWithKeys(fn ($row) => [$row->group."\0".$row->key => true])->all();
        $same = array_flip(array_filter((array) ($data[self::SAME_AS_ENGLISH] ?? []), 'is_string'));
        unset($data[self::SAME_AS_ENGLISH]);

        $count = 0;
        // A key nobody has would be stored and never shown: a typo, or a text
        // PNLCS has since removed. It is left out and named (#324).
        $unknown = [];
        foreach ($data as $group => $keys) {
            if (! is_array($keys)) {
                continue;
            }
            foreach ($keys as $key => $value) {
                if (! is_string($value)) {
                    continue;
                }
                $source = $english[$group][$key] ?? null;
                if (! is_string($source) && ! isset($databaseKeys[$group."\0".$key])) {
                    $unknown[] = $group.'.'.$key;

                    continue;
                }
                $sameAs = is_string($source) && trim($source) !== '' && isset($same[$group.'.'.$key]) ? $source : null;
                if ($this->storeOverride($locale, (string) $group, (string) $key, $value, $official, $sameAs)) {
                    $count++;
                }
            }
        }

        TranslationCacheManager::flushLocale($locale);

        $message = __('admin.messages.imported_translations', ['count' => $count]);
        if ($unknown !== []) {
            $message .= ' '.__('admin.messages.imported_translations_unknown', [
                'count' => count($unknown),
                'keys' => implode(', ', array_slice($unknown, 0, 5)).(count($unknown) > 5 ? ', …' : ''),
            ]);
        }

        return back()->with('success', $message);
    }

    public function clearCache()
    {
        TranslationCacheManager::flush();

        return back()->with('success', __('admin.messages.cache_cleared'));
    }
}
