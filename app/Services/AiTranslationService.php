<?php

namespace App\Services;

use App\Models\DynamicTranslation;
use App\Models\Language;
use App\Models\Setting;
use App\Translation\OfficialTranslationRepository;
use App\Translation\TranslationCacheManager;
use App\Translation\TranslationCoverage;
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
 *  - missing: only keys the language does not say in its own words - no
 *    text at all, or still the English one (TranslationCoverage). A text
 *    that differs from English is never touched.
 *  - all: every key, from the English source, overwriting what is there.
 *    For a language whose existing text cannot be trusted.
 *
 * Results are stored as database overrides (is_auto_translated = true), the
 * same place the editor writes, so a bad batch can be corrected in the
 * editor and "pnlcs:lang-write" can copy the result into lang/<locale> for a
 * contribution.
 *
 * The texts go to one of two providers, chosen on the languages screen:
 * OpenAI (chat completions, or any endpoint that speaks that dialect) or
 * Anthropic (the Claude Messages API). Each has its own key and model
 * setting, so a key entered for one is kept when the operator tries the
 * other. The instructions and the checks on what comes back are the same.
 */
class AiTranslationService
{
    public const BATCH_SIZE = 30;

    public const MODES = ['missing', 'all'];

    public const PROVIDERS = ['openai', 'anthropic'];

    /** Installs from before the choice existed have an OpenAI key and nothing else. */
    public const DEFAULT_PROVIDER = 'openai';

    /** Offered on the languages screen, per provider. Any other stored value still works. */
    public const MODELS = [
        'openai' => ['gpt-4o-mini', 'gpt-4o', 'gpt-4.1-mini', 'gpt-4.1', 'gpt-5-mini', 'gpt-5'],
        'anthropic' => ['claude-opus-5-5', 'claude-sonnet-5-5', 'claude-haiku-5-5'],
    ];

    public const DEFAULT_MODELS = ['openai' => 'gpt-4o-mini', 'anthropic' => 'claude-opus-5-5'];

    /** The settings that hold each provider's key and model (Setup > Languages). */
    public const KEY_SETTINGS = ['openai' => 'OpenAIApiKey', 'anthropic' => 'AnthropicApiKey'];

    public const MODEL_SETTINGS = ['openai' => 'OpenAIModel', 'anthropic' => 'AnthropicModel'];

    /** Room for thirty translated texts many times over; a cut-off answer is not valid JSON and is refused. */
    private const ANTHROPIC_MAX_TOKENS = 16000;

    public function __construct(
        private OfficialTranslationRepository $official,
        private TranslationCoverage $coverage,
    ) {}

    /** The provider the next run goes to. A stored value nobody knows means the default. */
    public function provider(): string
    {
        $provider = trim((string) Setting::get('AiTranslationProvider', ''));

        return in_array($provider, self::PROVIDERS, true) ? $provider : self::DEFAULT_PROVIDER;
    }

    /** Whether this provider (the chosen one when none is named) has a key. */
    public function configured(?string $provider = null): bool
    {
        return $this->apiKey($provider ?? $this->provider()) !== '';
    }

    public function model(?string $provider = null): string
    {
        $provider ??= $this->provider();
        $model = trim((string) Setting::get(self::MODEL_SETTINGS[$provider], ''));

        return $model !== '' ? $model : self::DEFAULT_MODELS[$provider];
    }

    /**
     * The models the languages screen offers for a provider: the known list,
     * plus whatever is stored so a hand-entered name is not silently replaced.
     *
     * @return list<string>
     */
    public function models(string $provider): array
    {
        $models = self::MODELS[$provider];
        $current = $this->model($provider);
        if (! in_array($current, $models, true)) {
            $models[] = $current;
        }

        return $models;
    }

    private function apiKey(string $provider): string
    {
        return trim((string) Setting::get(self::KEY_SETTINGS[$provider], ''));
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
            foreach ($this->coverage->translated($locale) as $fullKey => $_) {
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

        $translated = match ($this->provider()) {
            'anthropic' => $this->callAnthropic($language, $batch),
            default => $this->callOpenAI($language, $batch),
        };
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
            // Except the English text given back: kept, marked as machine
            // translated, so "translate missing" knows it was answered and
            // does not ask about it again (TranslationCoverage).
            if (($shipped[$group][$key] ?? null) === $value && $value !== $source) {
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

    /** What both providers are told, once per batch. */
    private function instructions(Language $language): string
    {
        $target = trim($language->name.' ('.$language->native_name.')');

        return "You translate the user interface of a web hosting and billing platform from English to {$target}. "
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
    }

    /** @param  array<string, string>  $items */
    private function encodeItems(array $items): string
    {
        return json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * The JSON object a model answered with. A model that was asked for JSON
     * only may still wrap it in a Markdown code fence; that is taken off.
     *
     * @return array<string, mixed>
     */
    private function decodeObject(string $text): array
    {
        $text = trim($text);
        if (preg_match('/^```[a-zA-Z]*\s*(.*?)\s*```$/s', $text, $m)) {
            $text = $m[1];
        }

        $result = json_decode($text, true);
        if (! is_array($result)) {
            throw new \RuntimeException('The model did not return a JSON object.');
        }

        return $result;
    }

    /**
     * @param  array<string, string>  $items  "group.key" => English
     * @return array<string, mixed>
     */
    private function callOpenAI(Language $language, array $items): array
    {
        $model = $this->model('openai');

        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $this->instructions($language)],
                ['role' => 'user', 'content' => $this->encodeItems($items)],
            ],
            'response_format' => ['type' => 'json_object'],
        ];

        // The reasoning models accept only their default temperature and
        // answer a request that sets one with a 400.
        if (! preg_match('/^(gpt-5|o\d)/', $model)) {
            $payload['temperature'] = 0.2;
        }

        $response = Http::withToken($this->apiKey('openai'))
            ->acceptJson()
            ->timeout(120)
            ->post(rtrim((string) config('services.openai.base_url', 'https://api.openai.com/v1'), '/').'/chat/completions', $payload);

        if (! $response->successful()) {
            $message = $response->json('error.message') ?: ('HTTP '.$response->status());

            throw new \RuntimeException((string) $message);
        }

        return $this->decodeObject((string) $response->json('choices.0.message.content', ''));
    }

    /**
     * The Claude Messages API. The instructions go in the system field; the
     * answer comes back as content blocks, of which the text ones are the
     * reply (a thinking block may come first). Sampling settings are not
     * sent: the API version used here refuses them.
     *
     * @param  array<string, string>  $items  "group.key" => English
     * @return array<string, mixed>
     */
    private function callAnthropic(Language $language, array $items): array
    {
        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey('anthropic'),
            'anthropic-version' => (string) config('services.anthropic.version', '2026-10-21'),
        ])
            ->acceptJson()
            ->timeout(120)
            ->post(rtrim((string) config('services.anthropic.base_url', 'https://api.anthropic.com'), '/').'/v1/messages', [
                'model' => $this->model('anthropic'),
                'max_tokens' => self::ANTHROPIC_MAX_TOKENS,
                'system' => $this->instructions($language),
                'messages' => [
                    ['role' => 'user', 'content' => $this->encodeItems($items)],
                ],
            ]);

        if (! $response->successful()) {
            $message = $response->json('error.message') ?: ('HTTP '.$response->status());

            throw new \RuntimeException((string) $message);
        }

        // A refused request is HTTP 200 with a stop reason that says so.
        if ($response->json('stop_reason') === 'refusal') {
            $why = (string) ($response->json('stop_details.explanation') ?: '');

            throw new \RuntimeException(trim('The model declined to translate this batch. '.$why));
        }

        $text = '';
        foreach ((array) $response->json('content', []) as $block) {
            if (is_array($block) && ($block['type'] ?? null) === 'text') {
                $text .= (string) ($block['text'] ?? '');
            }
        }

        return $this->decodeObject($text);
    }
}
