<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DynamicTranslation;
use App\Models\Language;
use App\Models\Setting;
use App\Translation\TranslationCacheManager;
use Illuminate\Http\Request;

class TranslationController extends Controller
{
    public function index()
    {
        $languages = Language::orderBy('sort_order')->get();

        // Calculate progress for each language
        $totalKeys = DynamicTranslation::where('language', 'en')->count();
        foreach ($languages as $lang) {
            if ($lang->code === 'en') {
                $lang->translation_progress = 100;
            } else {
                $translated = DynamicTranslation::where('language', $lang->code)
                    ->whereNotNull('value')
                    ->where('value', '!=', '')
                    ->count();
                $lang->translation_progress = $totalKeys > 0 ? round(($translated / $totalKeys) * 100, 1) : 0;
            }
            $lang->save();
        }

        return view('admin.config.languages.index', compact('languages', 'totalKeys'));
    }

    public function toggle(Language $language)
    {
        if ($language->is_default && $language->is_active) {
            return back()->with('error', __('messages.error.cannot_delete_default', ['item' => 'language']));
        }
        $language->update(['is_active' => !$language->is_active]);
        TranslationCacheManager::flush();
        return back()->with('success', $language->is_active ? __('messages.success.enabled', ['item' => $language->name]) : __('messages.success.disabled', ['item' => $language->name]));
    }

    public function setDefault(Request $request)
    {
        $request->validate(['code' => 'required|exists:languages,code']);
        Language::where('is_default', true)->update(['is_default' => false]);
        Language::where('code', $request->code)->update(['is_default' => true, 'is_active' => true]);
        Setting::set('DefaultLanguage', $request->code, 'language');
        TranslationCacheManager::flush();
        return back()->with('success', __('messages.success.settings_saved'));
    }

    public function translations(string $locale)
    {
        $language = Language::where('code', $locale)->firstOrFail();

        $query = DynamicTranslation::where('language', 'en');

        if (request('group')) {
            $query->where('group', request('group'));
        }
        if (request('search')) {
            $search = request('search');
            $query->where(function ($q) use ($search) {
                $q->where('key', 'like', "%{$search}%")
                  ->orWhere('value', 'like', "%{$search}%");
            });
        }

        $englishKeys = $query->orderBy('group')->orderBy('key')->paginate(50);

        // Get target translations
        $targetTranslations = DynamicTranslation::where('language', $locale)
            ->get(['group', 'key', 'value'])->mapWithKeys(fn($row) => [$row->group . '.' . $row->key => $row->value])
            ->toArray();

        $groups = DynamicTranslation::where('language', 'en')
            ->distinct()
            ->pluck('group')
            ->sort();

        $filter = request('filter', 'all');

        return view('admin.config.languages.translations', compact(
            'language', 'englishKeys', 'targetTranslations', 'groups', 'locale', 'filter'
        ));
    }

    public function saveTranslation(Request $request, string $locale)
    {
        $request->validate([
            'group' => 'required|string',
            'key' => 'required|string',
            'value' => 'nullable|string',
        ]);

        DynamicTranslation::updateOrCreate(
            ['language' => $locale, 'group' => $request->group, 'key' => $request->key],
            ['value' => $request->value, 'is_auto_translated' => false, 'is_reviewed' => true]
        );

        TranslationCacheManager::flushKey($locale, $request->group);

        if ($request->ajax()) {
            return response()->json(['success' => true]);
        }
        return back()->with('success', __('messages.success.saved'));
    }

    public function bulkSave(Request $request, string $locale)
    {
        $translations = $request->input('translations', []);
        $count = 0;

        foreach ($translations as $item) {
            if (empty($item['group']) || empty($item['key'])) continue;
            DynamicTranslation::updateOrCreate(
                ['language' => $locale, 'group' => $item['group'], 'key' => $item['key']],
                ['value' => $item['value'] ?? '', 'is_auto_translated' => false, 'is_reviewed' => true]
            );
            $count++;
        }

        TranslationCacheManager::flushLocale($locale);
        return back()->with('success', "Saved {$count} translations.");
    }

    public function aiTranslate(Request $request, string $locale)
    {
        $apiKey = Setting::get('OpenAIApiKey');
        if (!$apiKey) {
            return back()->with('error', 'OpenAI API key not configured. Go to Settings > Languages.');
        }

        $language = Language::where('code', $locale)->firstOrFail();

        // Get untranslated keys
        $englishKeys = DynamicTranslation::where('language', 'en')
            ->whereNotNull('value')
            ->where('value', '!=', '')
            ->get();

        $existingKeys = DynamicTranslation::where('language', $locale)
            ->whereNotNull('value')
            ->where('value', '!=', '')
            ->get(['group', 'key'])->mapWithKeys(fn($row) => [$row->group . '.' . $row->key => $row->key])
            ->toArray();

        $toTranslate = $englishKeys->filter(function ($item) use ($existingKeys) {
            return !isset($existingKeys[$item->group . '.' . $item->key]);
        });

        if ($toTranslate->isEmpty()) {
            return back()->with('success', 'All keys already translated!');
        }

        // Process in batches of 30
        $batches = $toTranslate->chunk(30);
        $model = Setting::get('OpenAIModel', 'gpt-4o-mini');
        $translated = 0;
        $failed = 0;

        foreach ($batches as $batch) {
            $items = [];
            foreach ($batch as $item) {
                $items[$item->group . '.' . $item->key] = $item->value;
            }

            try {
                $result = $this->callOpenAI($apiKey, $model, $language->native_name, $items);
                foreach ($result as $fullKey => $translatedValue) {
                    $parts = explode('.', $fullKey, 2);
                    if (count($parts) !== 2) continue;
                    DynamicTranslation::updateOrCreate(
                        ['language' => $locale, 'group' => $parts[0], 'key' => $parts[1]],
                        ['value' => $translatedValue, 'is_auto_translated' => true, 'is_reviewed' => false]
                    );
                    $translated++;
                }
            } catch (\Throwable $e) {
                $failed += $batch->count();
                \Log::error("AI Translation failed for {$locale}: " . $e->getMessage());
            }
        }

        TranslationCacheManager::flushLocale($locale);

        return back()->with('success', "AI translated {$translated} keys. Failed: {$failed}.");
    }

    private function callOpenAI(string $apiKey, string $model, string $targetLang, array $items): array
    {
        $systemPrompt = "You are a professional translator for a web hosting billing platform. Translate UI texts from English to {$targetLang}. Rules: 1) Keep :name, :count, :amount placeholders UNCHANGED. 2) Keep technical terms (DNS, SSL, FTP, PHP, MySQL, cPanel, VPS, SMTP) UNTRANSLATED. 3) Hosting/billing context: plan=hosting plan, ticket=support ticket. 4) Maintain UI brevity and formality. 5) Return ONLY valid JSON with same keys.";

        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => json_encode($items, JSON_UNESCAPED_UNICODE)],
            ],
            'temperature' => 0.3,
            'response_format' => ['type' => 'json_object'],
        ];

        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new \RuntimeException("OpenAI API returned HTTP {$httpCode}: {$response}");
        }

        $data = json_decode($response, true);
        $content = $data['choices'][0]['message']['content'] ?? '';
        $result = json_decode($content, true);

        if (!is_array($result)) {
            throw new \RuntimeException("Invalid JSON response from OpenAI");
        }

        return $result;
    }

    public function export(string $locale)
    {
        $translations = DynamicTranslation::where('language', $locale)
            ->orderBy('group')
            ->orderBy('key')
            ->get(['group', 'key', 'value'])
            ->groupBy('group')
            ->map(fn($items) => $items->pluck('value', 'key'));

        return response()->json($translations, 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            ->header('Content-Disposition', "attachment; filename=\"{$locale}.json\"");
    }

    public function import(Request $request, string $locale)
    {
        $request->validate(['file' => 'required|file|mimes:json,txt']);

        $content = file_get_contents($request->file('file')->getRealPath());
        $data = json_decode($content, true);

        if (!is_array($data)) {
            return back()->with('error', 'Invalid JSON file.');
        }

        $count = 0;
        foreach ($data as $group => $keys) {
            if (!is_array($keys)) continue;
            foreach ($keys as $key => $value) {
                DynamicTranslation::updateOrCreate(
                    ['language' => $locale, 'group' => $group, 'key' => $key],
                    ['value' => $value]
                );
                $count++;
            }
        }

        TranslationCacheManager::flushLocale($locale);
        return back()->with('success', "Imported {$count} translations.");
    }

    public function clearCache()
    {
        TranslationCacheManager::flush();
        return back()->with('success', 'Translation cache cleared.');
    }
}
