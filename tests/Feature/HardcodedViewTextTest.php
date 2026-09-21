<?php

/*
 * A RATCHET, NOT A CLEAN BILL OF HEALTH. Read this before you touch the numbers.
 *
 * Two public marketing pages - resources/views/pages/ssl.blade.php and
 * resources/views/pages/mail-setup.blade.php - carried 34 and 96 strings of
 * Turkish and English written straight into the markup, with not one call to
 * __() between them. Which language a visitor got was decided by an expression
 * at the top of each file, and that expression was wrong: it asked whether the
 * locale was NOT English, so every German, Polish, Chinese and Arabic visitor
 * was served Turkish on the page a search engine indexes and the page support
 * links to most. Nothing failed, because no test has ever looked at what a
 * blade prints that the translator did not produce.
 *
 * This test looks. What it CANNOT do is demand zero: there are still hundreds
 * of hardcoded English strings across the admin screens and the installer, and
 * a test that fails on all of them today is a test the next person switches
 * off. So it writes down what each file carries right now and fails only when
 * a file carries MORE. The numbers below may fall. They may never rise.
 *
 * The number will never reach zero either, and should not be expected to: a
 * fair share of what is counted is brand and protocol names - Outlook, IMAP,
 * SMTP, STARTTLS, cPanel/WHM, VISA - which no language translates. That is the
 * honest state of it. A file at 14 is not a file with 14 bugs; it is a file
 * with at most 14, and a file at 0 is one that cannot regress.
 *
 * WHEN THIS TEST FAILS, you added user-visible text to a blade that no
 * translation call produced. Wrap it in __('group.key') and add the English to
 * lang/en/<group>.php - or seed it into dynamic_translations, the way the
 * migrations in database/migrations do, if it belongs to the client area. If
 * what you added genuinely needs no translation - a brand name, a protocol, a
 * unit symbol - raise that file's number in the ledger in THIS FILE,
 * tests/Feature/HardcodedViewTextTest.php, and say why in the commit message.
 *
 * resources/views/legal/ is exempt in full and is not a defect. Those documents
 * are written once per language on purpose - legal/en/* and legal/tr/* are
 * separate texts, not translations of each other, and legal/index,
 * legal/layout and legal/document switch between them inline by design.
 */

/** Blade files this test reads: every view except the legal documents. */
function scannedViews(): array
{
    $files = [];
    $views = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('views')));

    foreach ($views as $file) {
        $path = (string) $file;

        if (! preg_match('/\.blade\.php$/', $path)) {
            continue;
        }

        $relative = str_replace(base_path().'/', '', $path);

        if (str_starts_with($relative, 'resources/views/legal/')) {
            continue;
        }

        $files[$relative] = $path;
    }

    ksort($files);

    return $files;
}

/**
 * The text a visitor reads on this page that no translation call produced.
 *
 * Everything the visitor does not read is blanked first - blade and HTML
 * comments, @php blocks, <script>, <style>, <pre> and <code> samples, every
 * {{ }} and {!! !!} echo (which is where __() lives, so a translated string
 * disappears here), and every blade directive. What is left between the tags,
 * plus the four attributes a visitor actually reads, is hardcoded text.
 *
 * @return list<string>
 */
function hardcodedViewText(string $path): array
{
    $source = file_get_contents($path);

    // Blanked rather than deleted, so nothing on either side of a removal is
    // joined into a word that was never in the file.
    $strip = function (string $pattern, string $subject): string {
        return preg_replace_callback(
            $pattern,
            fn ($m) => preg_replace('/[^\n]/', ' ', $m[0]),
            $subject
        );
    };

    $source = $strip('/\{\{--.*?--\}\}/s', $source);
    $source = $strip('/<!--.*?-->/s', $source);
    $source = $strip('/@verbatim.*?@endverbatim/s', $source);
    $source = $strip('/@php.*?@endphp/s', $source);
    $source = $strip('/<script\b[^>]*>.*?<\/script>/is', $source);
    $source = $strip('/<style\b[^>]*>.*?<\/style>/is', $source);
    // A command line or a code sample is copied, not read, and is not
    // translated in any language.
    $source = $strip('/<pre\b[^>]*>.*?<\/pre>/is', $source);
    $source = $strip('/<code\b[^>]*>.*?<\/code>/is', $source);
    $source = $strip('/\{!!.*?!!\}/s', $source);
    $source = $strip('/\{\{.*?\}\}/s', $source);
    // A directive, not the "@" of an email address: "info@example.com" must
    // keep its domain, which an unguarded /@[a-zA-Z]+/ eats as @example.
    $source = $strip('/(?<![\w.@-])@[a-zA-Z]+\s*\((?:[^()]|\((?:[^()]|\([^()]*\))*\))*\)/s', $source);
    $source = $strip('/(?<![\w.@-])@[a-zA-Z]+/', $source);

    $found = [];

    // The attributes a visitor reads are kept; every other attribute is thrown
    // away with the tag it sits in.
    $source = preg_replace_callback('/<[^>]*>/s', function ($match) use (&$found) {
        preg_match_all(
            '/\b(?:placeholder|title|alt|aria-label)\s*=\s*"([^"]*)"/i',
            $match[0],
            $attributes,
            PREG_SET_ORDER
        );

        foreach ($attributes as $attribute) {
            $found[] = $attribute[1];
        }

        return preg_replace('/[^\n]/', ' ', $match[0]);
    }, $source);

    foreach (explode("\n", $source) as $line) {
        // "Save | Cancel" is two strings, not one.
        foreach (preg_split('/\s*\|\s*/', $line) as $piece) {
            $found[] = $piece;
        }
    }

    $text = [];

    foreach ($found as $candidate) {
        $candidate = trim(html_entity_decode($candidate, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        // Two letters in a row: a word someone reads. Punctuation, numbers,
        // currency symbols and bare markup leftovers are not text.
        if ($candidate === '' || ! preg_match('/\p{L}{2,}/u', $candidate)) {
            continue;
        }

        $text[] = $candidate;
    }

    return $text;
}

/*
 * Measured 2026-09-20: 345 strings across 94 views. Lower a number when you
 * translate something; a view not listed here carries none and must stay that
 * way. THE NUMBERS MAY ONLY EVER GO DOWN.
 */
const HARDCODED_VIEW_TEXT_BUDGET = [
    'resources/views/admin/affiliates/show.blade.php' => 1,
    'resources/views/admin/api-docs.blade.php' => 5,
    'resources/views/admin/auth/enable-two-factor.blade.php' => 1,
    'resources/views/admin/auth/login.blade.php' => 3,
    'resources/views/admin/auth/two-factor.blade.php' => 1,
    'resources/views/admin/bulk/mass-email.blade.php' => 3,
    'resources/views/admin/calendar/index.blade.php' => 1,
    'resources/views/admin/clients/edit.blade.php' => 1,
    'resources/views/admin/clients/show.blade.php' => 13,
    'resources/views/admin/config/addon-output.blade.php' => 1,
    'resources/views/admin/config/admins.blade.php' => 1,
    'resources/views/admin/config/announcements.blade.php' => 1,
    'resources/views/admin/config/api-credentials.blade.php' => 1,
    'resources/views/admin/config/automation.blade.php' => 1,
    'resources/views/admin/config/billable-items.blade.php' => 1,
    'resources/views/admin/config/bundles.blade.php' => 4,
    'resources/views/admin/config/currencies.blade.php' => 1,
    'resources/views/admin/config/custom-fields.blade.php' => 2,
    'resources/views/admin/config/domain-pricing.blade.php' => 3,
    'resources/views/admin/config/downloads.blade.php' => 1,
    'resources/views/admin/config/email-templates.blade.php' => 1,
    'resources/views/admin/config/knowledge-base.blade.php' => 1,
    'resources/views/admin/config/languages/index.blade.php' => 1,
    'resources/views/admin/config/languages/translations.blade.php' => 2,
    'resources/views/admin/config/notifications.blade.php' => 19,
    'resources/views/admin/config/promotions.blade.php' => 4,
    'resources/views/admin/config/servers.blade.php' => 22,
    'resources/views/admin/config/system-database.blade.php' => 2,
    'resources/views/admin/config/tax.blade.php' => 2,
    'resources/views/admin/config/ticket-departments.blade.php' => 4,
    'resources/views/admin/config/ticket-escalation.blade.php' => 5,
    'resources/views/admin/config/ticket-spam.blade.php' => 3,
    'resources/views/admin/domains/index.blade.php' => 3,
    'resources/views/admin/domains/show.blade.php' => 4,
    'resources/views/admin/invoices/create.blade.php' => 8,
    'resources/views/admin/invoices/show.blade.php' => 2,
    'resources/views/admin/layouts/app.blade.php' => 2,
    'resources/views/admin/logs/module.blade.php' => 1,
    'resources/views/admin/orders/show.blade.php' => 2,
    'resources/views/admin/products/create-group.blade.php' => 1,
    'resources/views/admin/products/create.blade.php' => 1,
    'resources/views/admin/products/edit.blade.php' => 18,
    'resources/views/admin/products/index.blade.php' => 3,
    'resources/views/admin/projects/create.blade.php' => 1,
    'resources/views/admin/projects/edit.blade.php' => 1,
    'resources/views/admin/projects/index.blade.php' => 1,
    'resources/views/admin/projects/show.blade.php' => 3,
    'resources/views/admin/quotes/create.blade.php' => 3,
    'resources/views/admin/quotes/edit.blade.php' => 2,
    'resources/views/admin/quotes/show.blade.php' => 1,
    'resources/views/admin/reports/show.blade.php' => 1,
    'resources/views/admin/services/show.blade.php' => 2,
    'resources/views/admin/settings/appearance.blade.php' => 16,
    'resources/views/admin/settings/general.blade.php' => 17,
    'resources/views/admin/ssl/show.blade.php' => 2,
    'resources/views/admin/whois.blade.php' => 3,
    'resources/views/client/account/payment_methods.blade.php' => 1,
    'resources/views/client/announcements/show.blade.php' => 1,
    'resources/views/client/auth/enable-two-factor.blade.php' => 1,
    'resources/views/client/auth/forgot-password.blade.php' => 2,
    'resources/views/client/auth/login.blade.php' => 2,
    'resources/views/client/auth/reset-password.blade.php' => 2,
    'resources/views/client/cart/configure.blade.php' => 2,
    'resources/views/client/domain-pricing.blade.php' => 1,
    'resources/views/client/domains/show.blade.php' => 1,
    'resources/views/client/domains/transfer.blade.php' => 1,
    'resources/views/client/layouts/app.blade.php' => 1,
    'resources/views/client/services/hosting/backups.blade.php' => 3,
    'resources/views/client/services/hosting/containers.blade.php' => 2,
    'resources/views/client/services/hosting/databases.blade.php' => 3,
    'resources/views/client/services/hosting/email.blade.php' => 2,
    'resources/views/client/services/hosting/files.blade.php' => 1,
    'resources/views/client/services/hosting/ftp.blade.php' => 3,
    'resources/views/client/services/hosting/subdomains.blade.php' => 5,
    'resources/views/client/services/show.blade.php' => 2,
    'resources/views/client/ssl/configure.blade.php' => 2,
    'resources/views/emails/bulk-mass.blade.php' => 1,
    'resources/views/emails/login-email-changed.blade.php' => 3,
    'resources/views/emails/password-reset.blade.php' => 5,
    'resources/views/emails/service-welcome.blade.php' => 2,
    'resources/views/errors/403.blade.php' => 1,
    'resources/views/errors/404.blade.php' => 1,
    'resources/views/errors/419.blade.php' => 1,
    'resources/views/errors/500.blade.php' => 1,
    'resources/views/install/admin.blade.php' => 9,
    'resources/views/install/app.blade.php' => 19,
    'resources/views/install/database.blade.php' => 9,
    'resources/views/install/finish.blade.php' => 8,
    'resources/views/install/layout.blade.php' => 5,
    'resources/views/install/requirements.blade.php' => 9,
    'resources/views/pages/mail-setup.blade.php' => 14,
    'resources/views/pdf/invoice.blade.php' => 1,
    'resources/views/sections/footer.blade.php' => 6,
    'resources/views/sections/navigation.blade.php' => 2,
];

test('no view gains hardcoded text', function () {
    $worse = [];
    $total = 0;

    foreach (scannedViews() as $relative => $path) {
        $found = hardcodedViewText($path);
        $total += count($found);
        $budget = HARDCODED_VIEW_TEXT_BUDGET[$relative] ?? 0;

        if (count($found) > $budget) {
            $worse[] = sprintf(
                "%s carries %d hardcoded strings, budget %d:\n      %s",
                $relative,
                count($found),
                $budget,
                implode("\n      ", array_slice($found, 0, 8))
            );
        }
    }

    expect($worse)->toBe([], sprintf(
        "Text a visitor reads, that no translation call produced.\n\n%s\n\n".
        "Wrap it in __('group.key') and add the English to lang/en/<group>.php,\n".
        "or seed it into dynamic_translations the way the migrations in\n".
        "database/migrations do. If it is a brand name, a protocol or a unit\n".
        "symbol that no language translates, raise that file's number in\n".
        "tests/Feature/HardcodedViewTextTest.php and say why in the commit.\n".
        "Today's total across all views is %d.",
        implode("\n\n", $worse),
        $total
    ));
});

/*
 * A ledger line for a view that no longer exists is not harmless: the view can
 * come back, dirty, and this test would wave it through on the strength of a
 * number nobody remembers agreeing to.
 */
test('the hardcoded-text ledger has no lines for views that are gone', function () {
    $stale = array_values(array_diff(
        array_keys(HARDCODED_VIEW_TEXT_BUDGET),
        array_keys(scannedViews())
    ));

    expect($stale)->toBe([], sprintf(
        "These views are listed in HARDCODED_VIEW_TEXT_BUDGET and no longer exist.\n".
        "Delete their lines from tests/Feature/HardcodedViewTextTest.php:\n%s",
        implode("\n", $stale)
    ));
});

/*
 * The detector has to be able to fail, or the two tests above are decoration.
 *
 * resources/views/pages/ssl.blade.php is the page this whole round started
 * from: 34 strings of inline Turkish, now every one of them behind
 * client.pages.ssl.*, and 0 in the ledger. Putting a sentence back into it must
 * be seen.
 */
test('the detector sees text put back into a view that was cleaned', function () {
    $path = resource_path('views/pages/ssl.blade.php');
    $original = file_get_contents($path);

    expect(hardcodedViewText($path))->toBe([]);

    // On a copy, never on the view itself. A test that edits a file another
    // test is reading is a test that fails somebody else's run, and a run cut
    // short would leave the sentence in the product.
    $copy = sys_get_temp_dir().'/pnlcs-hardcoded-'.getmypid().'.blade.php';

    try {
        file_put_contents($copy, $original."\n<p>Sertifikanız otomatik yenilenir</p>\n");

        expect(hardcodedViewText($copy))->toBe(['Sertifikanız otomatik yenilenir']);
    } finally {
        @unlink($copy);
    }
});
