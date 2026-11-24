<?php

use App\Models\Client;
use App\Models\Domain;
use App\Models\DomainPricing;
use App\Models\RegistrarSettings;
use App\Models\Setting;
use App\Services\Module\ModuleRegistry;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * pnlcs:domain-sync used to be a stub: it counted domains, printed
 * "(Module integration required)" and changed nothing, so a domain renewed,
 * expired or locked at the registry was never reflected in the panel.
 *
 * Response shapes below follow the vendors' current documentation:
 *  - Namecheap namecheap.domains.getList — Domain element carries Expires,
 *    IsExpired, IsLocked, AutoRenew as attributes (official Go SDK fixture).
 *  - eNom GetDomainExp — <ExpirationDate> as "M/D/YYYY h:mm:ss AM".
 *  - LogicBoxes/ResellerClub domains/details-by-name.json — endtime (unix),
 *    currentstatus, orderstatus[], ns1..ns5.
 */
function syncDomainFor(string $registrar, array $attrs = []): Domain
{
    return Domain::create(array_merge([
        'client_id' => Client::factory()->create()->id,
        'domain' => 'sync-example.com',
        'type' => 'Register',
        'registrar' => $registrar,
        'status' => 'active',
        'registration_period' => 1,
        'registration_date' => '2015-01-01',
        'expiry_date' => '2026-01-01',
        'first_payment_amount' => 10,
        'recurring_amount' => 10,
    ], $attrs));
}

function namecheapListXml(string $domain, string $expires, string $isExpired = 'false', string $isLocked = 'false'): string
{
    return <<<XML
<?xml version="1.0" encoding="utf-8"?>
<ApiResponse Status="OK" xmlns="http://api.namecheap.com/xml.response">
  <Errors />
  <CommandResponse Type="namecheap.domains.getList">
    <DomainGetListResult>
      <Domain ID="1" Name="{$domain}" User="u" Created="06/02/2021" Expires="{$expires}" IsExpired="{$isExpired}" IsLocked="{$isLocked}" AutoRenew="true" WhoisGuard="ENABLED" IsPremium="false" IsOurDNS="true" />
    </DomainGetListResult>
    <Paging><TotalItems>1</TotalItems><CurrentPage>1</CurrentPage><PageSize>100</PageSize></Paging>
  </CommandResponse>
</ApiResponse>
XML;
}

beforeEach(function () {
    RegistrarSettings::updateOrCreate(['registrar' => 'namecheap', 'setting' => 'api_user'], ['value' => 'u']);
    RegistrarSettings::updateOrCreate(['registrar' => 'namecheap', 'setting' => 'api_key'], ['value' => 'k']);
    RegistrarSettings::updateOrCreate(['registrar' => 'namecheap', 'setting' => 'client_ip'], ['value' => '1.2.3.4']);
    RegistrarSettings::updateOrCreate(['registrar' => 'enom', 'setting' => 'uid'], ['value' => 'u']);
    RegistrarSettings::updateOrCreate(['registrar' => 'enom', 'setting' => 'pw'], ['value' => 'p']);
    RegistrarSettings::updateOrCreate(['registrar' => 'resellerclub', 'setting' => 'reseller_id'], ['value' => '1']);
    RegistrarSettings::updateOrCreate(['registrar' => 'resellerclub', 'setting' => 'api_key'], ['value' => 'k']);
});

// ---------------------------------------------------------------------------
// The lookup bug that made every module unreachable
// ---------------------------------------------------------------------------

test('registrar modules resolve regardless of the stored name casing', function () {
    $registry = app(ModuleRegistry::class);

    // The modules write "Namecheap"/"Enom"/"Manual" onto domains while they
    // register as lowercase — an exact lookup returned null for every one.
    expect($registry->getRegistrarModule('Namecheap'))->not->toBeNull()
        ->and($registry->getRegistrarModule('Enom'))->not->toBeNull()
        ->and($registry->getRegistrarModule('ResellerClub'))->not->toBeNull()
        ->and($registry->getRegistrarModule('Manual'))->not->toBeNull()
        ->and($registry->getRegistrarModule('namecheap'))->not->toBeNull()
        ->and($registry->getRegistrarModule('nope'))->toBeNull();
});

// ---------------------------------------------------------------------------
// Namecheap
// ---------------------------------------------------------------------------

test('a namecheap domain gets its expiry pulled from the registry', function () {
    Http::fake(['*namecheap*' => Http::response(namecheapListXml('sync-example.com', '06/02/2027'), 200)]);
    $domain = syncDomainFor('Namecheap');

    $this->artisan('pnlcs:domain-sync')->assertSuccessful();

    expect($domain->fresh()->expiry_date->toDateString())->toBe('2027-06-02');
});

test('a domain expired at the registry is marked expired locally', function () {
    Http::fake(['*namecheap*' => Http::response(namecheapListXml('sync-example.com', '06/02/2025', 'true'), 200)]);
    $domain = syncDomainFor('Namecheap');

    $this->artisan('pnlcs:domain-sync')->assertSuccessful();

    expect($domain->fresh()->status)->toBe('expired');
});

test('a domain missing from the registrar account is reported, not overwritten', function () {
    Http::fake(['*namecheap*' => Http::response(namecheapListXml('someone-else.com', '06/02/2027'), 200)]);
    $domain = syncDomainFor('Namecheap');

    $this->artisan('pnlcs:domain-sync')->assertSuccessful();

    expect($domain->fresh()->expiry_date->toDateString())->toBe('2026-01-01');
});

test('an API error leaves the domain data untouched but records the failed check', function () {
    Http::fake(['*namecheap*' => Http::response('boom', 500)]);
    $domain = syncDomainFor('Namecheap');

    $this->artisan('pnlcs:domain-sync')->assertSuccessful();

    $fresh = $domain->fresh();
    expect($fresh->expiry_date->toDateString())->toBe('2026-01-01')
        ->and($fresh->last_sync_at)->not->toBeNull()
        ->and($fresh->last_sync_status)->toBe('error');
});

// ---------------------------------------------------------------------------
// eNom + ResellerClub
// ---------------------------------------------------------------------------

test('an enom domain gets its expiry pulled from the registry', function () {
    Http::fake(['*' => Http::response('<?xml version="1.0"?><interface-response><ExpirationDate>6/10/2028 3:56:56 PM</ExpirationDate><ErrCount>0</ErrCount><NS1>ns1.test.com</NS1><lockstatus>locked</lockstatus></interface-response>', 200)]);
    $domain = syncDomainFor('Enom');

    $this->artisan('pnlcs:domain-sync')->assertSuccessful();

    expect($domain->fresh()->expiry_date->toDateString())->toBe('2028-06-10');
});

test('a resellerclub domain syncs expiry, status and nameservers', function () {
    Http::fake(['*' => Http::response([
        'endtime' => (string) Carbon::parse('2029-03-04')->timestamp,
        'currentstatus' => 'Active',
        'orderstatus' => ['resellerlock'],
        'ns1' => 'NS1.EXAMPLE.NET',
        'ns2' => 'ns2.example.net',
    ], 200)]);
    $domain = syncDomainFor('ResellerClub');

    $this->artisan('pnlcs:domain-sync')->assertSuccessful();

    $fresh = $domain->fresh();
    expect($fresh->expiry_date->toDateString())->toBe('2029-03-04')
        ->and(json_decode($fresh->nameservers, true))->toBe(['ns1.example.net', 'ns2.example.net']);
});

// ---------------------------------------------------------------------------
// Command behaviour
// ---------------------------------------------------------------------------

test('manual domains are skipped because there is no registry to read', function () {
    Http::fake();
    $domain = syncDomainFor('Manual');

    $this->artisan('pnlcs:domain-sync')->assertSuccessful();

    Http::assertNothingSent();
    expect($domain->fresh()->last_sync_at)->toBeNull()
        ->and($domain->fresh()->last_sync_status)->toBeNull();
});

test('dry run reports without writing', function () {
    Http::fake(['*namecheap*' => Http::response(namecheapListXml('sync-example.com', '06/02/2027'), 200)]);
    $domain = syncDomainFor('Namecheap');

    $this->artisan('pnlcs:domain-sync', ['--dry-run' => true])->assertSuccessful();

    $fresh = $domain->fresh();
    expect($fresh->expiry_date->toDateString())->toBe('2026-01-01')
        ->and($fresh->last_sync_at)->toBeNull()
        ->and($fresh->last_sync_status)->toBeNull();
});

test('the domain filter only touches the requested domain', function () {
    Http::fake(['*namecheap*' => Http::response(namecheapListXml('only-this.com', '06/02/2027'), 200)]);
    $target = syncDomainFor('Namecheap', ['domain' => 'only-this.com']);
    $other = syncDomainFor('Namecheap', ['domain' => 'not-this.com']);

    $this->artisan('pnlcs:domain-sync', ['--domain' => 'only-this.com'])->assertSuccessful();

    expect($target->fresh()->expiry_date->toDateString())->toBe('2027-06-02')
        ->and($other->fresh()->expiry_date->toDateString())->toBe('2026-01-01');
});

test('billing dates are never overwritten by a registry sync', function () {
    Http::fake(['*namecheap*' => Http::response(namecheapListXml('sync-example.com', '06/02/2027'), 200)]);
    $domain = syncDomainFor('Namecheap', ['next_due_date' => '2026-02-15']);

    $this->artisan('pnlcs:domain-sync')->assertSuccessful();

    // Billing owns next_due_date; the registry only informs expiry_date.
    expect($domain->fresh()->next_due_date->toDateString())->toBe('2026-02-15');
});

test('a due date that was following the expiry date follows the registry\'s correction', function () {
    // A .co.uk transferred in on 20 November 2026: the order wrote both dates
    // as that day plus a year. The registry added no time; it expires on
    // 15 June 2027, five months before the invoice would have been raised.
    Http::fake(['*namecheap*' => Http::response(namecheapListXml('sync-example.com', '06/15/2027'), 200)]);
    $domain = syncDomainFor('Namecheap', ['type' => 'Transfer', 'expiry_date' => '2027-11-20', 'next_due_date' => '2027-11-20']);

    $this->artisan('pnlcs:domain-sync')->assertSuccessful();

    expect($domain->fresh()->expiry_date->toDateString())->toBe('2027-06-15')
        ->and($domain->fresh()->next_due_date->toDateString())->toBe('2027-06-15');
});

test('the corrected due date is what the renewal invoice is raised from', function () {
    Http::fake(['*namecheap*' => Http::response(namecheapListXml('sync-example.com', '06/15/2027'), 200)]);
    $domain = syncDomainFor('Namecheap', ['type' => 'Transfer', 'expiry_date' => '2027-11-20', 'next_due_date' => '2027-11-20',
        'payment_method' => 'banktransfer']);
    $this->artisan('pnlcs:domain-sync')->assertSuccessful();

    $this->travelTo(Carbon::parse('2027-06-05 09:00:00'));
    \Illuminate\Support\Facades\Mail::fake();
    app(\App\Services\InvoiceGenerationService::class)->generateDueInvoices();

    expect(\App\Models\InvoiceItem::where('type', 'Domain')->where('rel_id', $domain->id)->exists())->toBeTrue();
});

test('a registry that gives a transfer more time moves the due date later with it', function () {
    Http::fake(['*namecheap*' => Http::response(namecheapListXml('sync-example.com', '06/15/2028'), 200)]);
    $domain = syncDomainFor('Namecheap', ['type' => 'Transfer', 'expiry_date' => '2027-11-20', 'next_due_date' => '2027-11-20']);

    $this->artisan('pnlcs:domain-sync')->assertSuccessful();

    expect($domain->fresh()->next_due_date->toDateString())->toBe('2028-06-15');
});

test('a domain with no due date is not given one by the sync', function () {
    Http::fake(['*namecheap*' => Http::response(namecheapListXml('sync-example.com', '06/02/2027'), 200)]);
    $domain = syncDomainFor('Namecheap');

    $this->artisan('pnlcs:domain-sync')->assertSuccessful();

    expect($domain->fresh()->next_due_date)->toBeNull();
});

test('a dry run reports the due date it would move and writes neither date', function () {
    Http::fake(['*namecheap*' => Http::response(namecheapListXml('sync-example.com', '06/15/2027'), 200)]);
    $domain = syncDomainFor('Namecheap', ['expiry_date' => '2027-11-20', 'next_due_date' => '2027-11-20']);

    $this->artisan('pnlcs:domain-sync', ['--dry-run' => true])
        ->expectsOutputToContain('next_due_date')
        ->assertSuccessful();

    expect($domain->fresh()->next_due_date->toDateString())->toBe('2027-11-20')
        ->and($domain->fresh()->expiry_date->toDateString())->toBe('2027-11-20');
});

test('an unchanged domain records a successful check without rewriting its data', function () {
    // A date still ahead of us: a domain whose expiry has passed is moved along
    // the lifecycle, which is a change and belongs to its own test.
    $ahead = now()->addYear();
    Http::fake(['*namecheap*' => Http::response(namecheapListXml('sync-example.com', $ahead->format('m/d/Y')), 200)]);
    $domain = syncDomainFor('Namecheap', ['expiry_date' => $ahead->toDateString()]);

    $this->artisan('pnlcs:domain-sync')->assertSuccessful();

    $fresh = $domain->fresh();
    expect($fresh->expiry_date->toDateString())->toBe($ahead->toDateString())
        ->and($fresh->last_sync_at)->not->toBeNull()
        ->and($fresh->last_sync_status)->toBe('ok');
});

test('a batch checks only the configured number and the next run continues', function () {
    Setting::set('DomainSyncBatchSize', '2');
    Http::fake(['*namecheap*' => Http::response(namecheapListXml('someone-else.com', now()->addYear()->format('m/d/Y')), 200)]);

    $domains = collect(range(1, 5))->map(fn ($i) => syncDomainFor('Namecheap', [
        'domain' => "batch-{$i}.com",
        'status' => 'pending',
        'expiry_date' => now()->addYear()->toDateString(),
    ]));

    $this->artisan('pnlcs:domain-sync')->assertSuccessful();
    expect(Domain::whereNotNull('last_sync_at')->count())->toBe(2);

    $this->artisan('pnlcs:domain-sync')->assertSuccessful();
    expect(Domain::whereNotNull('last_sync_at')->count())->toBe(4);

    $this->artisan('pnlcs:domain-sync')->assertSuccessful();
    expect(Domain::whereNotNull('last_sync_at')->count())->toBe(5)
        ->and(Domain::where('last_sync_status', 'error')->count())->toBe(5);
});

test('a recently synced domain waits while an older one is checked', function () {
    Setting::set('DomainSyncBatchSize', '10');
    Http::fake(['*namecheap*' => Http::response(namecheapListXml('old-one.com', now()->addYear()->format('m/d/Y')), 200)]);

    $recentAt = now()->subHour()->toDateTimeString();
    $recent = syncDomainFor('Namecheap', ['domain' => 'recent-one.com', 'last_sync_at' => $recentAt]);
    $old = syncDomainFor('Namecheap', ['domain' => 'old-one.com', 'last_sync_at' => now()->subDays(2)]);

    $this->artisan('pnlcs:domain-sync')->assertSuccessful();

    expect($recent->fresh()->last_sync_at->toDateTimeString())->toBe($recentAt)
        ->and($old->fresh()->last_sync_at->greaterThan(now()->subDay()))->toBeTrue();
    Http::assertSentCount(1);
});

test('the domain option ignores the batch and the due-date filter', function () {
    Setting::set('DomainSyncBatchSize', '1');
    Http::fake(['*namecheap*' => Http::response(namecheapListXml('only-one.com', now()->addYear()->format('m/d/Y')), 200)]);

    $target = syncDomainFor('Namecheap', ['domain' => 'only-one.com', 'last_sync_at' => now()]);
    $other = syncDomainFor('Namecheap', ['domain' => 'other-one.com']);

    $this->artisan('pnlcs:domain-sync', ['--domain' => 'only-one.com'])->assertSuccessful();

    expect($target->fresh()->last_sync_at)->not->toBeNull()
        ->and($other->fresh()->last_sync_at)->toBeNull();
});

// ---------------------------------------------------------------------------
// Missing registration date filled from RDAP
// ---------------------------------------------------------------------------

function rdapResponse(string $registrationDate): array
{
    return [
        'objectClassName' => 'domain',
        'ldhName' => 'sync-example.com',
        'events' => [
            ['eventAction' => 'registration', 'eventDate' => $registrationDate.'T04:00:00Z'],
        ],
    ];
}

test('a domain without a registration date gets it filled from RDAP', function () {
    Http::fake([
        '*namecheap*' => Http::response(namecheapListXml('sync-example.com', '06/02/2027'), 200),
        'rdap.org/*' => Http::response(rdapResponse('1995-08-14'), 200),
    ]);
    $domain = syncDomainFor('Namecheap', ['registration_date' => null]);

    $this->artisan('pnlcs:domain-sync')->assertSuccessful();

    expect($domain->fresh()->registration_date->toDateString())->toBe('1995-08-14');
});

test('a domain with a known registration date is never asked of RDAP', function () {
    Http::fake(['*namecheap*' => Http::response(namecheapListXml('sync-example.com', '06/02/2027'), 200)]);
    $domain = syncDomainFor('Namecheap');

    $this->artisan('pnlcs:domain-sync')->assertSuccessful();

    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'rdap.org'));
    expect($domain->fresh()->registration_date->toDateString())->toBe('2015-01-01');
});

test('dry run reports the RDAP fill without writing it', function () {
    Http::fake([
        '*namecheap*' => Http::response(namecheapListXml('sync-example.com', '06/02/2027'), 200),
        'rdap.org/*' => Http::response(rdapResponse('1995-08-14'), 200),
    ]);
    $domain = syncDomainFor('Namecheap', ['registration_date' => null]);

    $this->artisan('pnlcs:domain-sync', ['--dry-run' => true])->assertSuccessful();

    expect($domain->fresh()->registration_date)->toBeNull();
});

test('an RDAP miss leaves the registration date null without stopping the sync', function () {
    Http::fake([
        '*namecheap*' => Http::response(namecheapListXml('sync-example.com', '06/02/2027'), 200),
        'rdap.org/*' => Http::response(['errorCode' => 404, 'title' => 'Not Found'], 404),
    ]);
    $domain = syncDomainFor('Namecheap', ['registration_date' => null]);

    $this->artisan('pnlcs:domain-sync')->assertSuccessful();

    expect($domain->fresh()->registration_date)->toBeNull()
        ->and($domain->fresh()->expiry_date->toDateString())->toBe('2027-06-02');
});

test('the per-run RDAP limit is read from the setting, and zero disables it', function () {
    Setting::set('DomainSyncRdapMaxPerRun', '0', 'general');
    Http::fake(['*namecheap*' => Http::response(namecheapListXml('sync-example.com', '06/02/2027'), 200)]);
    $domain = syncDomainFor('Namecheap', ['registration_date' => null]);

    $this->artisan('pnlcs:domain-sync')->assertSuccessful();

    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'rdap.org'));
    expect($domain->fresh()->registration_date)->toBeNull();
});

/*
 * Moving a lapsed domain along is a local decision - it reads the TLD's grace
 * and redemption periods and writes a status - so it must not be limited by
 * the batch that paces the registrar calls. It was: the batch holds only
 * registrars that can sync, so a manual domain stopped leaving "active"
 * altogether, and a domain past the batch waited for its turn to lapse.
 */
test('a lapsed domain is moved along even when the batch does not reach it', function () {
    Setting::set('DomainSyncBatchSize', '1');
    Http::fake(['*namecheap*' => Http::response(namecheapListXml('in-batch.com', now()->addYear()->format('m/d/Y')), 200)]);

    DomainPricing::updateOrCreate(
        ['extension' => '.com'],
        ['register_price' => 10, 'renew_price' => 12, 'transfer_price' => 10, 'min_years' => 1,
            'max_years' => 5, 'enabled' => true, 'sort_order' => 0,
            'grace_period' => 30, 'redemption_grace_period' => 30]
    );

    // Fills the single batch slot.
    syncDomainFor('Namecheap', ['domain' => 'in-batch.com', 'expiry_date' => now()->addYear()]);

    // Not in the batch: it is manual, and the batch is full anyway.
    $manual = syncDomainFor('Manual', [
        'domain' => 'lapsed-manual.com',
        'status' => 'active',
        'expiry_date' => now()->subDays(5),
        'next_due_date' => now()->subDays(5),
    ]);

    $this->artisan('pnlcs:domain-sync')->assertSuccessful();

    expect($manual->fresh()->status)->toBe('grace')
        ->and($manual->fresh()->last_sync_at)->toBeNull();
});
