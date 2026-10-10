<?php

/*
 * A link written as a plain path ("/client/store") leaves the language in the
 * address behind: with LocaleUrls on, a visitor reading Turkish who follows it
 * lands on the default language, or is redirected only if they chose Turkish
 * before. url() and route() put the page's prefix in front. Assets, the admin
 * area, the API and gateway callbacks are outside the language addresses and
 * may stay plain.
 */

function plainLinkViewFiles(): array
{
    $files = [];
    foreach ([resource_path('views'), base_path('themes')] as $root) {
        if (! is_dir($root)) {
            continue;
        }
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            $path = $file->getPathname();
            if (str_ends_with($path, '.blade.php') && ! preg_match('#/views/(admin|emails|install|vendor)/#', $path)) {
                $files[] = $path;
            }
        }
    }

    return $files;
}

test('no page links to a plain path that would drop the page\'s language', function () {
    $found = [];
    foreach (plainLinkViewFiles() as $file) {
        preg_match_all('#\b(?:href|action|data-href)="(/(?!/)[^"]*)"#', (string) file_get_contents($file), $m);
        foreach ($m[1] as $link) {
            if (! preg_match('#^/(css|js|img|images|build|storage|themes|vendor|favicon|branding|fonts|gateway|admin|api)\b#', $link)) {
                $found[] = str_replace(base_path().'/', '', $file).': '.$link;
            }
        }
    }

    expect($found)->toBe([], "Write these with url() or route(), so they carry the page's language:\n".implode("\n", $found));
});

test('a link on a page in another language keeps its prefix', function () {
    \App\Models\Language::updateOrCreate(['code' => 'tr'], ['name' => 'Turkish', 'native_name' => 'Türkçe', 'flag_code' => 'tr', 'direction' => 'ltr', 'is_active' => true, 'is_default' => false, 'sort_order' => 2]);
    \App\Models\Setting::set('DefaultLanguage', 'en', 'language');
    \App\Models\Setting::set(\App\Support\LocaleUrl::SETTING, '1', 'language');
    \App\Support\LocaleUrl::forget();
    $this->withoutMiddleware(\App\Http\Middleware\RedirectToInstaller::class);

    $html = $this->get('/tr')->assertOk()->getContent();

    expect($html)->toContain('/tr/client/domain-search"')->and($html)->not->toContain('href="/client/domain-search"');
});
