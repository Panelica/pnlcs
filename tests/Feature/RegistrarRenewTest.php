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

test('a failed registrar renewal still moves the billing dates on', function () {
    // The customer has paid. If the registrar API is down, the dates cannot be
    // left where they are or the generator bills them again tomorrow.
    Http::fake(['*' => Http::response('', 500)]);

    $domain = domainOn('Namecheap');

    app(DomainService::class)->renewDomain($domain, 1);

    expect($domain->fresh()->expiry_date->toDateString())->toBe('2028-03-10')
        ->and($domain->fresh()->next_due_date->toDateString())->toBe('2028-03-10');
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
