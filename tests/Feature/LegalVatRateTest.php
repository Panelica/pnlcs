<?php

use App\Models\Setting;
use App\Models\TaxRule;

/*
 * The terms, the distance sales contract and the preliminary information form
 * promised 20% VAT to every customer resident in Türkiye, whatever the shop
 * charged: a seller outside Türkiye that charges no VAT published a contract
 * saying otherwise, and one charging another rate published the wrong one.
 */

function lvPages(): array
{
    Setting::set('Country', 'TR', 'general');
    app()->setLocale('tr');

    return [
        test()->get(route('legal.show', 'terms').'?lang=tr')->assertOk()->getContent(),
        test()->get(route('legal.show', 'distance-sales').'?lang=tr')->assertOk()->getContent(),
        test()->get(route('legal.show', 'pre-information').'?lang=tr')->assertOk()->getContent(),
    ];
}

it('states no VAT rate when the shop charges none', function () {
    TaxRule::query()->delete();

    foreach (lvPages() as $html) {
        expect($html)->not->toContain('%20')->not->toContain('KDV eklenir')->not->toContain('katma değer vergisi eklenir');
    }
});

it('states the rate the tax rule for Türkiye charges', function () {
    TaxRule::query()->delete();
    TaxRule::create(['name' => 'KDV', 'state' => '', 'country' => 'TR', 'tax_rate' => 18, 'is_default' => false]);

    foreach (lvPages() as $html) {
        expect($html)->toContain('%18')->not->toContain('%20');
    }
});

it('falls back to the default rule', function () {
    TaxRule::query()->delete();
    TaxRule::create(['name' => 'VAT', 'state' => '', 'country' => '', 'tax_rate' => 20, 'is_default' => true]);

    expect(lvPages()[0])->toContain('%20 KDV');
});
