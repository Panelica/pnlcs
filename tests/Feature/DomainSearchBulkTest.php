<?php

use App\Models\DomainPricing;
use App\Models\RegistrarSettings;
use App\Models\Setting;
use App\Services\DomainAvailability;
use App\Services\WhoisLookup;
use Illuminate\Support\Facades\Http;
use Modules\Registrars\DomainNameApi\DomainNameApiRegistrar;

/**
 * The domain search asks the registry about every name in one request.
 *
 * It asked about the name typed and each suggested ending one after another,
 * through a one-name bulk-search each time. Against the live DomainNameAPI
 * that is about 3 seconds a name - a search with six suggestions kept the
 * customer waiting about 21 seconds. The same seven names in one bulk-search
 * came back in about 3.5 seconds, and a single name through domains/search in
 * about 0.7 (measured 2026-10-01).
 */
function bulkDnaSettings(): void
{
    RegistrarSettings::updateOrCreate(['registrar' => 'domainnameapi', 'setting' => 'reseller_id'], ['value' => 'reseller-1']);
    RegistrarSettings::updateOrCreate(['registrar' => 'domainnameapi', 'setting' => 'api_key'], ['value' => 'key-1']);
}

function bulkPricing(array $extensions): void
{
    foreach ($extensions as $i => $extension) {
        DomainPricing::updateOrCreate(['extension' => $extension], [
            'register_price' => 12.99, 'transfer_price' => 12.99, 'renew_price' => 14.99,
            'min_years' => 1, 'max_years' => 10, 'sort_order' => $i, 'enabled' => true,
        ]);
    }
}

function bulkWhois(bool $checked, bool $available = false): void
{
    app()->instance(WhoisLookup::class, new class($checked, $available) extends WhoisLookup
    {
        public array $asked = [];

        public function __construct(private bool $checkedResult, private bool $availableResult) {}

        public function check(string $domain, ?string $server): array
        {
            $this->asked[] = $domain;

            return ['available' => $this->availableResult, 'checked' => $this->checkedResult, 'response' => ''];
        }
    });
}

function bulkInfo(string $domain, string $status): array
{
    return ['domainName' => $domain, 'status' => $status, 'price' => 11.31, 'currency' => 'USD'];
}

it('asks DomainNameAPI about the name and its suggestions in one request', function () {
    bulkDnaSettings();
    bulkPricing(['.com', '.net', '.org']);
    bulkWhois(checked: false);
    Http::fake(['*domains/bulk-search*' => Http::response(['success' => true, 'infos' => [
        bulkInfo('bulk-example.com', 'AVAILABLE'),
        bulkInfo('bulk-example.net', 'NOTAVAILABLE'),
        bulkInfo('bulk-example.org', 'AVAILABLE'),
    ]])]);

    $results = $this->post(route('client.domain.check'), ['domain' => 'bulk-example.com'])->assertOk()->json();

    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => str_contains($request->url(), 'domains/bulk-search')
        && collect($request->data())->pluck('domainName')->sort()->values()->all()
            === ['bulk-example.com', 'bulk-example.net', 'bulk-example.org']);

    expect($results['primary'])->toMatchArray(['domain' => 'bulk-example.com', 'available' => true, 'checked' => true])
        ->and(collect($results['alternatives'])->pluck('available', 'domain')->all())
        ->toBe(['bulk-example.net' => false, 'bulk-example.org' => true]);
    expect(app(WhoisLookup::class)->asked)->toBe([]);
});

it('asks WHOIS only about a name the registry left out of its answer', function () {
    bulkDnaSettings();
    bulkPricing(['.com', '.net']);
    bulkWhois(checked: false);
    Http::fake(['*domains/bulk-search*' => Http::response(['success' => true, 'infos' => [
        bulkInfo('partial-example.com', 'AVAILABLE'),
    ]])]);

    $results = $this->post(route('client.domain.check'), ['domain' => 'partial-example.com'])->assertOk()->json();

    expect($results['primary']['checked'])->toBeTrue()
        ->and($results['alternatives'][0])->toMatchArray(['domain' => 'partial-example.net', 'available' => false, 'checked' => false])
        ->and(app(WhoisLookup::class)->asked)->toBe(['partial-example.net']);
});

it('does not report names as available when the bulk request fails', function () {
    bulkDnaSettings();
    bulkPricing(['.com', '.net']);
    bulkWhois(checked: false);
    Http::fake(['*domains/bulk-search*' => Http::response('Too Many Requests', 429)]);

    $results = $this->post(route('client.domain.check'), ['domain' => 'failed-example.com'])->assertOk()->json();

    expect($results['primary'])->toMatchArray(['available' => false, 'checked' => false])
        ->and($results['alternatives'][0])->toMatchArray(['available' => false, 'checked' => false]);
});

it('still asks one name at a time when the registrar cannot take a list', function () {
    Setting::set('default_registrar', 'manual', 'general');
    bulkPricing(['.com', '.net']);
    bulkWhois(checked: true, available: true);
    Http::fake();

    $results = app(DomainAvailability::class)->checkMany(['one-example.com', 'one-example.net']);

    Http::assertNothingSent();
    expect(array_keys($results))->toBe(['one-example.com', 'one-example.net'])
        ->and($results['one-example.com'])->toMatchArray(['available' => true, 'checked' => true]);
});

it('checks a single name through domains/search', function () {
    bulkDnaSettings();
    Http::fake(['*domains/search*' => Http::response(['success' => true, 'info' => bulkInfo('single-example.com', 'AVAILABLE')])]);

    $result = (new DomainNameApiRegistrar)->checkAvailability('single-example.com');

    expect($result['available'])->toBeTrue()->and($result)->not->toHaveKey('error');
    Http::assertSent(fn ($request) => str_ends_with($request->url(), 'domains/search')
        && $request['domainName'] === 'single-example.com');
});

it('reports a single name it got no answer for as an error, not as taken', function () {
    bulkDnaSettings();
    Http::fake(['*domains/search*' => Http::response('Too Many Requests', 429)]);

    expect((new DomainNameApiRegistrar)->checkAvailability('noanswer-example.com'))->toHaveKey('error');
});
