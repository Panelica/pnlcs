<?php

use App\Models\DomainPricing;
use App\Models\Setting;
use App\Services\WhoisLookup;

/*
 * The endings the domain search suggests are the operator's to choose.
 *
 * They were a fixed list in the controller (.com .net .org .io .co .dev .app
 * .online .site .xyz, six at most), so a seller of .com.tr, .de or .eu could
 * never have them suggested. The list and its length are now settings; empty
 * keeps the old list, so nothing changes until an operator says otherwise.
 */

function dssPricing(array $extensions): void
{
    foreach ($extensions as $i => $extension) {
        DomainPricing::updateOrCreate(['extension' => $extension], [
            'register_price' => 9.99, 'transfer_price' => 9.99, 'renew_price' => 9.99,
            'min_years' => 1, 'max_years' => 10, 'sort_order' => $i, 'enabled' => true,
        ]);
    }

    // No registrar to ask: WHOIS answers "free" for every name.
    Setting::set('default_registrar', 'manual');
    app()->instance(WhoisLookup::class, new class extends WhoisLookup
    {
        public function check(string $domain, ?string $server): array
        {
            return ['available' => true, 'checked' => true, 'response' => ''];
        }
    });
}

function dssSuggested(): array
{
    $response = test()->get(route('client.domain.search', ['domain' => 'marka', 'tld' => '.com']))->assertOk();

    return array_map(fn ($r) => $r['tld'], $response->viewData('results')['alternatives']);
}

it('keeps the built-in suggestions while nothing is set', function () {
    dssPricing(['.com', '.net', '.org', '.io', '.dev', '.app', '.com.tr', '.xyz']);

    expect(dssSuggested())->toBe(['.net', '.org', '.io', '.dev', '.app', '.xyz']);
});

it('suggests the operator\'s endings in their order', function () {
    dssPricing(['.com', '.net', '.org', '.com.tr', '.de', '.eu']);
    Setting::set('DomainSuggestionTlds', 'com.tr, .de .eu .net');

    expect(dssSuggested())->toBe(['.com.tr', '.de', '.eu', '.net']);
});

it('skips an ending that is not sold and the one searched for', function () {
    dssPricing(['.com', '.net', '.com.tr']);
    Setting::set('DomainSuggestionTlds', '.com .com.tr .fr .net');

    expect(dssSuggested())->toBe(['.com.tr', '.net']);
});

it('shows as many suggestions as the operator asks for', function () {
    dssPricing(['.com', '.net', '.org', '.io', '.dev']);
    Setting::set('DomainSuggestionTlds', '.net .org .io .dev');
    Setting::set('DomainSuggestionCount', '2');

    expect(dssSuggested())->toBe(['.net', '.org']);
});
