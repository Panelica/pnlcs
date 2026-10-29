<?php

use App\Services\AdminSettingsIndex;

/*
 * The general settings page is split into tabs (#332). Two things have to hold
 * or a setting becomes unreachable: every section belongs to a tab that
 * exists, and a link to one section opens the tab holding it - the admin
 * search returns exactly such links (AdminSettingsIndex), and a card in a
 * hidden tab cannot be scrolled to.
 */

function stView(): string
{
    return (string) file_get_contents(resource_path('views/admin/settings/general.blade.php'));
}

it('puts every settings section in a tab that exists', function () {
    $view = stView();
    preg_match_all('/<button type="button" class="settings-tab[^"]*" data-tab="([a-z_]+)"/', $view, $tabs);
    preg_match_all('/<div class="card"([^>]*)id="(settings-[^"]+|auto-charge)"/', $view, $cards, PREG_SET_ORDER);

    expect($tabs[1])->not->toBeEmpty()->and($cards)->not->toBeEmpty();

    $orphans = [];
    foreach ($cards as $card) {
        preg_match('/data-tab="([a-z_]+)"/', $card[1], $m);
        if (! $m || ! in_array($m[1], $tabs[1], true)) {
            $orphans[] = $card[2].': '.($m[1] ?? 'no tab');
        }
    }

    // A card with no tab, or with a tab nobody can click, is a setting the
    // operator can never see again.
    expect($orphans)->toBe([]);
});

it('opens the tab a link points into, for every section the admin search offers', function () {
    $view = stView();

    // The page reads the address and opens the tab holding that section.
    expect($view)->toContain('openFromHash')->toContain('hashchange');

    foreach (app(AdminSettingsIndex::class)->cards() as $card) {
        expect($view)->toContain('id="'.$card['anchor'].'"');
    }
});
