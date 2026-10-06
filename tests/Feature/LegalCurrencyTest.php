<?php

use App\Models\Currency;
use App\Models\Setting;
use App\Support\CustomerCurrency;

/*
 * The terms, the distance sales contract, the preliminary information form and
 * the refund policy said "prices shown in US dollars, invoiced in Turkish lira
 * at the TCMB rate" on every installation, whatever it sold in. A shop selling
 * in euros and invoicing in euros published a contract saying otherwise.
 */

function lcShop(string $code): void
{
    Currency::query()->update(['is_default' => false]);
    Currency::updateOrCreate(['code' => $code], ['prefix' => '', 'suffix' => '', 'format' => 1, 'rate' => 1, 'is_default' => true]);
    app()->forgetInstance('pnlcs.currency');
    Setting::set('Country', 'TR', 'general');
}

function lcPage(string $doc, string $lang): string
{
    app()->setLocale($lang);

    return test()->get(route('legal.show', $doc).'?lang='.$lang)->assertOk()->getContent();
}

it('names the shop currency, and says invoices are in it, when no billing currency is set', function () {
    lcShop('EUR');
    Setting::set('BillingCurrency', '', 'general');

    foreach (['terms', 'distance-sales', 'pre-information', 'refund'] as $doc) {
        expect(lcPage($doc, 'tr'))->not->toContain('ABD doları')->not->toContain('Türk lirası')->not->toContain('TCMB')->not->toContain('Merkez Bankası');
    }
    expect(lcPage('terms', 'tr'))->toContain('euro (EUR)')
        ->and(lcPage('terms', 'en'))->toContain('euros (EUR)')->not->toContain('US dollars')->not->toContain('Turkish lira');
});

it('keeps the TCMB wording for a shop that bills in lira at the Central Bank rate', function () {
    lcShop('USD');
    Currency::updateOrCreate(['code' => 'TRY'], ['prefix' => '', 'suffix' => ' TL', 'format' => 1, 'rate' => 41, 'is_default' => false]);
    Setting::set('BillingCurrency', 'TRY', 'general');
    Setting::set('OfficialRateProvider', 'tcmb', 'general');

    expect(lcPage('terms', 'tr'))->toContain('ABD doları (USD)')->toContain('Merkez Bankası')->toContain('Türk lirası (TRY)')
        ->and(lcPage('pre-information', 'tr'))->toContain('TCMB bülten')
        ->and(lcPage('refund', 'en'))->toContain('Turkish lira (TRY) amount shown on your invoice');
});

it('says the day\'s rate, not the Central Bank\'s, for another billing currency', function () {
    lcShop('EUR');
    Currency::updateOrCreate(['code' => 'GBP'], ['prefix' => '£', 'suffix' => '', 'format' => 1, 'rate' => 0.85, 'is_default' => false]);
    Setting::set('BillingCurrency', 'GBP', 'general');
    Setting::set('OfficialRateProvider', '', 'general');

    expect(lcPage('terms', 'en'))->toContain('euros (EUR)')->toContain('pounds sterling (GBP)')->not->toContain('Central Bank');
});

it('says an account in another currency is invoiced in it, when customers may choose', function () {
    lcShop('USD');
    Currency::updateOrCreate(['code' => 'EUR'], ['prefix' => '€', 'suffix' => '', 'format' => 1, 'rate' => 0.9, 'is_default' => false]);
    Setting::set('BillingCurrency', '', 'general');
    Setting::set(CustomerCurrency::SETTING, '1', 'general');

    expect(lcPage('terms', 'en'))->toContain('If your account is set to one of them, your invoices are issued in that currency')
        ->and(lcPage('terms', 'tr'))->toContain('Hesabınız bunlardan birinde ise')
        ->and(lcPage('distance-sales', 'tr'))->toContain('Hesabınız bunlardan birinde ise')
        ->and(lcPage('pre-information', 'tr'))->toContain('Diğer para birimleri');

    Setting::set(CustomerCurrency::SETTING, '0', 'general');
    expect(lcPage('terms', 'en'))->not->toContain('If your account is set to one of them');
});
