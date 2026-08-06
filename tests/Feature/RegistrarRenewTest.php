<?php

use App\Models\Domain;
use App\Services\DomainService;
use App\Services\Module\ModuleRegistry;
use Illuminate\Support\Facades\Http;

/**
 * Renewing through a registrar has to move the dates by exactly the number of
 * years paid for. Adding the interval to a Carbon instance that is then reused
 * moves it twice; forgetting to write the dates back leaves the domain due, so
 * the generator invoices it again next run.
 */
function domainOn(string $registrar): Domain
{
    return Domain::factory()->create([
        'domain' => strtolower($registrar).'-renew.com',
        'registrar' => $registrar,
        'status' => 'active',
        'registration_period' => 1,
        'expiry_date' => '2027-03-10',
        'next_due_date' => '2027-03-10',
        'recurring_amount' => 15,
    ]);
}

test('every registrar module moves the dates by exactly the years renewed', function () {
    // Each API answers in its own dialect; a single generic fake only
    // satisfies some of them and the rest would be skipped silently.
    Http::fake([
        '*namecheap*' => Http::response('<?xml version="1.0"?><ApiResponse Status="OK"><CommandResponse><DomainRenewResult Renew="true" /></CommandResponse></ApiResponse>', 200),
        '*enom*' => Http::response('<?xml version="1.0"?><interface-response><ErrCount>0</ErrCount></interface-response>', 200),
        '*orderid.json*' => Http::response('["12345678"]', 200),
        '*httpapi.com*' => Http::response(['status' => 'Success'], 200),
        '*' => Http::response('<?xml version="1.0"?><interface-response><ErrCount>0</ErrCount></interface-response>', 200),
    ]);

    $registry = app(ModuleRegistry::class);
    $wrong = [];

    foreach (['Manual', 'Namecheap', 'Enom', 'ResellerClub'] as $name) {
        $module = $registry->getRegistrarModule($name);

        if (! $module) {
            $wrong[] = $name.' → module not found';

            continue;
        }

        $domain = domainOn($name);

        try {
            $result = $module->renew($domain, 2);
        } catch (Throwable $e) {
            $wrong[] = $name.' → threw '.$e->getMessage();

            continue;
        }

        if (! ($result['success'] ?? false)) {
            // Every module here is given a response it should accept, so a
            // failure means the module, not the fixture.
            $wrong[] = $name.' → reported failure: '.($result['message'] ?? '?');

            continue;
        }

        $domain->refresh();
        $expiry = $domain->expiry_date?->toDateString();
        $due = $domain->next_due_date?->toDateString();

        if ($expiry !== '2029-03-10') {
            $wrong[] = $name.' → expiry '.($expiry ?? 'null').', expected 2029-03-10';
        }

        if ($due !== '2029-03-10') {
            $wrong[] = $name.' → next due '.($due ?? 'null').', expected 2029-03-10';
        }
    }

    expect($wrong)->toBe([]);
});

test('a failed registrar renewal leaves the billing dates where they are', function () {
    // This used to move the dates on, so that the generator would not bill the
    // customer again tomorrow. The price of that was a panel showing a domain
    // paid up until next year that the registry had expiring this month.
    // Re-billing is now prevented by the generator itself, which skips a domain
    // whose period is already paid for, so the dates can tell the truth.
    Http::fake(['*' => Http::response('', 500)]);

    $domain = domainOn('Namecheap');
    $before = $domain->expiry_date->toDateString();

    app(DomainService::class)->renewDomain($domain, 1);

    expect($domain->fresh()->expiry_date->toDateString())->toBe($before)
        ->and($domain->fresh()->next_due_date->toDateString())->toBe($before);
});

test('renewing a domain with no registrar set still moves the dates', function () {
    $domain = Domain::factory()->create([
        'domain' => 'no-registrar.com',
        'registrar' => null,
        'status' => 'active',
        'expiry_date' => '2027-03-10',
        'next_due_date' => '2027-03-10',
        'recurring_amount' => 15,
    ]);

    app(DomainService::class)->renewDomain($domain, 1);

    expect($domain->fresh()->expiry_date->toDateString())->toBe('2028-03-10');
});
