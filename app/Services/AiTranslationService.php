<?php

namespace App\Services;

use App\Models\DynamicTranslation;
use App\Models\Language;
use App\Models\Setting;
use App\Translation\OfficialTranslationRepository;
use App\Translation\TranslationCacheManager;
use Illuminate\Support\Facades\Http;

/**
 * Machine translation of the interface, one small batch per call.
 *
 * The translation editor drives it from the browser: it asks for the next
 * batch, shows what came back, and asks again. Nothing runs in the
 * background, so it works the same on an install whose queue is "sync", every
 * request stays far inside a web server's timeout, and the operator watches
 * the strings arrive and can stop at any point. The cursor is the last
 * "group.key" handled; keys are walked in sorted order, so a stopped run
 * carries on where it left off and a key that failed is not asked about
 * again in the same run.
 *
 * Two modes:
 *  - missing: only keys the language has no text for, in its files or in
 *    the database. Nothing the operator or the project wrote is touched.
 *  - all: every key, from the English source, overwriting what is there.
 *    For a language whose existing text cannot be trusted.
 *
 * Results are stored as database overrides (is_auto_translated = true), the
 * same place the editor writes, so a bad batch can be corrected in the
 * editor and "pnlcs:lang-write" can copy the result into lang/<locale> for a
 * contribution.
 */
class AiTranslationService
{
    public const BATCH_SIZE = 30;

    public const MODES = ['missing', 'all'];

    /** Offered on the languages screen. Any other stored value still works. */
    public const MODELS = ['gpt-4o-mini', 'gpt-4o', 'gpt-4.1-mini', 'gpt-4.1', 'gpt-5-mini', 'gpt-5'];

    public const DEFAULT_MODEL = 'gpt-4o-mini';

    public function __construct(private OfficialTranslationRepository $official) {}

    public function configured(): bool
    {
        return trim((string) Setting::get('OpenAIApiKey', '')) !== '';
    }

    public function model(): string
    {
        $model = trim((string) Setting::get('OpenAIModel', ''));

        return $model !== '' ? $model : self::DEFAULT_MODEL;
    }

    /**
     * The English keys a run in this mode would translate, sorted by
     * "group.key". English values that are empty have nothing to translate.
     *
     * @return array<string, string> "group.key" => English text
     */
    public function candidates(string $locale, string $mode): array
    {
        $english = [];
        foreach ($this->official->forLocale('en') as $group => $keys) {
            foreach ($keys as $key => $value) {
                if (trim($value) !== '') {
                    $english[$group.'.'.$key] = $value;
                }
            }
        }

        if ($mode === 'missing') {
            foreach ($this->existing($locale) as $fullKey => $_) {
                unset($english[$fullKey]);
            }
        }

        ksort($english, SORT_STRING);

        return $english;
    }

    /**
     * Translate the next batch after $cursor and store it.
     *
     * @return array{items: list<array{group: string, key: string, source: string, value: string}>,
     *               skipped: list<array{group: string, key: string, source: string, reason: string}>,
     *               cursor: ?string, remaining: int, done: bool}
     */
    public function translateNext(Language $language, string $mode, ?string $cursor, int $size = self::BATCH_SIZE): array
    {
        $pending = array_filter(
            $this->candidates($language->code, $mode),
            fn ($fullKey) => $cursor === null || strcmp($fullKey, $cursor) > 0,
            ARRAY_FILTER_USE_KEY
        );

        $batch = array_slice($pending, 0, $size, true);
        if ($batch === []) {
            return ['items' => [], 'skipped' => [], 'cursor' => $cursor, 'remaining' => 0, 'done' => true];
        }

        $translated = $this->callOpenAI($language, $batch);
        $shipped = $this->official->forLocale($language->code);

        $items = [];
        $skipped = [];
        foreach ($batch as $fullKey => $source) {
            [$group, $key] = explode('.', $fullKey, 2);
            $value = $translated[$fullKey] ?? null;

            $reason = match (true) {
                ! is_string($value) || trim($value) === '' => 'empty',
                ! $this->samePlaceholders($source, $value) => 'placeholders',
                default => null,
            };

            if ($reason !== null) {
                $skipped[] = compact('group', 'key', 'source', 'reason');

                continue;
            }

            // The same words the language file already ships need no
            // override - one would only hide the file's later improvements.
            if (($shipped[$group][$key] ?? null) === $value) {
                DynamicTranslation::where(['language' => $language->code, 'group' => $group, 'key' => $key])->delete();
            } else {
                DynamicTranslation::updateOrCreate(
                    ['language' => $language->code, 'group' => $group, 'key' => $key],
                    ['value' => $value, 'is_auto_translated' => true, 'is_reviewed' => false]
                );
            }
            $items[] = compact('group', 'key', 'source', 'value');
        }

        TranslationCacheManager::flushLocale($language->code);

        $last = array_key_last($batch);
        $remaining = count($pending) - count($batch);

        return [
            'items' => $items,
            'skipped' => $skipped,
            'cursor' => $last,
            'remaining' => $remaining,
            'done' => $remaining === 0,
        ];
    }

    /**
     * ":name" placeholders are filled in by the application; a translation
     * that drops, renames or invents one prints a literal ":nmae" or loses the
     * amount. Such a value is not stored - the key keeps what it had.
     */
    public function samePlaceholders(string $source, string $value): bool
    {
        $find = function (string $text): array {
            preg_match_all('/:([A-Za-z_][A-Za-z0-9_]*)/', $text, $m);
            $names = $m[1];
            sort($names);

            return $names;
        };

        return $find($source) === $find($value);
    }

    /** @return array<string, true> keys that already have text in this language */
    private function existing(string $locale): array
    {
        $have = [];
        foreach ($this->official->forLocale($locale) as $group => $keys) {
            foreach ($keys as $key => $value) {
                if (trim($value) !== '') {
                    $have[$group.'.'.$key] = true;
                }
            }
        }

        DynamicTranslation::where('language', $locale)
            ->whereNotNull('value')->where('value', '!=', '')
            ->get(['group', 'key'])
            ->each(function ($row) use (&$have) {
                $have[$row->group.'.'.$row->key] = true;
            });

        return $have;
    }

    /**
     * @param  array<string, string>  $items  "group.key" => English
     * @return array<string, mixed>
     */
    private function callOpenAI(Language $language, array $items): array
    {
        $target = trim($language->name.' ('.$language->native_name.')');
        $model = $this->model();

        $system = "You translate the user interface of a web hosting and billing platform from English to {$target}. "
            .'You receive a JSON object of key => English text and return a JSON object with exactly the same keys, '
            .'each value translated. Rules: '
            .'1) Placeholders that start with a colon (:name, :count, :amount) stay exactly as they are, untranslated. '
            .'2) HTML tags and entities stay exactly as they are; translate only the text between them. '
            .'3) Product, protocol and brand names stay untranslated: DNS, SSL, FTP, SFTP, PHP, MySQL, SMTP, IMAP, POP3, '
            .'VPS, CPU, RAM, API, URL, cPanel, Plesk, WordPress, Stripe, PayPal, PNLCS. '
            ."4) Write natural, fluent, correct {$target} with proper spelling and all its letters and diacritics, "
            .'as a native-speaking professional would - never transliterate or translate word by word. '
            .'5) Address the customer politely and consistently, keep labels and buttons short, keep the capitalisation '
            .'style of the source (Title Case labels stay labels, sentences stay sentences). '
            .'6) Hosting and billing meaning: plan = hosting package, ticket = support ticket, invoice, domain = domain name. '
            .'7) Return only the JSON object.';

        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
            ],
            'response_format' => ['type' => 'json_object'],
        ];

        // The reasoning models accept only their default temperature and
        // answer a request that sets one with a 400.
        if (! preg_match('/^(gpt-5|o\d)/', $model)) {
            $payload['temperature'] = 0.2;
        }

        $response = Http::withToken(trim((string) Setting::get('OpenAIApiKey', '')))
            ->acceptJson()
            ->timeout(120)
            ->post(rtrim((string) config('services.openai.base_url', 'https://api.openai.com/v1'), '/').'/chat/completions', $payload);

        if (! $response->successful()) {
            $message = $response->json('error.message') ?: ('HTTP '.$response->status());

            throw new \RuntimeException((string) $message);
        }

        $result = json_decode((string) $response->json('choices.0.message.content', ''), true);
        if (! is_array($result)) {
            throw new \RuntimeException('The model did not return a JSON object.');
        }

        return $result;
    }
}
