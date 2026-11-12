<?php

use App\Models\DomainPricing;
use App\Models\Setting;

/*
 * The TLDs handed to the front page.
 *
 * Their order is the operator's list from Admin > Settings > Domains, not the
 * catalogue's sort_order: most of the catalogue shares sort_order 0, so the
 * old query returned a different set on every request.
 */

function makeFrontPageTld(string $extension, bool $enabled = true, int $sortOrder = 0): DomainPricing
{
    return DomainPricing::create([
        'extension' => $extension,
        'register_price' => 10,
        'renew_price' => 10,
        'transfer_price' => 10,
        'restore_price' => 10,
        'sort_order' => $sortOrder,
        'enabled' => $enabled,
    ]);
}

/** @return array<int, string> */
function frontPageTlds($response): array
{
    return collect($response->viewData('domainPricing'))
        ->pluck('extension')
        ->values()
        ->all();
}

it('shows the front-page TLDs in the operator order', function () {
    makeFrontPageTld('.zz-home-alpha');
    makeFrontPageTld('.zz-home-beta');
    makeFrontPageTld('.zz-home-gamma');

    Setting::set('DomainSuggestionTlds', '.zz-home-gamma .zz-home-alpha .zz-home-beta', 'general');

    $response = $this->get('/')->assertOk();

    expect(array_slice(frontPageTlds($response), 0, 3))
        ->toBe(['.zz-home-gamma', '.zz-home-alpha', '.zz-home-beta']);
});

it('skips disabled endings from the operator list', function () {
    makeFrontPageTld('.zz-home-alpha');
    makeFrontPageTld('.zz-home-beta', enabled: false);
    makeFrontPageTld('.zz-home-gamma');

    Setting::set('DomainSuggestionTlds', '.zz-home-alpha .zz-home-beta .zz-home-gamma', 'general');

    $response = $this->get('/')->assertOk();

    expect(array_slice(frontPageTlds($response), 0, 2))
        ->toBe(['.zz-home-alpha', '.zz-home-gamma']);
});
