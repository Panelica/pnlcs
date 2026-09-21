<?php

/*
 * The sentences that are allowed to carry markup, and the guard that decides
 * which markup that is.
 *
 * /ssl and /guides/mail-setup are public and unauthenticated, and every
 * sentence on them is a translation. Most of those sentences genuinely hold a
 * tag - "issued by <strong>Let's Encrypt</strong>", "enter <code>info@…</code>"
 * - because a key has to hold a whole sentence for a translator to be able to
 * reorder it, and the Turkish proves the point: the <strong> that opens the
 * English sentence sits in the middle of the Turkish one. So the pages printed
 * those values raw.
 *
 * The values are not only the language files. DbTranslationLoader layers
 * dynamic_translations over them, and that table is written by the admin
 * translation editor with nothing sanitising the write path - not in
 * saveTranslation, not in bulkSave, not in aiTranslate, not in import. Anybody
 * holding manage_settings could therefore put a <script> on a page that every
 * visitor sees without signing in, and before App\Support\InlineMarkup existed
 * that is exactly what happened: the first test here failed with
 * "<script>alert(1)</script>" sitting in the rendered <p>.
 *
 * The tests are written through the editor route rather than against the table,
 * because the hole was the distance between what that route accepts and what
 * the page prints, and a test that inserts its own row would not measure it.
 */

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\HomepageSection;
use App\Support\InlineMarkup;

/** Save a value the way an operator does: signed in, through the editor. */
function saveTranslationAs(object $test, string $group, string $key, string $value, string $locale = 'en'): void
{
    $role = AdminRole::factory()->fullAdmin()->create();
    $admin = Admin::factory()->create(['role_id' => $role->id]);

    $test->actingAs($admin, 'admin')
        ->post(route('admin.config.languages.save', $locale), compact('group', 'key') + ['value' => $value])
        ->assertSessionHasNoErrors();
}

/*
 * ===== The hole itself =====
 */

test('a script stored through the translation editor does not reach /ssl', function () {
    saveTranslationAs($this, 'client', 'pages.ssl.included_issuer',
        'Issued by <strong>Let\'s Encrypt</strong>.<script>alert(1)</script>');

    app()->setLocale('en');
    $body = $this->get(route('pages.ssl'))->assertOk()->getContent();

    expect($body)->not->toContain('<script>alert(1)</script>')
        // Escaped, not stripped: the operator who typed it sees it on the page.
        ->and($body)->toContain('&lt;script&gt;alert(1)&lt;/script&gt;')
        // And the sentence it was hidden in still reads correctly.
        ->and($body)->toContain('<strong>Let&#039;s Encrypt</strong>');
});

test('a script stored through the translation editor does not reach /guides/mail-setup', function () {
    saveTranslationAs($this, 'client', 'pages.mail_setup.password_note',
        '<strong>Password</strong><script>alert(2)</script>');

    app()->setLocale('en');
    $body = $this->get(route('pages.mail-setup'))->assertOk()->getContent();

    expect($body)->not->toContain('<script>alert(2)</script>')
        ->and($body)->toContain('&lt;script&gt;alert(2)&lt;/script&gt;');
});

test('a script stored through the translation editor does not reach the home page headline', function () {
    // The headline draws only when the operator has the section switched on,
    // which is how it ships. Without this row the hero is never included and
    // the test would pass without testing anything.
    HomepageSection::create(['slug' => 'hero', 'title' => 'Hero', 'is_enabled' => true, 'sort_order' => 1]);

    saveTranslationAs($this, 'sections', 'hero.title',
        'Hosting That <span>Simply Works</span><script>alert(3)</script>');

    app()->setLocale('en');
    $body = $this->get('/')->assertOk()->getContent();

    expect($body)->not->toContain('<script>alert(3)</script>')
        // The <span> is what .hero__title span colours, so it has to survive.
        ->and($body)->toContain('<span>Simply Works</span>');
});

test('the stored value cannot smuggle an event handler in on an allowed tag', function () {
    saveTranslationAs($this, 'client', 'pages.ssl.how_p2',
        'Renewed <span onmouseover="alert(4)">automatically</span> and <strong onclick="alert(5)">free</strong>.');

    app()->setLocale('en');
    $body = $this->get(route('pages.ssl'))->assertOk()->getContent();

    // The words are still on the page - as text, which is the point. What
    // must not be there is either of them as an attribute on a live tag.
    expect($body)->not->toContain('<span onmouseover')
        ->and($body)->not->toContain('<strong onclick')
        ->and($body)->toContain('&lt;span onmouseover=&quot;alert(4)&quot;&gt;');
});

/*
 * ===== What must still work =====
 *
 * Closing the hole by escaping everything would have printed the tags to the
 * customer, which is the defect traded for the defect. These hold the output.
 */

test('the markup the pages are written with still renders, in English and in Turkish', function () {
    foreach (['en', 'tr'] as $locale) {
        app()->setLocale($locale);

        $ssl = $this->get(route('pages.ssl'))->assertOk()->getContent();
        $mail = $this->get(route('pages.mail-setup'))->assertOk()->getContent();

        expect($ssl)->toContain('<strong>Let&#039;s Encrypt</strong>')
            ->and($ssl)->toContain('<code>')
            ->and($mail)->toContain('<strong>')
            ->and($mail)->toContain('<code>');

        // Not "&lt;strong&gt;" anywhere a sentence lives: a page that escaped
        // its own markup would still pass the assertions above on the labels
        // the blade prints itself.
        expect($ssl)->not->toContain('&lt;strong&gt;')
            ->and($mail)->not->toContain('&lt;strong&gt;');
    }
});

test('an entity written into a value still reads as the character it names', function () {
    // client.pages.mail_setup.webmail_p2 says "My Services &rarr; Email
    // Accounts". Escaping with double encoding on would have printed the
    // customer the seven letters "&rarr;" instead of the arrow.
    app()->setLocale('en');

    expect($this->get(route('pages.mail-setup'))->assertOk()->getContent())
        ->toContain('&rarr;')
        ->not->toContain('&amp;rarr;');
});

test('the links the two pages build in the view still render as links', function () {
    app()->setLocale('en');

    $ssl = $this->get(route('pages.ssl'))->assertOk()->getContent();
    $mail = $this->get(route('pages.mail-setup'))->assertOk()->getContent();

    expect($ssl)->toContain('<a href="'.route('client.contact').'">write to us</a>')
        // The webmail link is off-site, so it keeps the pair it was written
        // with rather than losing it to the guard.
        ->and($mail)->toContain('target="_blank" rel="noopener"')
        ->and($mail)->toContain('webmail.');
});

/*
 * ===== The guard on its own =====
 *
 * Reached through the same call the views make, so a change to the helper is
 * covered as well as a change to the class.
 */

test('the allow-list restores the tags a sentence needs and nothing else', function (string $stored, string $expected) {
    expect(InlineMarkup::render($stored)->toHtml())->toBe($expected);
})->with([
    'bold' => ['a <strong>b</strong> c', 'a <strong>b</strong> c'],
    'code' => ['use <code>info@x</code>', 'use <code>info@x</code>'],
    'emphasis' => ['<em>x</em>', '<em>x</em>'],
    'span' => ['a <span>b</span>', 'a <span>b</span>'],
    'line break' => ['a<br>b', 'a<br>b'],
    'self-closing break' => ['a<br />b', 'a<br>b'],

    'script' => ['<script>alert(1)</script>', '&lt;script&gt;alert(1)&lt;/script&gt;'],
    'image with an onerror' => ['<img src=x onerror=alert(1)>', '&lt;img src=x onerror=alert(1)&gt;'],
    'svg' => ['<svg/onload=alert(1)>', '&lt;svg/onload=alert(1)&gt;'],
    'iframe' => ['<iframe src="//evil"></iframe>', '&lt;iframe src=&quot;//evil&quot;&gt;&lt;/iframe&gt;'],
    'style' => ['<style>body{}</style>', '&lt;style&gt;body{}&lt;/style&gt;'],
    'an allowed tag carrying an attribute' => [
        '<span onmouseover="x">b</span>',
        '&lt;span onmouseover=&quot;x&quot;&gt;b&lt;/span&gt;',
    ],
    'an allowed tag name used as a prefix' => ['<embed>', '&lt;embed&gt;'],
    'a quote in the text' => ["it's", 'it&#039;s'],
    'an entity the author wrote' => ['a &rarr; b', 'a &rarr; b'],
    // The patterns run over bytes rather than code points; the boundaries they
    // match on are all ASCII, so a Turkish sentence has to come back whole.
    'a sentence that is not ASCII' => [
        'Sertifikalar <strong>Let\'s Encrypt</strong> tarafından veriliyor; şğüöçİ.',
        'Sertifikalar <strong>Let&#039;s Encrypt</strong> tarafından veriliyor; şğüöçİ.',
    ],
    'nothing' => ['', ''],
]);

test('a link is restored only when its address is one a browser should follow', function (string $stored, string $expected) {
    expect(InlineMarkup::render($stored)->toHtml())->toBe($expected);
})->with([
    'https' => ['<a href="https://x.test/a">go</a>', '<a href="https://x.test/a">go</a>'],
    'http' => ['<a href="http://x.test">go</a>', '<a href="http://x.test">go</a>'],
    'mailto' => ['<a href="mailto:a@x.test">mail</a>', '<a href="mailto:a@x.test">mail</a>'],
    'relative' => ['<a href="/contact">go</a>', '<a href="/contact">go</a>'],
    'in-page' => ['<a href="#top">go</a>', '<a href="#top">go</a>'],
    'a query string' => [
        '<a href="https://x.test/?a=1&b=2">go</a>',
        '<a href="https://x.test/?a=1&amp;b=2">go</a>',
    ],
    'a link inside a non-ASCII sentence' => [
        '<a href="https://x.test/ş">bize yazın</a> şğüöç',
        '<a href="https://x.test/ş">bize yazın</a> şğüöç',
    ],
    'the external pair the mail guide uses' => [
        '<a href="https://webmail.x.test" target="_blank" rel="noopener">webmail.x.test</a>',
        '<a href="https://webmail.x.test" target="_blank" rel="noopener">webmail.x.test</a>',
    ],

    // Refused: left escaped, so it shows as text and the operator sees it.
    'javascript' => [
        '<a href="javascript:alert(1)">go</a>',
        '&lt;a href=&quot;javascript:alert(1)&quot;&gt;go&lt;/a&gt;',
    ],
    'javascript spelled with an entity' => [
        '<a href="&#106;avascript:alert(1)">go</a>',
        '&lt;a href=&quot;&#106;avascript:alert(1)&quot;&gt;go&lt;/a&gt;',
    ],
    'javascript split by a tab' => [
        "<a href=\"java\tscript:alert(1)\">go</a>",
        "&lt;a href=&quot;java\tscript:alert(1)&quot;&gt;go&lt;/a&gt;",
    ],
    'data' => [
        '<a href="data:text/html;base64,PHNjcmlwdD4=">go</a>',
        '&lt;a href=&quot;data:text/html;base64,PHNjcmlwdD4=&quot;&gt;go&lt;/a&gt;',
    ],
    'protocol-relative' => [
        '<a href="//evil.test">go</a>',
        '&lt;a href=&quot;//evil.test&quot;&gt;go&lt;/a&gt;',
    ],
    'an attribute that is not the pair we allow' => [
        '<a href="https://x.test" onclick="alert(1)">go</a>',
        '&lt;a href=&quot;https://x.test&quot; onclick=&quot;alert(1)&quot;&gt;go&lt;/a&gt;',
    ],
]);

test('a translation key naming a whole group prints the key rather than raising', function () {
    // __() hands back an array for a group. The guard takes a string, so the
    // helper has to decide what an array means before it gets there.
    expect(trans_markup('client.pages')->toHtml())->toBe('client.pages');
});

/*
 * ===== The two pages behind a sign-in that had the same shape =====
 *
 * Neither was made by this change and neither is reachable without an account,
 * but both printed a translation raw, and a translation is writable by anybody
 * holding manage_settings. On /admin/api-docs that is one admin reaching a
 * more privileged one; on the verify-email page it is an admin reaching every
 * customer who has just signed up. They are the same defect with a smaller
 * audience, and they take the same call to close.
 */

test('a script stored in a translation does not reach the admin API docs', function () {
    saveTranslationAs($this, 'admin', 'api_docs.auth_intro',
        'Authenticate with <code>identifier</code>.<script>alert(6)</script>');

    $role = AdminRole::factory()->fullAdmin()->create();
    $admin = Admin::factory()->create(['role_id' => $role->id]);

    $body = $this->actingAs($admin, 'admin')->get(route('admin.api-docs'))->assertOk()->getContent();

    expect($body)->not->toContain('<script>alert(6)</script>')
        ->and($body)->toContain('<code>identifier</code>');
});

test('a script stored in a translation does not reach the verify-email page', function () {
    saveTranslationAs($this, 'client', 'email_verify.sent_to',
        'We sent it to :email<script>alert(7)</script>');

    // The sign-in identity is the User; email_verified_at lives there, which
    // is why EmailVerificationTest builds the pair rather than a Client alone.
    $user = App\Models\User::factory()->create(['email_verified_at' => null]);

    $body = $this->actingAs($user, 'web')->get(route('client.verification.notice'))->assertOk()->getContent();

    expect($body)->not->toContain('<script>alert(7)</script>')
        // The address is still spelled out in bold, which is the whole point
        // of that sentence.
        ->and($body)->toContain('<strong>'.$user->email.'</strong>');
});

/*
 * ===== The rule, rather than the four places it was broken =====
 *
 * Every value a view prints raw has to be one a visitor cannot write. A
 * translation is not: DbTranslationLoader serves dynamic_translations, and the
 * admin editor writes that table unsanitised. So no view may print __() raw -
 * and this is the test that says so, because the next page written from the
 * same honest motive ("the sentence carries a <strong>") would otherwise open
 * the hole again with nobody noticing.
 */
test('no view prints a translation without the allow-list', function () {
    $offenders = [];

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(resource_path('views'), FilesystemIterator::SKIP_DOTS)
    );

    foreach ($files as $file) {
        if (! str_ends_with($file->getFilename(), '.blade.php')) {
            continue;
        }

        foreach (file($file->getPathname()) as $number => $line) {
            // A raw echo reaching straight for a translation, in any of the
            // spellings Laravel offers for one.
            if (preg_match('~\{!!\s*(?:__|trans|trans_choice)\s*\(~', $line)) {
                $offenders[] = str_replace(base_path().'/', '', $file->getPathname()).':'.($number + 1);
            }
        }
    }

    expect($offenders)->toBe([]);
});
