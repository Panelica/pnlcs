<?php

use App\Models\Client;
use App\Models\Domain;
use App\Models\RegistrarSettings;
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

test('an API error leaves the domain untouched', function () {
    Http::fake(['*namecheap*' => Http::response('boom', 500)]);
    $domain = syncDomainFor('Namecheap');

    $this->artisan('pnlcs:domain-sync')->assertSuccessful();

    expect($domain->fresh()->expiry_date->toDateString())->toBe('2026-01-01');
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
    syncDomainFor('Manual');

    $this->artisan('pnlcs:domain-sync')->assertSuccessful();

    Http::assertNothingSent();
});

test('dry run reports without writing', function () {
    Http::fake(['*namecheap*' => Http::response(namecheapListXml('sync-example.com', '06/02/2027'), 200)]);
    $domain = syncDomainFor('Namecheap');

    $this->artisan('pnlcs:domain-sync', ['--dry-run' => true])->assertSuccessful();

    expect($domain->fresh()->expiry_date->toDateString())->toBe('2026-01-01');
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

test('an unchanged domain is not rewritten', function () {
    Http::fake(['*namecheap*' => Http::response(namecheapListXml('sync-example.com', '01/01/2026'), 200)]);
    $domain = syncDomainFor('Namecheap');
    $before = $domain->updated_at;

    $this->travel(2)->seconds();
    $this->artisan('pnlcs:domain-sync')->assertSuccessful();

    expect($domain->fresh()->updated_at->eq($before))->toBeTrue();
});
