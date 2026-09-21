<?php

/*
 * Every language against English.
 *
 * Twenty-six of the thirty languages sit at exactly the same 2,921 keys, which
 * is what it looks like when a set of files is generated once and never touched
 * again. Chasing all of it down in one go is not realistic, so this test does
 * the next best thing: it writes today's gap down and fails when a gap grows.
 *
 * That means an English string added tomorrow cannot quietly widen the hole in
 * twenty-eight languages. Translate it, or lower the number here on purpose.
 * The numbers may only ever go down.
 */

/** @return array<string, true> every key in a locale, flattened to "file.dotted.key" */
function localeKeys(string $locale): array
{
    $flatten = function (array $rows, string $prefix) use (&$flatten): array {
        $out = [];
        foreach ($rows as $key => $value) {
            $full = $prefix === '' ? (string) $key : $prefix.'.'.$key;
            if (is_array($value)) {
                $out += $flatten($value, $full);
            } else {
                $out[$full] = true;
            }
        }

        return $out;
    };

    $keys = [];
    foreach (glob(base_path('lang/'.$locale.'/*.php')) as $file) {
        $keys += $flatten((array) require $file, basename($file, '.php'));
    }

    return $keys;
}

// Measured 2026-08-20. Lower these as translations land; never raise them.
const TRANSLATION_GAP_BUDGET = [
    'pl' => 0,
    'zh' => 0,
    'tr' => 0,
    // The twenty-six that were generated together and left behind.
    'ar' => 984, 'az' => 984, 'ca' => 984, 'cs' => 984, 'da' => 984, 'de' => 984,
    'el' => 984, 'es' => 984, 'et' => 984, 'fa' => 984, 'fi' => 984, 'fr' => 984,
    'he' => 984, 'hr' => 984, 'hu' => 984, 'it' => 984, 'ja' => 984, 'ko' => 984,
    'mk' => 984, 'nl' => 984, 'no' => 984, 'pt-br' => 984, 'ro' => 984,
    'ru' => 984, 'sv' => 984, 'uk' => 984,
];

test('no language falls further behind English than it already is', function () {
    $english = localeKeys('en');
    expect($english)->not->toBeEmpty();

    $worse = [];
    foreach (array_keys(TRANSLATION_GAP_BUDGET) as $locale) {
        $missing = count(array_diff_key($english, localeKeys($locale)));
        $budget = TRANSLATION_GAP_BUDGET[$locale];
        if ($missing > $budget) {
            $worse[] = sprintf('%s is missing %d keys, budget %d', $locale, $missing, $budget);
        }
    }

    expect($worse)->toBe([], "Translate the new strings, or lower the budget deliberately:\n".implode("\n", $worse));
});

test('every shipped language is accounted for', function () {
    $shipped = array_map('basename', glob(base_path('lang/*'), GLOB_ONLYDIR));
    $tracked = array_merge(['en'], array_keys(TRANSLATION_GAP_BUDGET));

    // A language added without a budget line would never be checked again.
    expect(array_values(array_diff($shipped, $tracked)))->toBe([]);
});

test('the complete languages stay complete', function () {
    // Cheap to keep at parity, expensive to regain once they drift.
    //
    // KEY SETS ONLY. This says a locale has a line for every English line; it
    // says nothing about what is on that line, which is the next three tests'
    // job. Keeping the two apart is deliberate: a missing key and an English
    // value are different faults with different fixes.
    foreach (['pl', 'zh', 'tr'] as $locale) {
        expect(count(array_diff_key(localeKeys('en'), localeKeys($locale))))->toBe(0);
    }
});

/*
 * WHY THE THREE TESTS BELOW EXIST.
 *
 * 'the complete languages stay complete' passed - 0 missing keys in tr - on the
 * day the Turkish home page read "Shd Sunucuing" and the Turkish login page
 * read "Sign in to sizin account". array_diff_key compares key sets, so a row
 * whose value is English, or machine-mangled, or empty, is indistinguishable
 * from a row that is correctly translated. Presence was measured; translation
 * was not. Two separate measurements of lang/tr reported it "100% complete"
 * for exactly that reason.
 *
 * So these look at the value.
 */

/** @return array<string, string> every key in a locale, flattened, with its string */
function localeValues(string $locale): array
{
    $flatten = function (array $rows, string $prefix) use (&$flatten): array {
        $out = [];
        foreach ($rows as $key => $value) {
            $full = $prefix === '' ? (string) $key : $prefix.'.'.$key;
            if (is_array($value)) {
                $out += $flatten($value, $full);
            } else {
                $out[$full] = (string) $value;
            }
        }

        return $out;
    };

    $values = [];
    foreach (glob(base_path('lang/'.$locale.'/*.php')) as $file) {
        $values += $flatten((array) require $file, basename($file, '.php'));
    }

    return $values;
}

/*
 * Strings that are the same word in every language: product names, protocols,
 * file extensions, units. Counting them as untranslated would push the budgets
 * below up for no gain and invite somebody to "translate" SMTP.
 *
 * Measured 2026-09-21 against lang/en. Lower these as translations land; never
 * raise them - a raise means a value went back to English.
 */
const UNTRANSLATED_VALUE_BUDGET = [
    'tr' => 33,
    'pl' => 70,
    'zh' => 20,
];

test('a complete language is translated, not merely present', function () {
    $english = localeValues('en');

    $worse = [];
    foreach (UNTRANSLATED_VALUE_BUDGET as $locale => $budget) {
        $theirs = localeValues($locale);

        $identical = 0;
        foreach ($english as $key => $value) {
            // A value with no letter in it - '-', ':count', '%' - carries no
            // language, so it cannot be untranslated.
            if (! isset($theirs[$key]) || ! preg_match('/\p{L}/u', $value)) {
                continue;
            }
            if ($theirs[$key] === $value) {
                $identical++;
            }
        }

        if ($identical > $budget) {
            $worse[] = sprintf('%s leaves %d values in English, budget %d', $locale, $identical, $budget);
        }
    }

    expect($worse)->toBe([], "Translate the new strings, or lower the budget deliberately:\n".implode("\n", $worse));
});

/**
 * Every string written in a locale's files, as file:line => value.
 *
 * NOT the resolved translations, and that is the point. These files write the
 * same key twice - a nested block and a flat dotted key - and Laravel returns
 * the flat one (Arr::exists() tests the whole dotted key before it splits on
 * dots), so a shadowed copy can hold anything at all and no page will show it.
 * That is exactly where the English survived: client.php:328 said
 * 'Welcome back, :name!' while the flat key said 'Tekrar hoş geldiniz, :name!'.
 * Unreachable today; live the moment somebody tidies the duplicate away.
 *
 * @return array<string, string>
 */
function localeLines(string $locale): array
{
    $lines = [];
    foreach (glob(base_path('lang/'.$locale.'/*.php')) as $file) {
        foreach (file($file) as $number => $line) {
            if (! preg_match("/^\s*'(?:[^'\\\\]|\\\\.)*'\s*=>\s*'((?:[^'\\\\]|\\\\.)*)'\s*,\s*$/u", $line, $matches)) {
                continue;
            }
            $lines[basename($file).':'.($number + 1)] = str_replace(["\\'", '\\\\'], ["'", '\\'], $matches[1]);
        }
    }

    return $lines;
}

/** @return list<string> the words of a translation, markup and placeholders removed */
function translationWords(string $value): array
{
    // ':from' and '<strong>' are not prose. Stripping them first is what keeps
    // 'çalışmayı' from being read as three words and ':have' as one.
    $prose = preg_replace('/:[a-zA-Z_][a-zA-Z0-9_]*/u', ' ', $value);
    $prose = preg_replace('/<[^>]*>/u', ' ', (string) $prose);

    preg_match_all('/[\p{L}]+/u', (string) $prose, $matches);

    return $matches[0];
}

test('no Turkish string carries an English word left behind by a machine pass', function () {
    /*
     * The fault this catches, in its own words:
     *
     *   sections.footer.shared_hosting  'Shd Sunucuing'
     *   auth.login.subtitle             'Sign in to sizin account'
     *   admin.clients.no_notes          'Hayır notes yet.'
     *   admin.api_credentials.view_docs 'Görüntüle API Documentation'
     *
     * Somebody ran a word-for-word substitution over the English and shipped
     * the result, so whatever the substitution list did not know stayed in
     * English.
     *
     * EVERY WORD BELOW WAS CHECKED AGAINST TODAY'S lang/tr AND APPEARS IN NONE
     * OF IT, so none of them can fire on correct Turkish. Add a word the same
     * way - grep lang/tr first - or the test starts lying. The words
     * deliberately LEFT OUT are the ones that do collide: 'on' (Turkish for
     * ten; also 'On Ek' = prefix), 'in', 'at' ('Göz At'), 'an' ('şu an'), 'no'
     * ('TC kimlik no'), 'not' (Turkish for note), 'name'/'names' ('Common
     * Name'), 'key' ('.key'), 'try' (the TRY currency code), 'theme' and
     * 'modules' (theme.json, modules/Addons/), 'message' (a JSON field),
     * 'ticket' ('[Ticket #123456]'), 'create' (a module command), 'payout',
     * 'subject' and 'bundle'.
     *
     * This is zero, not a budget. One of these is a line a Turkish customer
     * cannot read, and there is no version of "some of them are fine".
     */
    $english = array_flip([
        // Function words: no Turkish sentence contains one, while a Turkish
        // sentence may perfectly well contain 'Stripe', 'SMTP' or 'WordPress'.
        'the', 'and', 'for', 'this', 'that', 'these', 'those', 'with', 'from', 'your', 'you',
        'yours', 'our', 'ours', 'their', 'them', 'they', 'will', 'would', 'shall', 'should',
        'could', 'been', 'being', 'are', 'was', 'were', 'is', 'yet', 'already', 'never',
        'always', 'into', 'onto', 'over', 'under', 'after', 'before', 'while', 'when',
        'where', 'which', 'who', 'whom', 'whose', 'why', 'how', 'about', 'between', 'among',
        'within', 'without', 'against', 'across', 'along', 'around', 'because', 'since',
        'until', 'unless', 'though', 'although', 'even', 'but', 'nor', 'either', 'neither',
        'both', 'many', 'much', 'most', 'less', 'least', 'than', 'each', 'any', 'all',
        'some', 'more', 'other', 'another', 'only', 'just', 'very', 'such', 'same',
        'still', 'here', 'there', 'back', 'down', 'off', 'through', 'to', 'of', 'as', 'by',
        'be', 'it', 'its', 'do', 'does', 'did', 'has', 'have', 'had', 'if', 'so', 'up',
        'we', 'us', 'per', 'new', 'old', 'yes',
        // Panel vocabulary: what the substitution list kept hitting.
        'account', 'accounts', 'action', 'actions', 'activate', 'active', 'activity',
        'add', 'address', 'administration', 'advanced', 'affiliate', 'affiliates',
        'amount', 'amounts', 'applied', 'area', 'attachment', 'attachments',
        'authentication', 'balance', 'banned', 'basic', 'body', 'branding', 'builder',
        'bundles', 'cancel', 'cancelled', 'cart', 'categories', 'category', 'client',
        'clients', 'close', 'code', 'codes', 'configuration', 'configured', 'contact',
        'contacts', 'cost', 'created', 'credential', 'credentials', 'credit', 'currencies',
        'currency', 'custom', 'dark', 'date', 'dates', 'deactivate', 'debit', 'default',
        'defaults', 'delete', 'deleted', 'department', 'departments', 'description',
        'detail', 'details', 'disable', 'disabled', 'discount', 'discounts',
        'documentation', 'domain', 'domains', 'download', 'downloads', 'draft',
        'earnings', 'edit', 'enable', 'enabled', 'escalation', 'exempt', 'expired',
        'expires', 'expiry', 'fee', 'fees', 'field', 'fields', 'file', 'files', 'filters',
        'found', 'general', 'generation', 'group', 'groups', 'hello', 'hidden', 'history',
        'immediate', 'inactive', 'information', 'install', 'installed', 'invoice',
        'invoices', 'item', 'items', 'keys', 'language', 'languages', 'level', 'light',
        'limiting', 'line', 'lines', 'link', 'links', 'list', 'lists', 'live', 'log',
        'logs', 'management', 'mass', 'mode', 'module', 'none', 'note', 'notes',
        'notification', 'notifications', 'open', 'option', 'optional', 'options', 'order',
        'orders', 'overdue', 'overview', 'page', 'pages', 'paid', 'patterns', 'patterns',
        'payment', 'payments', 'pending', 'please', 'preset', 'presets', 'price', 'prices',
        'pricing', 'priorities', 'priority', 'private', 'process', 'product', 'products',
        'public', 'published', 'put', 'quote', 'quotes', 'recipient', 'recipients',
        'references', 'referral', 'referrals', 'refresh', 'refund', 'refunds', 'reminder',
        'reminders', 'remove', 'renewal', 'renewals', 'replies', 'reply', 'required',
        'role', 'roles', 'rule', 'rules', 'save', 'search', 'select', 'selected', 'send',
        'sender', 'server', 'servers', 'service', 'services', 'set', 'settings',
        'shopping', 'sign', 'simple', 'statuses', 'status', 'sticky', 'store', 'stored',
        'submit', 'subscription', 'summary', 'suspended', 'suspension', 'tax', 'taxes',
        'template', 'templates', 'terminated', 'termination', 'themes', 'thanks',
        'threshold', 'tickets', 'time', 'times', 'total', 'totals', 'type', 'types',
        'unavailable', 'uninstall', 'unpaid', 'unpublished', 'update', 'updated',
        'upload', 'uploaded', 'use', 'used', 'user', 'users', 'using', 'value', 'values',
        'verification', 'view', 'visible', 'welcome',
    ]);

    $offenders = [];
    foreach (localeLines('tr') as $where => $value) {
        foreach (translationWords($value) as $word) {
            // A word carrying a Turkish letter is Turkish; only a run of plain
            // ASCII letters can be an English leftover. Matching whole words
            // this way also stops 'çalışmayı' being read as 'may'.
            if (! preg_match('/^[A-Za-z]+$/', $word)) {
                continue;
            }
            if (isset($english[strtolower($word)])) {
                $offenders[] = $where.'  '.$value;
                break;
            }
        }
    }

    expect($offenders)->toBe([], "Turkish that is still partly English:\n".implode("\n", $offenders));
});

test('no Turkish word is an English ending stuck onto a Turkish stem', function () {
    /*
     * The other half of the same machine pass. Where the list did not know a
     * word it left English behind (the test above); where it knew the stem but
     * not the grammar it glued the English ending onto the Turkish stem and
     * produced a word that exists in no language:
     *
     *   'Sistem Günlüks'          Günlük  + s      (System Logs)
     *   'Vadesi Geçmiş Faturas'   Fatura  + s      (Overdue Invoices)
     *   'Shd Sunucuing'           Sunucu  + ing    (Shared Hosting)
     *   'Genel Ayarlatings'       Ayarla  + tings  (General Settings)
     *   'Kayıted'                 Kayıt   + ed     (Registered)
     *   'Sistem Veribase'         Veri    + base   (System Database)
     *   'Yöneticiistration Area'  Yönetici + istration (Administration Area)
     *
     * Turkish does not pluralise with -s and has no -ing, so the shape itself
     * is the proof. The frequency rule is what keeps it honest: the stem has
     * to appear in lang/tr at least as often as the suffixed form, so a real
     * Turkish word is never mistaken for a stem plus an ending it happens to
     * end with. Anything English uses itself - 'Hosting', 'Settings' - is
     * checked against lang/en's vocabulary first and never reaches the rule.
     *
     * Zero, for the same reason as the test above.
     */
    $endings = [
        'istration', 'rmation', 'lation', 'ility', 'ation', 'tings', 'words', 'width',
        'right', 'ments', 'ator', 'ness', 'able', 'ible', 'tion', 'sion', 'ment', 'name',
        'size', 'zone', 'base', 'ress', 'ings', 'ity', 'ing', 'ons', 'out', 'es', 'ed', 's',
    ];

    $englishWords = [];
    foreach (localeLines('en') as $value) {
        foreach (translationWords($value) as $word) {
            $englishWords[mb_strtolower($word, 'UTF-8')] = true;
        }
    }

    $turkish = localeLines('tr');

    $frequency = [];
    foreach ($turkish as $value) {
        foreach (translationWords($value) as $word) {
            $lower = mb_strtolower($word, 'UTF-8');
            $frequency[$lower] = ($frequency[$lower] ?? 0) + 1;
        }
    }

    $offenders = [];
    foreach ($turkish as $where => $value) {
        foreach (translationWords($value) as $word) {
            $lower = mb_strtolower($word, 'UTF-8');
            if (isset($englishWords[$lower])) {
                continue;   // a word English itself uses; not ours to judge
            }

            foreach ($endings as $ending) {
                $length = mb_strlen($ending);
                if (mb_strlen($lower) <= $length + 3 || mb_substr($lower, -$length) !== $ending) {
                    continue;
                }

                $stem = mb_substr($lower, 0, mb_strlen($lower) - $length);
                if (isset($englishWords[$stem])) {
                    continue;   // an English word wearing an English ending
                }
                if (($frequency[$stem] ?? 0) >= ($frequency[$lower] ?? 0)) {
                    $offenders[] = $where.'  '.$value.'   ('.$word.' = '.$stem.' + '.$ending.')';
                    break 2;
                }
            }
        }
    }

    expect($offenders)->toBe([], "Turkish stems wearing English endings:\n".implode("\n", $offenders));
});

/**
 * Every key a locale file writes, with every line that writes it.
 *
 * Keyed by the full dotted key and holding line => value, so a key written
 * twice - once nested, once flat - comes back with both of its lines. That
 * shape is what the two tests below need: one asks whether the copies agree,
 * the other counts how many copies exist at all.
 *
 * @return array<string, array<int, string>>
 */
function localeFileRows(string $file): array
{
    $stack = [];
    $rows = [];

    foreach (file($file) as $number => $line) {
        if (preg_match('/^\s*\],?\s*$/', $line)) {
            array_pop($stack);

            continue;
        }
        if (preg_match("/^\s*'((?:[^'\\\\]|\\\\.)*)'\s*=>\s*\[\s*$/", $line, $matches)) {
            $stack[] = stripcslashes($matches[1]);

            continue;
        }
        if (preg_match("/^\s*'((?:[^'\\\\]|\\\\.)*)'\s*=>\s*'((?:[^'\\\\]|\\\\.)*)'\s*,\s*$/", $line, $matches)) {
            $key = implode('.', array_merge($stack, [str_replace(["\\'", '\\\\'], ["'", '\\'], $matches[1])]));
            $rows[$key][$number + 1] = str_replace(["\\'", '\\\\'], ["'", '\\'], $matches[2]);
        }
    }

    return $rows;
}

test('a key written twice says the same thing on both lines', function () {
    /*
     * These files write many keys twice, once inside a nested block and once
     * as a flat dotted key, and Laravel returns the flat one. A shadowed line
     * is therefore invisible - which is how 396 of them kept their English
     * through a round that fixed everything visible. Keeping the copies in
     * step is what makes the two tests above trustworthy, because it means the
     * line they read is the line the customer gets.
     */
    $duplicates = [];
    foreach (glob(base_path('lang/tr/*.php')) as $file) {
        foreach (localeFileRows($file) as $key => $written) {
            if (count(array_unique($written)) > 1) {
                $duplicates[] = basename($file).' '.$key.' is written as '.implode(' and ', array_map(
                    fn ($line, $value) => "'".$value."' (line ".$line.')',
                    array_keys($written),
                    $written,
                ));
            }
        }
    }

    expect($duplicates)->toBe([], "One key, two answers - the shadowed one is the one nobody sees:\n".implode("\n", $duplicates));
});

test('no shipped translation is blank', function () {
    // An empty value is worse than a missing key: Laravel falls back to English
    // for a missing key and prints nothing at all for an empty one.
    $english = localeValues('en');

    $blank = [];
    foreach (array_merge(['en'], array_keys(TRANSLATION_GAP_BUDGET)) as $locale) {
        foreach (localeValues($locale) as $key => $value) {
            if (trim($value) === '' && trim($english[$key] ?? '') !== '') {
                $blank[] = $locale.'.'.$key;
            }
        }
    }

    expect($blank)->toBe([], 'Blank translations render as nothing at all: '.implode(', ', $blank));
});

/*
 * ================================================================
 * THE THIRD FAMILY: THE WORDS WERE TURKISH AND THE SENTENCE WAS NOT
 * ================================================================
 *
 * The two tests above catch a machine pass that left English behind
 * ('Görüntüle API Documentation') or glued an English ending onto a Turkish
 * stem ('Shd Sunucuing'). Both read WORDS. Both passed on the day the admin
 * panel's buttons said:
 *
 *   admin.clients.add_client      'Ekle Müşteri'        Add Client
 *   admin.invoices.create_invoice 'Oluştur Fatura'      Create Invoice
 *   admin.invoices.download_pdf   'Indir PDF'           Download PDF
 *   admin.invoices.client_info    'Müşteri Bilgi'       Client Info
 *   admin.domains.expiry_date     'Son Kullanma Tarih'  Expiry Date
 *   admin.nav.transactions        'Işlemler'            Transactions
 *
 * Every word there is a Turkish word. Nothing is left in English, nothing
 * wears an English ending, nothing is identical to the English, and every key
 * is present - so all six of the value tests above said yes. What the machine
 * kept was the ENGLISH GRAMMAR: English puts the verb first ('Add Client'),
 * Turkish puts it last ('Müşteri Ekle'); English juxtaposes two nouns ('Client
 * Info'), Turkish marks the head of the compound ('Müşteri Bilgileri');
 * English has one capital I, Turkish has two and they are different letters
 * ('Işlemler' is not a word, 'İşlemler' is).
 *
 * 171 lines carried it. The three tests below are the three shapes, each one
 * mechanically decidable from the string alone - which is why they are here
 * and why the fourth shape, below, is not.
 *
 * WHAT THESE CANNOT CATCH, said plainly: a sentence that is fluent Turkish and
 * says the wrong thing. No detector in this file reads meaning. They catch the
 * residue of a substitution pass, nothing else, and a translation that is
 * simply wrong will sail past all six.
 */

/**
 * The verb a Turkish label puts last and this one puts first, or null.
 *
 * Turkish is verb-final: the button is 'Müşteri Ekle', never 'Ekle Müşteri'.
 * A label that opens with one of these verbs is therefore English word order
 * with Turkish words in it.
 *
 * EVERY VERB BELOW WAS GREPPED AGAINST lang/tr AND LEADS NO CORRECT LABEL
 * THERE - the same discipline the English-word list above is held to. The ones
 * deliberately left out are the ones that do lead correctly: 'Ara' ('Ara
 * Toplam' = subtotal), 'Askıya' ('Askıya Al'), 'Geri' ('Geri Dön'), 'İçe'/'Dışa'
 * ('İçe Aktar'), 'Yeniden' ('Yeniden Gönder'), 'Devre' ('Devre Dışı Bırak') and
 * 'Test' ('Test Gönder', where Test is the object and Gönder is already last).
 */
function turkishLabelVerbFirst(string $value): ?string
{
    $verbs = [
        'Ekle', 'Sil', 'Düzenle', 'Oluştur', 'Görüntüle', 'Kaldır', 'Yükle', 'Engelle',
        'Seç', 'Onayla', 'Reddet', 'Kapat', 'İndir', 'Güncelle', 'Yenile', 'Başlat',
        'Durdur', 'Kopyala', 'Taşı', 'Yazdır', 'Değiştir', 'Filtrele', 'Etkinleştir',
    ];

    // A label, not prose: a sentence ends in punctuation and may legitimately
    // open with an imperative ('Seçin ve kaydedin.').
    if (preg_match('/[.!?:,;]/u', $value)) {
        return null;
    }

    $words = preg_split('/\s+/u', trim($value));

    if (count($words) < 2 || count($words) > 4) {
        return null;
    }

    // 'Ekle ve Kapat' is two verbs joined, not a verb with its object behind it.
    if (in_array($words[1], ['ve', 'veya', 'ya'], true)) {
        return null;
    }

    return in_array($words[0], $verbs, true) ? $words[0] : null;
}

test('no Turkish label is written in English word order', function () {
    $offenders = [];

    foreach (localeLines('tr') as $where => $value) {
        if ($verb = turkishLabelVerbFirst($value)) {
            $offenders[] = $where.'  '.$value.'   ('.$verb.' belongs at the end)';
        }
    }

    expect($offenders)->toBe([], sprintf(
        "Turkish words in English word order. Turkish puts the verb last:\n".
        "'Ekle Müşteri' is 'Müşteri Ekle', 'Oluştur Fatura' is 'Fatura Oluştur'.\n".
        "If the verb genuinely belongs first, it is a verb that leads a correct\n".
        "label and does not belong in the list in\n".
        "tests/Feature/TranslationParityTest.php:turkishLabelVerbFirst().\n%s",
        implode("\n", $offenders)
    ));
});

/**
 * A word written with the ASCII capital I where Turkish requires İ, or null.
 *
 * Turkish has two i's: dotted i/İ and dotless ı/I. They are different letters,
 * so 'Iptal' and 'Işlem' are not misspellings of 'İptal' and 'İşlem' - they are
 * not words. A machine that uppercased with the English rules produced them.
 *
 * Only flagged ON EVIDENCE: the same word must appear elsewhere in lang/tr
 * spelled with İ or with a lowercase i. That is what keeps 'Izgaradaki' (from
 * 'ızgara', a genuinely dotless word) out of the list, and it is also this
 * detector's limit - a word that exists in lang/tr ONLY in the broken spelling
 * has nothing to be judged against and passes.
 *
 * @param  array<string, true>  $spellings  every word in lang/tr, as written
 */
function turkishDottedCapital(string $value, array $spellings): ?string
{
    foreach (translationWords($value) as $word) {
        if (! preg_match('/^I\p{Ll}+$/u', $word)) {
            continue;
        }

        $rest = mb_substr($word, 1);

        if (isset($spellings['İ'.$rest]) || isset($spellings['i'.$rest])) {
            return $word;
        }
    }

    return null;
}

test('Turkish writes the dotted capital İ where its alphabet requires it', function () {
    $lines = localeLines('tr');

    $spellings = [];
    foreach ($lines as $value) {
        foreach (translationWords($value) as $word) {
            $spellings[$word] = true;
        }
    }

    $offenders = [];
    foreach ($lines as $where => $value) {
        if ($word = turkishDottedCapital($value, $spellings)) {
            $offenders[] = $where.'  '.$value.'   ('.$word.' should be İ'.mb_substr($word, 1).')';
        }
    }

    expect($offenders)->toBe([], sprintf(
        "Turkish has two capital i's and these lines use the English one.\n".
        "'Iptal' and 'Işlem' are not words; 'İptal' and 'İşlem' are. Each of\n".
        "these is already spelled with İ somewhere else in lang/tr, which is\n".
        "how the test knows:\n%s",
        implode("\n", $offenders)
    ));
});

/**
 * The bare head noun a Turkish compound should have marked, or null.
 *
 * English builds a compound by putting two nouns side by side - 'Client Info',
 * 'Expiry Date'. Turkish marks the second one: 'Müşteri Bilgileri', 'Son
 * Kullanma Tarihi'. A substitution pass translates both words and leaves the
 * suffix off, which is how the invoice screen came to say 'Müşteri Bilgi'.
 *
 * The heads below are the ones that actually occurred, so this is a net with
 * known holes, not a rule: a compound ending in a head noun nobody has mangled
 * yet ('... Şifre') passes. It is here to keep 65 repaired lines repaired.
 */
function turkishBareCompoundHead(string $value): ?string
{
    $heads = ['Tarih', 'Ayarlar', 'Bilgi', 'Detaylar', 'Anahtar', 'Adres', 'Numara',
        'Boyut', 'Konu', 'Durum', 'Sayfa', 'Yöntem', 'Listesi', 'Modu', 'Süre',
        'Hesap', 'Grup', 'Metin', 'Türü'];

    /*
     * Judged one by one, and every one of them is adjective + noun, where
     * Turkish marks nothing: 'Genel Ayarlar' (general settings), 'Yeni Durum'
     * (new status). It is the NOUN + noun compounds that take the suffix, and
     * no rule in a regex can tell the two apart - so they are listed.
     */
    $correct = [
        'Temel Adres', 'Alt Bilgi', 'Yeni Durum', 'İndirim Türü', 'Yapılacaklar Listesi',
        'Ek Süre', 'Yayınlanabilir Anahtar', 'Test (Sandbox) Modu', 'Gizli Anahtar',
        'Başarısız Durum', 'Bekleyen Durum', 'Gönderilen Durum', 'Genel Ayarlar',
        'Yeni Grup', 'Doldurma Türü', 'En Fazla Hesap', 'Bakım Modu', 'Alan Türü',
        'Uzun Metin', 'Son Tarih', 'İptal Türü', 'Sertifika Türü', 'Özel Anahtar',
        'Web Sunucusu Türü', 'Açık Adres', 'Ana Sayfa', 'Komisyon Türü',
    ];

    if (preg_match('/[.!?:,;]/u', $value) || in_array($value, $correct, true)) {
        return null;
    }

    $words = preg_split('/\s+/u', trim($value));

    if (count($words) < 2 || count($words) > 4) {
        return null;
    }

    $head = end($words);

    return in_array($head, $heads, true) ? $head : null;
}

test('a Turkish compound carries the suffix English leaves off', function () {
    $offenders = [];

    foreach (localeLines('tr') as $where => $value) {
        if ($head = turkishBareCompoundHead($value)) {
            $offenders[] = $where.'  '.$value.'   ('.$head.' is unmarked)';
        }
    }

    expect($offenders)->toBe([], sprintf(
        "A Turkish compound marks its head noun: 'Client Info' is 'Müşteri\n".
        "Bilgileri', not 'Müşteri Bilgi'; 'Expiry Date' is 'Son Kullanma\n".
        "Tarihi', not 'Son Kullanma Tarih'. If this label is adjective + noun\n".
        "and takes no suffix - 'Genel Ayarlar' - add it to the judged list in\n".
        "tests/Feature/TranslationParityTest.php:turkishBareCompoundHead().\n%s",
        implode("\n", $offenders)
    ));
});

/*
 * ================================================================
 * A RATCHET, NOT A CLEAN BILL OF HEALTH
 * ================================================================
 *
 * THE LEDGER BELOW IS THE NAMED PLACE FOR THIS DEBT, and the file it is named
 * in is this one, tests/Feature/TranslationParityTest.php - the same way
 * tests/Feature/HardcodedViewTextTest.php carries its own. The numbers may
 * fall. They may never rise.
 *
 * WHAT IS BEING COUNTED. These files write 1,697 keys twice: once inside a
 * nested block, once as a flat dotted key. Laravel answers with the flat one,
 * because Arr::exists() tests the whole dotted key before it splits on dots -
 * so the other line is unreachable, and unreachable is where English hides. It
 * is where 220 raw-English lines and 396 divergent copies sat through a round
 * that fixed everything visible, and it is why the value tests in this file
 * read localeLines() - every written line - instead of the resolved value.
 *
 * WHY IT IS A CEILING AND NOT A ZERO. Deleting the twins is a change to the
 * shape of lang/en and lang/tr, which is not a translation fix; lang/en has the
 * identical shape, and every other locale was generated from it. That is a
 * deliberate piece of work for somebody who can run the whole product against
 * it, not a side effect of a translation round. So this test does the one thing
 * that is honestly available: it stops the hole being dug deeper.
 *
 * WHEN THIS TEST FAILS you added a key that is already written somewhere else
 * in the same file - almost always by adding a flat 'group.key' line next to a
 * nested block that already has it, or the other way round. Put your string on
 * the line that is already there. If you deliberately removed a twin, LOWER the
 * number. If you have a reason to add one, raise it and say why in the commit.
 *
 * A file at 544 is not a file with 544 bugs. It is a file with 544 places where
 * one of the two lines is invisible, and the tests above are what keep the
 * invisible one honest. Measured 2026-09-21.
 */
const DUPLICATE_KEY_BUDGET = [
    'en/admin.php' => 546,
    'en/client.php' => 209,
    'en/common.php' => 61,
    'en/errors.php' => 2,
    'en/messages.php' => 33,
    'tr/admin.php' => 544,
    'tr/client.php' => 208,
    'tr/common.php' => 61,
    'tr/messages.php' => 33,
];

/** @return array<string, int> "locale/file.php" => how many keys it writes more than once */
function duplicateKeyCounts(): array
{
    $counts = [];

    foreach (['en', 'tr'] as $locale) {
        foreach (glob(base_path('lang/'.$locale.'/*.php')) as $file) {
            $duplicated = 0;
            foreach (localeFileRows($file) as $written) {
                if (count($written) > 1) {
                    $duplicated++;
                }
            }

            if ($duplicated > 0) {
                $counts[$locale.'/'.basename($file)] = $duplicated;
            }
        }
    }

    return $counts;
}

test('the shadowed-key debt does not grow', function () {
    $counts = duplicateKeyCounts();

    // A parser that quietly stopped matching would report nothing and pass
    // every budget in the ledger. The two files that carry the bulk of it are
    // named here so that cannot happen silently.
    expect($counts)->toHaveKeys(['en/admin.php', 'tr/admin.php']);

    $worse = [];
    foreach ($counts as $file => $duplicated) {
        $budget = DUPLICATE_KEY_BUDGET[$file] ?? 0;

        if ($duplicated > $budget) {
            $worse[] = sprintf('lang/%s writes %d keys twice, budget %d', $file, $duplicated, $budget);
        }
    }

    expect($worse)->toBe([], sprintf(
        "%s\n\n".
        "A key written twice has one line Laravel answers with and one line\n".
        "nobody will ever see. Put your string on the line that already exists\n".
        "rather than adding a second one. If you removed a twin, lower that\n".
        "file's number in DUPLICATE_KEY_BUDGET in\n".
        "tests/Feature/TranslationParityTest.php. This is a ratchet, not a\n".
        "statement that the remaining %d are fine.",
        implode("\n", $worse),
        array_sum(DUPLICATE_KEY_BUDGET)
    ));
});

test('the shadowed-key ledger has no lines for files that are gone', function () {
    // A budget for a file that no longer exists would wave the file through if
    // it ever came back, on the strength of a number nobody remembers agreeing
    // to - and it would hide the fact that the debt was paid off.
    $stale = [];
    foreach (array_keys(DUPLICATE_KEY_BUDGET) as $file) {
        if (! is_file(base_path('lang/'.$file))) {
            $stale[] = $file;
        }
    }

    expect($stale)->toBe([], sprintf(
        "These files are in DUPLICATE_KEY_BUDGET and no longer exist. Delete\n".
        "their lines from tests/Feature/TranslationParityTest.php:\n%s",
        implode("\n", $stale)
    ));
});

/*
 * The three detectors have to be able to fail, or the three tests above are
 * decoration that will pass for ever.
 *
 * Every string on the left is one this repository actually shipped - the
 * file:line it came from is in the comment - and every string on the right is
 * what replaced it. The pair is the point: a detector that flags both is as
 * useless as one that flags neither, because the second kind gets switched off
 * within a week of somebody writing correct Turkish.
 */
test('the Turkish detectors can actually fail', function () {
    $wordOrder = [
        // admin.php:713 / :932 / :1006      'Add Client'
        'Ekle Müşteri' => 'Müşteri Ekle',
        // admin.php:1244 / :1301 / :2439    'Create Invoice'
        'Oluştur Fatura' => 'Fatura Oluştur',
        // admin.php:2782                    'Close Ticket'
        'Kapat Destek Talebi' => 'Destek Talebini Kapat',
        // admin.php:780                     'Update Client'
        'Güncelle Müşteri' => 'Müşteriyi Güncelle',
    ];

    foreach ($wordOrder as $broken => $fixed) {
        expect(turkishLabelVerbFirst($broken))->not->toBeNull("'{$broken}' should be flagged")
            ->and(turkishLabelVerbFirst($fixed))->toBeNull("'{$fixed}' is correct Turkish and must not be flagged");
    }

    // Correct labels that open with a word this detector must leave alone.
    foreach (['Ara Toplam', 'Askıya Al', 'Geri Dön', 'İçe Aktar', 'Yeniden Gönder'] as $correct) {
        expect(turkishLabelVerbFirst($correct))->toBeNull("'{$correct}' must not be flagged");
    }

    $spellings = [];
    foreach (localeLines('tr') as $value) {
        foreach (translationWords($value) as $word) {
            $spellings[$word] = true;
        }
    }

    $capitals = [
        'Iptal Et' => 'İptal Et',           // admin.php:2562, client.php:760
        'Indirmeler' => 'İndirmeler',       // client.php:489, admin.php:800
        'Işlemler' => 'İşlemler',           // admin.php:1750  'Transactions'
        'Site Ikonu' => 'Site İkonu',       // admin.php:397   'Favicon'
    ];

    foreach ($capitals as $broken => $fixed) {
        expect(turkishDottedCapital($broken, $spellings))->not->toBeNull("'{$broken}' should be flagged")
            ->and(turkishDottedCapital($fixed, $spellings))->toBeNull("'{$fixed}' is correct and must not be flagged");
    }

    // 'ızgara' is genuinely dotless, so its capital genuinely is I. The
    // evidence rule is the only thing standing between this and a false
    // failure, and admin.php:1903 is the line that proves it.
    expect(turkishDottedCapital('Izgaradaki sıra', $spellings))->toBeNull();

    $compounds = [
        'Müşteri Bilgi' => 'Müşteri Bilgileri',          // admin.php:1241  'Client Info'
        'Son Kullanma Tarih' => 'Son Kullanma Tarihi',   // admin.php:1065  'Expiry Date'
        'E-posta Ayarlar' => 'E-posta Ayarları',         // admin.php:2342  'Email Settings'
        'Ödeme Yöntem' => 'Ödeme Yöntemi',               // admin.php:1075  'Payment Method'
    ];

    foreach ($compounds as $broken => $fixed) {
        expect(turkishBareCompoundHead($broken))->not->toBeNull("'{$broken}' should be flagged")
            ->and(turkishBareCompoundHead($fixed))->toBeNull("'{$fixed}' is correct and must not be flagged");
    }

    // Adjective + noun takes no suffix; the judged list is what keeps these out.
    foreach (['Genel Ayarlar', 'Yeni Durum', 'Ana Sayfa', 'Gizli Anahtar'] as $correct) {
        expect(turkishBareCompoundHead($correct))->toBeNull("'{$correct}' must not be flagged");
    }
});
