<?php

/*
 * The two public guide pages: /ssl and /guides/mail-setup.
 *
 * They used to carry their own English and Turkish inline, the way
 * resources/views/pages/about.blade.php does, and which one a visitor got was
 * decided by a single expression at the top of each file. It was written two
 * different ways: about.blade.php asks whether the locale IS Turkish, the other
 * two asked whether it is NOT English. A German, Polish or Chinese visitor
 * therefore got Turkish on /ssl and /guides/mail-setup - the German word for
 * "Email Setup" is not "E-posta Kurulumu".
 *
 * The text now lives in client.pages.ssl.* and client.pages.mail_setup.*, so
 * there is no such expression left to get wrong; what a visitor reads is
 * decided by the locale alone. These tests hold the same promise across that
 * move, and add the two it did not make before: that an English visitor reads
 * English, and that neither page can leak a key it has no translation for.
 *
 * These are marketing pages: the one a search engine indexes and the one the
 * most common support request links to.
 */
test('a non-Turkish visitor does not get Turkish on the public guides', function (string $route) {
    foreach (['en', 'de', 'zh', 'pl'] as $locale) {
        app()->setLocale($locale);

        $body = $this->get(route($route))->assertOk()->getContent();

        expect($body)->not->toContain('E-posta Kurulumu')
            ->and($body)->not->toContain('SSL Sertifikaları')
            ->and($body)->not->toContain('Nasıl çalışıyor');
    }
})->with(['pages.ssl', 'pages.mail-setup']);

test('a Turkish visitor gets Turkish on the public guides', function () {
    app()->setLocale('tr');

    expect($this->get(route('pages.ssl'))->assertOk()->getContent())->toContain('SSL Sertifikaları')
        ->and($this->get(route('pages.mail-setup'))->assertOk()->getContent())->toContain('E-posta Kurulumu');
});

test('an English visitor gets English on the public guides', function () {
    app()->setLocale('en');

    $ssl = $this->get(route('pages.ssl'))->assertOk()->getContent();
    $mail = $this->get(route('pages.mail-setup'))->assertOk()->getContent();

    // A heading, a body sentence and a list item from each page: a page that
    // rendered its title and nothing else would otherwise pass.
    expect($ssl)->toContain('SSL Certificates')
        ->and($ssl)->toContain('What it covers')
        ->and($ssl)->toContain('Renewed automatically')
        ->and($ssl)->toContain('Almost every hosting company offers free SSL today.')
        ->and($mail)->toContain('Email Setup')
        ->and($mail)->toContain('Server settings')
        ->and($mail)->toContain('I can receive but not send')
        ->and($mail)->toContain('Thunderbird fetches the settings from the server.');
});

/*
 * A key with no row behind it is printed verbatim by Laravel, so the page would
 * read "client.pages.ssl.included" where the heading belongs. Both locales,
 * because a key can be seeded in one language and forgotten in the other - which
 * is exactly what had happened to 309 client strings before this round.
 */
test('neither guide page leaks a raw translation key', function (string $route) {
    foreach (['en', 'tr'] as $locale) {
        app()->setLocale($locale);

        $body = $this->get(route($route))->assertOk()->getContent();

        expect($body)->not->toMatch('/client\.(pages|hosting)\.[a-z0-9_.]+/i');
    }
})->with(['pages.ssl', 'pages.mail-setup']);

/*
 * The customer's most common mistake, explained with an example address.
 *
 * The example was written "info@{{ $mail['domain'] }}" - and "@{{" is Blade's
 * own escape for printing literal braces, so the sentence that tells a customer
 * to use their FULL address showed them "info{{ $mail['domain'] }}". Twice, in
 * both languages, on the page support links to most. The address now arrives as
 * a :domain placeholder, which has no such second meaning.
 */
test('the mail guide prints a real example address, not Blade source', function () {
    foreach (['en', 'tr'] as $locale) {
        app()->setLocale($locale);

        $body = $this->get(route('pages.mail-setup'))->assertOk()->getContent();
        // Derived the way PageController::mailSetup derives it, so the test
        // does not depend on whether a SystemURL row happens to exist.
        $domain = preg_replace(
            '/^www\./',
            '',
            (string) parse_url((string) App\Models\Setting::get('SystemURL', url('/')), PHP_URL_HOST)
        );

        expect($body)->not->toContain('{{')
            ->and($body)->toContain('info@'.$domain);
    }
});
