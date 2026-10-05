<?php

/*
 * Translate with AI, the editor's Save, and writing the result into the files.
 *
 * Reported on a live install: the Turkish editor showed "Geriup Kods" for
 * "Backup Codes", "Ekleress" for "Address" and "Sizin account devre disi
 * birakildi" - and the same everywhere else. lang/tr had been rewritten
 * properly in September; these were the first release's word-by-word
 * replacements, sitting in dynamic_translations. The editor's Save posted all
 * fifty rows of a page and stored each one, so an operator who fixed one word
 * froze the other forty-nine as database overrides of the old file - and an
 * override always wins. Until 2026-09-19 the loader ignored dotted database
 * keys, so those rows lay dormant; the fix that made saved texts show up
 * brought them all to the screen at once.
 *
 * Covered here: Save and Import store only what differs from the shipped
 * file; the operator can retranslate a language - all of it, overwriting -
 * one watched batch at a time; and the result can be written into
 * lang/<locale> to be committed.
 */

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\DynamicTranslation;
use App\Models\Language;
use App\Models\Setting;
use App\Services\AiTranslationService;
use App\Translation\LangFileWriter;
use App\Translation\OfficialTranslationRepository;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

function aiAdmin(array $permissions = []): Admin
{
    $role = $permissions === []
        ? AdminRole::factory()->fullAdmin()->create()
        : AdminRole::factory()->create(['name' => 'Limited', 'permissions' => $permissions]);

    return Admin::factory()->create(['role_id' => $role->id]);
}

function aiLanguage(string $code = 'tr'): Language
{
    $names = ['tr' => ['Turkish', 'Türkçe'], 'fr' => ['French', 'Français']];

    return Language::updateOrCreate(['code' => $code], [
        'name' => $names[$code][0], 'native_name' => $names[$code][1], 'is_active' => true,
    ]);
}

/** Answer every chat completion by passing each English text through $translate. */
function fakeOpenAi(callable $translate): void
{
    Http::fake(['api.openai.com/*' => function (ClientRequest $request) use ($translate) {
        $items = json_decode($request->data()['messages'][1]['content'], true);
        $out = [];
        foreach ($items as $key => $english) {
            $out[$key] = $translate($english, $key);
        }

        return Http::response(['choices' => [['message' => ['content' => json_encode($out, JSON_UNESCAPED_UNICODE)]]]]);
    }]);
}

function batch(object $test, string $locale, string $mode, ?string $cursor = null)
{
    return $test->actingAs(aiAdmin(), 'admin')
        ->postJson(route('admin.config.languages.ai-translate-batch', $locale), compact('mode', 'cursor'));
}

beforeEach(function () {
    Setting::set('OpenAIApiKey', 'sk-test-key', 'general');
    Setting::set('OpenAIModel', 'gpt-4o-mini', 'general');
});

/*
 * ===== Save stores only what was changed =====
 */

test('saving a page of unchanged texts stores nothing', function () {
    aiLanguage();
    $tr = app(OfficialTranslationRepository::class)->forLocale('tr')['auth'];

    $rows = [];
    foreach (['2fa.backup_codes', 'register.address', 'login.title'] as $key) {
        $rows[] = ['group' => 'auth', 'key' => $key, 'value' => $tr[$key]];
    }

    $before = DynamicTranslation::where('language', 'tr')->count();

    $this->actingAs(aiAdmin(), 'admin')
        ->post(route('admin.config.languages.bulk-save', 'tr'), ['translations' => $rows])
        ->assertRedirect();

    expect(DynamicTranslation::where('language', 'tr')->count())->toBe($before);
});

test('saving a page stores the one text that was changed', function () {
    aiLanguage();
    $tr = app(OfficialTranslationRepository::class)->forLocale('tr')['auth'];

    $this->actingAs(aiAdmin(), 'admin')
        ->post(route('admin.config.languages.bulk-save', 'tr'), ['translations' => [
            ['group' => 'auth', 'key' => 'login.title', 'value' => $tr['login.title']],
            ['group' => 'auth', 'key' => 'register.address', 'value' => 'Açık Adres'],
        ]])
        ->assertRedirect();

    expect(DynamicTranslation::where(['language' => 'tr', 'group' => 'auth'])->pluck('value', 'key')->all())
        ->toBe(['register.address' => 'Açık Adres']);
});

test('saving the shipped text over a frozen old one removes the override', function () {
    aiLanguage();
    DynamicTranslation::create(['language' => 'tr', 'group' => 'auth', 'key' => '2fa.backup_codes',
        'value' => 'Geriup Kods', 'is_auto_translated' => false, 'is_reviewed' => true]);
    $shipped = app(OfficialTranslationRepository::class)->forLocale('tr')['auth']['2fa.backup_codes'];

    $this->actingAs(aiAdmin(), 'admin')
        ->post(route('admin.config.languages.bulk-save', 'tr'), ['translations' => [
            ['group' => 'auth', 'key' => '2fa.backup_codes', 'value' => $shipped],
        ]])
        ->assertRedirect();

    expect(DynamicTranslation::where(['language' => 'tr', 'group' => 'auth'])->count())->toBe(0);
    app()->setLocale('tr');
    expect(__('auth.2fa.backup_codes'))->toBe($shipped);
});

test('an exported file imported back stores nothing it did not change', function () {
    aiLanguage();
    $admin = aiAdmin();
    $rows = fn () => DynamicTranslation::where('language', 'tr')->get()
        ->mapWithKeys(fn ($r) => [$r->group.'.'.$r->key => $r->value])->all();
    $before = $rows();

    $export = $this->actingAs($admin, 'admin')->get(route('admin.config.languages.export', 'tr'))->assertOk();
    $data = json_decode($export->getContent(), true);
    $data['auth']['register.address'] = 'Açık Adres';

    $file = UploadedFile::fake()->createWithContent('tr.json', json_encode($data, JSON_UNESCAPED_UNICODE));
    $this->actingAs($admin, 'admin')
        ->post(route('admin.config.languages.import', 'tr'), ['file' => $file])
        ->assertRedirect();

    $after = $rows();
    expect(array_diff_assoc($after, $before))->toBe(['auth.register.address' => 'Açık Adres']);

    // Rows the import dropped said exactly what the file says, so nothing a
    // reader sees has changed except the one edited text.
    $official = app(OfficialTranslationRepository::class)->forLocale('tr');
    foreach (array_diff_key($before, $after) as $fullKey => $value) {
        [$group, $key] = explode('.', $fullKey, 2);
        expect($official[$group][$key] ?? null)->toBe($value);
    }
});

/*
 * ===== Translate with AI, one batch at a time =====
 */

test('missing mode sends only the texts the language does not have', function () {
    aiLanguage('fr');
    $missing = app(AiTranslationService::class)->candidates('fr', 'missing');
    $all = app(AiTranslationService::class)->candidates('fr', 'all');
    expect(count($missing))->toBeGreaterThan(0)->toBeLessThan(count($all));

    fakeOpenAi(fn ($english) => 'FR '.$english);

    $response = batch($this, 'fr', 'missing')->assertOk()->json();

    $first = array_slice($missing, 0, AiTranslationService::BATCH_SIZE, true);
    expect(array_map(fn ($i) => $i['group'].'.'.$i['key'], $response['items']))->toBe(array_keys($first))
        ->and($response['cursor'])->toBe(array_key_last($first))
        ->and($response['remaining'])->toBe(count($missing) - count($first))
        ->and($response['done'])->toBeFalse();

    Http::assertSent(function (ClientRequest $request) use ($first) {
        return array_keys(json_decode($request->data()['messages'][1]['content'], true)) === array_keys($first);
    });

    $row = DynamicTranslation::where('language', 'fr')->latest('id')->first();
    expect($row->is_auto_translated)->toBeTrue()
        ->and($row->is_reviewed)->toBeFalse()
        ->and($row->value)->toStartWith('FR ');
});

test('all mode overwrites what the language already says', function () {
    aiLanguage();
    DynamicTranslation::create(['language' => 'tr', 'group' => 'auth', 'key' => '2fa.backup_codes',
        'value' => 'Geriup Kods', 'is_auto_translated' => false, 'is_reviewed' => true]);
    fakeOpenAi(fn ($english, $key) => $key === 'auth.2fa.backup_codes' ? 'Yedek Kodları' : 'TR '.$english);

    // Start just before the auth group, as a resumed run would.
    batch($this, 'tr', 'all', 'admin.~')->assertOk();

    expect(DynamicTranslation::where(['language' => 'tr', 'group' => 'auth', 'key' => '2fa.backup_codes'])->value('value'))
        ->toBe('Yedek Kodları');
    app()->setLocale('tr');
    expect(__('auth.2fa.backup_codes'))->toBe('Yedek Kodları');
});

test('the cursor carries a run on from where it stopped, to the end', function () {
    aiLanguage('fr');
    fakeOpenAi(fn ($english) => 'FR '.$english);
    $total = count(app(AiTranslationService::class)->candidates('fr', 'missing'));

    $cursor = null;
    $seen = 0;
    for ($i = 0; $i < 200; $i++) {
        $r = batch($this, 'fr', 'missing', $cursor)->assertOk()->json();
        $seen += count($r['items']) + count($r['skipped']);
        $cursor = $r['cursor'];
        if ($r['done']) {
            break;
        }
    }

    expect($r['done'])->toBeTrue()
        ->and($seen)->toBe($total)
        ->and(app(AiTranslationService::class)->candidates('fr', 'missing'))->toBe([]);
});

test('a translation that loses a placeholder is not stored', function () {
    aiLanguage('fr');
    $missing = app(AiTranslationService::class)->candidates('fr', 'missing');
    $withPlaceholder = array_key_first(array_filter($missing, fn ($v) => preg_match('/:[a-z_]+/', $v)));
    expect($withPlaceholder)->not->toBeNull();

    fakeOpenAi(fn ($english) => preg_replace('/:[a-z_]+/', 'X', $english));
    $keys = array_keys($missing);
    $cursor = ($pos = array_search($withPlaceholder, $keys, true)) > 0 ? $keys[$pos - 1] : null;

    $r = batch($this, 'fr', 'missing', $cursor)->assertOk()->json();

    expect(collect($r['skipped'])->map(fn ($s) => $s['group'].'.'.$s['key'])->all())->toContain($withPlaceholder)
        ->and($r['skipped'][0]['reason'])->toBe('placeholders');
    [$group, $key] = explode('.', $withPlaceholder, 2);
    expect(DynamicTranslation::where(['language' => 'fr', 'group' => $group, 'key' => $key])->exists())->toBeFalse();
});

test('placeholders are compared by name, in any order', function () {
    $ai = app(AiTranslationService::class);

    expect($ai->samePlaceholders('Pay :amount by :date', ':date tarihine kadar :amount ödeyin'))->toBeTrue()
        ->and($ai->samePlaceholders('Pay :amount', 'Ödeyin'))->toBeFalse()
        ->and($ai->samePlaceholders('Pay :amount', ':tutar ödeyin'))->toBeFalse()
        ->and($ai->samePlaceholders('Time: 10:30', 'Saat: 10:30'))->toBeTrue();
});

test('the request carries the key and the model, and no temperature for gpt-5', function () {
    aiLanguage('fr');
    Setting::set('OpenAIModel', 'gpt-5-mini', 'general');
    fakeOpenAi(fn ($english) => 'FR '.$english);

    batch($this, 'fr', 'missing')->assertOk();

    Http::assertSent(fn (ClientRequest $r) => $r->hasHeader('Authorization', 'Bearer sk-test-key')
        && $r->data()['model'] === 'gpt-5-mini'
        && ! array_key_exists('temperature', $r->data()));
});

test('the instructions name the target language', function () {
    aiLanguage();
    fakeOpenAi(fn ($english) => 'TR '.$english);

    batch($this, 'tr', 'all')->assertOk();

    Http::assertSent(function (ClientRequest $r) {
        $system = $r->data()['messages'][0]['content'];

        return str_contains($system, 'to Turkish (Türkçe).')
            && str_contains($system, 'correct Turkish (Türkçe) with')
            && ! str_contains($system, '{$target}');
    });
});

test('an answer that matches the language file is not stored as an override', function () {
    aiLanguage();
    $shipped = app(OfficialTranslationRepository::class)->forLocale('tr');
    DynamicTranslation::create(['language' => 'tr', 'group' => 'auth', 'key' => '2fa.backup_codes',
        'value' => 'Geriup Kods', 'is_auto_translated' => false, 'is_reviewed' => true]);
    fakeOpenAi(function ($english, $fullKey) use ($shipped) {
        [$group, $key] = explode('.', $fullKey, 2);

        return $shipped[$group][$key] ?? 'TR '.$english;
    });

    $r = batch($this, 'tr', 'all', 'admin.~')->assertOk()->json();

    expect(count($r['items']))->toBe(AiTranslationService::BATCH_SIZE)
        ->and(DynamicTranslation::where(['language' => 'tr', 'group' => 'auth'])->count())->toBe(0);
    app()->setLocale('tr');
    expect(__('auth.2fa.backup_codes'))->toBe($shipped['auth']['2fa.backup_codes']);
});

test('the address of the API can be pointed elsewhere', function () {
    aiLanguage('fr');
    config(['services.openai.base_url' => 'https://llm.example.com/v1/']);
    Http::fake(['llm.example.com/*' => Http::response(['choices' => [['message' => ['content' => '{}']]]])]);

    batch($this, 'fr', 'missing')->assertOk();

    Http::assertSent(fn (ClientRequest $r) => $r->url() === 'https://llm.example.com/v1/chat/completions');
});

test('an OpenAI error stops the run with its message and stores nothing', function () {
    aiLanguage('fr');
    Http::fake(['api.openai.com/*' => Http::response(['error' => ['message' => 'Incorrect API key provided']], 401)]);
    $before = DynamicTranslation::where('language', 'fr')->count();

    $r = batch($this, 'fr', 'missing')->assertStatus(502)->json();

    expect($r['message'])->toContain('Incorrect API key provided')
        ->and(DynamicTranslation::where('language', 'fr')->count())->toBe($before);
});

test('without a key the batch says so and calls nobody', function () {
    aiLanguage('fr');
    Setting::set('OpenAIApiKey', '', 'general');
    Http::fake();

    batch($this, 'fr', 'missing')->assertStatus(422);
    Http::assertNothingSent();
});

test('English is the source and cannot be machine translated', function () {
    Http::fake();
    batch($this, 'en', 'all')->assertNotFound();
    Http::assertNothingSent();
});

test('an unknown mode is refused', function () {
    aiLanguage('fr');
    batch($this, 'fr', 'everything')->assertStatus(422);
});

test('an admin without manage_settings cannot run it', function () {
    aiLanguage('fr');
    Http::fake();

    $this->actingAs(aiAdmin(['list_tickets']), 'admin')
        ->postJson(route('admin.config.languages.ai-translate-batch', 'fr'), ['mode' => 'all'])
        ->assertForbidden();
    Http::assertNothingSent();
});

test('a stored text is shown at once, whatever group it is in', function () {
    aiLanguage();
    app()->setLocale('tr');
    __('sections.apps.point_isolation');
    expect(Cache::has('translations:tr:sections'))->toBeTrue();

    fakeOpenAi(fn ($english) => 'YENI '.$english);
    batch($this, 'tr', 'all', 'pdf.~')->assertOk();

    expect(Cache::has('translations:tr:sections'))->toBeFalse();
});

/*
 * ===== The screens =====
 */

test('the AI settings are the first thing on the languages page and the old AI button is gone', function () {
    aiLanguage();
    $html = $this->actingAs(aiAdmin(), 'admin')->get(route('admin.config.languages.index'))->assertOk()->getContent();

    expect(strpos($html, 'id="ai-settings"'))->toBeLessThan(strpos($html, 'id="panel-languages"'))
        ->and($html)->toContain('name="OpenAIApiKey"')
        ->and($html)->toContain('name="OpenAIModel"')
        ->and($html)->not->toContain('sk-test-key')
        ->and($html)->not->toContain('/ai-translate/tr"');
});

test('the editor offers both modes with their sizes when a key is saved', function () {
    aiLanguage();
    $html = $this->actingAs(aiAdmin(), 'admin')->get(route('admin.config.languages.translations', 'tr'))->assertOk()->getContent();
    $all = count(app(AiTranslationService::class)->candidates('tr', 'all'));

    expect($html)->toContain('id="ai-panel"')
        ->and($html)->toContain('value="missing"')
        ->and($html)->toContain('value="all"')
        ->and($html)->toContain('('.$all.')')
        ->and($html)->toContain(json_encode(route('admin.config.languages.ai-translate-batch', 'tr')));
});

test('without a key the editor points to the settings instead', function () {
    aiLanguage();
    Setting::set('OpenAIApiKey', '', 'general');
    $html = $this->actingAs(aiAdmin(), 'admin')->get(route('admin.config.languages.translations', 'tr'))->assertOk()->getContent();

    expect($html)->toContain(__('admin.config.translations.ai_not_configured'))
        ->and($html)->not->toContain('id="ai-start"');
});

/*
 * ===== Into the files =====
 */

function langSandbox(): string
{
    $dir = sys_get_temp_dir().'/pnlcs-lang-'.bin2hex(random_bytes(4));
    mkdir($dir.'/en', 0777, true);
    mkdir($dir.'/tr', 0777, true);

    file_put_contents($dir.'/en/demo.php', <<<'PHP'
<?php

return [
    // A comment that has to survive.
    'title' => 'Title',
    'flat.key' => 'Flat',
    'nested' => [
        'inner' => 'Inner',
    ],
    'mixed.group' => [
        'leaf' => 'Leaf',
    ],
    'quote' => 'It is',
    'lines' => "One\nTwo",
    'new_one' => 'New',
];
PHP);

    file_put_contents($dir.'/tr/demo.php', <<<'PHP'
<?php

return [
    // A comment that has to survive.
    'title' => 'Başlık',
    'flat.key' => 'Düz',
    'nested' => [
        'inner' => 'İç',
    ],
    'mixed.group' => [
        'leaf' => 'Yaprak',
    ],
    'quote' => 'Öyle',
    'lines' => "Bir\nİki",
];
PHP);

    return $dir;
}

test('the writer changes only the values it is given, in place', function () {
    $dir = langSandbox();
    $before = file_get_contents($dir.'/tr/demo.php');

    app(LangFileWriter::class)->write($dir.'/tr/demo.php', $dir.'/en/demo.php', [
        'nested.inner' => 'İçeride',
        'mixed.group.leaf' => 'Yaprakçık',
        'quote' => "Kurum'un \\ yolu",
        'lines' => "Bir\nİki\nÜç",
        'new_one' => 'Yeni',
    ]);

    $after = file_get_contents($dir.'/tr/demo.php');
    $data = require $dir.'/tr/demo.php';

    expect($after)->toContain('// A comment that has to survive.')
        ->and($data['title'])->toBe('Başlık')
        ->and($data['flat.key'])->toBe('Düz')
        ->and($data['nested']['inner'])->toBe('İçeride')
        ->and($data['mixed.group']['leaf'])->toBe('Yaprakçık')
        ->and($data['quote'])->toBe("Kurum'un \\ yolu")
        ->and($data['lines'])->toBe("Bir\nİki\nÜç")
        ->and($data['new_one'])->toBe('Yeni')
        ->and($after)->toContain('"Bir\nİki\nÜç"');

    // Every line not asked about is exactly as it was.
    $unchanged = array_intersect(explode("\n", $before), explode("\n", $after));
    expect(count($unchanged))->toBeGreaterThanOrEqual(count(explode("\n", $before)) - 4);
});

test('a key written both flat and nested is changed in both places', function () {
    $dir = langSandbox();
    file_put_contents($dir.'/tr/demo.php', "<?php\n\nreturn [\n    'nested.inner' => 'Düz',\n    'nested' => [\n        'inner' => 'İç',\n    ],\n];\n");

    app(LangFileWriter::class)->write($dir.'/tr/demo.php', $dir.'/en/demo.php', ['nested.inner' => 'Yeni']);

    $data = require $dir.'/tr/demo.php';
    expect($data['nested.inner'])->toBe('Yeni')
        ->and($data['nested']['inner'])->toBe('Yeni');
});

test('a stub that returns English is filled from the English file', function () {
    $dir = langSandbox();
    file_put_contents($dir.'/tr/demo.php', "<?php\n// TODO: Translate to TR\nreturn array_map(fn (\$v) => \$v, require __DIR__ . \"/../en/demo.php\");\n");

    app(LangFileWriter::class)->write($dir.'/tr/demo.php', $dir.'/en/demo.php', ['title' => 'Başlık']);

    $data = require $dir.'/tr/demo.php';
    expect($data['title'])->toBe('Başlık')
        ->and($data['nested']['inner'])->toBe('Inner')
        ->and(file_get_contents($dir.'/tr/demo.php'))->toContain('// A comment that has to survive.');
});

test('pnlcs:lang-write puts the database texts into the files', function () {
    $dir = langSandbox();
    app()->useLangPath($dir);
    DynamicTranslation::create(['language' => 'tr', 'group' => 'demo', 'key' => 'nested.inner', 'value' => 'İçeride', 'is_auto_translated' => true]);
    DynamicTranslation::create(['language' => 'tr', 'group' => 'demo', 'key' => 'title', 'value' => 'Başlık', 'is_auto_translated' => true]);
    DynamicTranslation::create(['language' => 'tr', 'group' => 'demo', 'key' => 'only_in_db', 'value' => 'Yalnız DB', 'is_auto_translated' => true]);
    $before = file_get_contents($dir.'/tr/demo.php');

    $this->artisan('pnlcs:lang-write', ['locale' => 'tr', '--dry-run' => true])->assertSuccessful();
    expect(file_get_contents($dir.'/tr/demo.php'))->toBe($before);

    $this->artisan('pnlcs:lang-write', ['locale' => 'tr', '--clear' => true])->assertSuccessful();

    $data = require $dir.'/tr/demo.php';
    expect($data['nested']['inner'])->toBe('İçeride')
        ->and($data)->not->toHaveKey('only_in_db')
        // Written rows are gone; the one with no file to go to stays.
        ->and(DynamicTranslation::where(['language' => 'tr', 'group' => 'demo'])->pluck('key')->all())->toBe(['only_in_db']);
});

test('pnlcs:lang-write refuses English', function () {
    $this->artisan('pnlcs:lang-write', ['locale' => 'en'])->assertFailed();
});
