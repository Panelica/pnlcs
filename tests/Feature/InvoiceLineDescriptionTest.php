<?php

use App\Models\Client;
use App\Models\Domain;
use App\Models\ProductAddon;
use App\Models\Service;
use App\Models\ServiceAddon;
use App\Models\Setting;
use App\Services\AddonService;
use App\Services\InvoiceLineDescription;
use Carbon\Carbon;

/*
 * One setting controls what every recurring invoice line says about its
 * period: the billing cycle, the date range, or both.
 */

function lineDescriptionClient(string $language = 'pl'): Client
{
    return Client::factory()->create(['language' => $language]);
}

function lineDescriptionService(Client $client): Service
{
    return Service::factory()->create([
        'client_id' => $client->id,
        'domain' => 'cncorp.pl',
        'billing_cycle' => 'Monthly',
    ]);
}

it('uses the selected cycle and period parts', function (string $mode, string $expected) {
    Setting::set('InvoiceLinePeriodDisplay', $mode);

    $line = app(InvoiceLineDescription::class)->service(
        'Obsługa i utrzymanie serwerów pocztowego',
        'cncorp.pl',
        'Monthly',
        Carbon::parse('2026-12-01'),
        Carbon::parse('2027-01-01'),
        'pl'
    );

    expect($line)->toBe($expected);
})->with([
    'cycle' => ['cycle', 'Obsługa i utrzymanie serwerów pocztowego (Miesięcznie) — cncorp.pl'],
    'period' => ['period', 'Obsługa i utrzymanie serwerów pocztowego — cncorp.pl — 2026/12/01 - 2026/12/31'],
    'both' => ['cycle_and_period', 'Obsługa i utrzymanie serwerów pocztowego (Miesięcznie) — cncorp.pl — 2026/12/01 - 2026/12/31'],
]);

it('defaults to cycle and period when the setting is missing or invalid', function (string $setting) {
    Setting::set('InvoiceLinePeriodDisplay', $setting);

    expect(app(InvoiceLineDescription::class)->service(
        'Hosting', 'example.test', 'Monthly',
        Carbon::parse('2026-12-01'), Carbon::parse('2027-01-01'), 'en'
    ))->toBe('Hosting (Monthly) — example.test — 2026/12/01 - 2026/12/31');
})->with(['missing' => [''], 'invalid' => ['nonsense']]);

it('localises the domain cycle and applies the same three modes', function (string $mode, string $expected) {
    Setting::set('InvoiceLinePeriodDisplay', $mode);
    $client = lineDescriptionClient();
    $domain = Domain::factory()->create([
        'client_id' => $client->id,
        'domain' => 'cncorp.pl',
        'registration_period' => 1,
        'next_due_date' => '2026-12-01',
        'expiry_date' => '2027-12-01',
    ]);

    expect(app(InvoiceLineDescription::class)->domainRenewal($domain))->toBe($expected);
})->with([
    'cycle' => ['cycle', 'Odnowienie domeny: cncorp.pl (1 rok)'],
    'period' => ['period', 'Odnowienie domeny: cncorp.pl — 2026/12/01 - 2027/11/30'],
    'both' => ['cycle_and_period', 'Odnowienie domeny: cncorp.pl (1 rok) — 2026/12/01 - 2027/11/30'],
]);

it('describes a first domain registration in the same mode', function () {
    Setting::set('InvoiceLinePeriodDisplay', 'cycle_and_period');
    $client = lineDescriptionClient();
    $domain = Domain::factory()->create([
        'client_id' => $client->id,
        'domain' => 'cncorp.pl',
        'registration_period' => 1,
        'registration_date' => '2026-01-01',
        'expiry_date' => '2027-01-01',
    ]);

    expect(app(InvoiceLineDescription::class)->domainRegistration($domain, 'register'))
        ->toBe('Rejestracja domeny: cncorp.pl (1 rok) — 2026/01/01 - 2026/12/31');
});

it('uses the setting on add-on invoice lines', function () {
    Setting::set('InvoiceLinePeriodDisplay', 'cycle_and_period');
    $client = lineDescriptionClient();
    $service = lineDescriptionService($client);
    $productAddon = ProductAddon::create([
        'name' => 'SEO IP',
        'packages' => null,
        'hidden' => false,
        'retired' => false,
        'sort_order' => 0,
        'tax' => true,
    ]);
    $addon = ServiceAddon::create([
        'service_id' => $service->id,
        'client_id' => $client->id,
        'addon_id' => $productAddon->id,
        'qty' => 1,
        'amount' => 10,
        'billing_cycle' => 'Monthly',
        'next_due_date' => '2026-12-01',
        'status' => 'active',
    ]);

    $line = app(AddonService::class)->lineItem($addon);

    expect($line['description'])
        ->toBe('Addon: SEO IP (Miesięcznie) — cncorp.pl — 2026/12/01 - 2026/12/31');
});

/*
 * The period is printed inclusively (#491), which on a row whose start and
 * end fall on the same day would read backwards: "2026/12/01 - 2026/11/30".
 * An add-on from before first_invoice_date existed can be exactly that.
 */
it('does not print a period that runs backwards', function () {
    Setting::set(InvoiceLineDescription::SETTING, InvoiceLineDescription::PERIOD);
    $same = Carbon::parse('2026-12-01');

    expect(app(InvoiceLineDescription::class)->service('Hosting', null, 'Monthly', $same, $same->copy(), 'en'))
        ->toBe('Hosting — 2026/12/01 - 2026/12/01');
});
