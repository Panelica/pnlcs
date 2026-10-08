<?php

use App\Models\DomainPricing;
use App\Models\RegistrarSettings;
use App\Models\Setting;
use App\Services\DomainAvailability;
use App\Services\Module\ModuleRegistry;
use App\Services\WhoisLookup;
use Illuminate\Support\Facades\Http;
use Modules\Registrars\OpenProvider\OpenProviderRegistrar;

/**
 * A TLD that names its own registrar is asked about through that registrar,
 * not the default one - a private or registry-specific TLD can only be
 * answered by its own EPP registrar. The default registrar still gets every
 * other name in one bulk request.
 */
class PerTldFakeRegistrar extends OpenProviderRegistrar
{
    public static array $asked = [];

    public function __construct() {}

    public function checkAvailability(string $domain): array
    {
        self::$asked[] = $domain;

        return ['available' => str_starts_with($domain, 'free'), 'domain' => $domain];
    }
}

function perTldSetup(array $pricing): void
{
    PerTldFakeRegistrar::$asked = [];
    app(ModuleRegistry::class)->registerRegistrar('fakeepp', PerTldFakeRegistrar::class);
    Setting::set('default_registrar', 'domainnameapi', 'general');
    RegistrarSettings::updateOrCreate(['registrar' => 'domainnameapi', 'setting' => 'reseller_id'], ['value' => 'reseller-1']);
    RegistrarSettings::updateOrCreate(['registrar' => 'domainnameapi', 'setting' => 'api_key'], ['value' => 'key-1']);
    foreach ($pricing as $i => [$extension, $registrar]) {
        DomainPricing::updateOrCreate(['extension' => $extension], [
            'register_price' => 9.99, 'transfer_price' => 9.99, 'renew_price' => 9.99,
            'min_years' => 1, 'max_years' => 10, 'sort_order' => $i, 'enabled' => true,
            'auto_registrar' => $registrar,
        ]);
    }
    app()->instance(WhoisLookup::class, new class extends WhoisLookup
    {
        public array $asked = [];

        public function __construct() {}

        public function check(string $domain, ?string $server): array
        {
            $this->asked[] = $domain;

            return ['available' => false, 'checked' => false, 'response' => ''];
        }
    });
}

function perTldBulkAnswer(array $statuses): void
{
    Http::fake(['*domains/bulk-search*' => Http::response(['success' => true, 'infos' => collect($statuses)
        ->map(fn ($status, $domain) => ['domainName' => $domain, 'status' => $status, 'price' => 1, 'currency' => 'USD'])
        ->values()->all()])]);
}

it('asks a TLD with its own registrar through that registrar, and the rest in one bulk request', function () {
    perTldSetup([['.com', null], ['.test', 'fakeepp']]);
    perTldBulkAnswer(['free-name.com' => 'AVAILABLE']);

    $results = app(DomainAvailability::class)->checkMany(['free-name.com', 'free-name.test']);

    Http::assertSentCount(1);
    Http::assertSent(fn ($r) => collect($r->data())->pluck('domainName')->all() === ['free-name.com']);
    expect(PerTldFakeRegistrar::$asked)->toBe(['free-name.test'])
        ->and($results['free-name.com'])->toMatchArray(['available' => true, 'checked' => true])
        ->and($results['free-name.test'])->toMatchArray(['available' => true, 'checked' => true])
        ->and(app(WhoisLookup::class)->asked)->toBe([]);
});

it('routes by the longest listed suffix, so .com.tr and .tr can use different registrars', function () {
    perTldSetup([['.tr', 'fakeepp'], ['.com.tr', null]]);
    perTldBulkAnswer(['taken.com.tr' => 'NOTAVAILABLE']);

    $results = app(DomainAvailability::class)->checkMany(['taken.com.tr', 'taken.tr']);

    expect(PerTldFakeRegistrar::$asked)->toBe(['taken.tr'])
        ->and($results['taken.com.tr'])->toMatchArray(['available' => false, 'checked' => true])
        ->and($results['taken.tr'])->toMatchArray(['available' => false, 'checked' => true]);
});

it('uses the default registrar for a TLD set to manual or to nothing', function () {
    perTldSetup([['.com', 'Manual'], ['.net', '']]);
    perTldBulkAnswer(['free-a.com' => 'AVAILABLE', 'free-a.net' => 'AVAILABLE']);

    $results = app(DomainAvailability::class)->checkMany(['free-a.com', 'free-a.net']);

    Http::assertSentCount(1);
    expect(PerTldFakeRegistrar::$asked)->toBe([])
        ->and($results['free-a.com']['checked'])->toBeTrue()
        ->and($results['free-a.net']['checked'])->toBeTrue();
});

it('matches an extension only on a label boundary', function () {
    // An extension typed without its leading dot must not claim every name
    // that merely ends in the same letters (example.telecom is not a .com).
    perTldSetup([['com', 'fakeepp']]);
    perTldBulkAnswer(['free-x.telecom' => 'AVAILABLE']);

    app(DomainAvailability::class)->checkMany(['free-x.telecom']);

    expect(PerTldFakeRegistrar::$asked)->toBe([]);
});

it('check() for one name follows the TLD registrar too', function () {
    perTldSetup([['.test', 'fakeepp']]);
    Http::fake();

    $result = app(DomainAvailability::class)->check('free-one.test');

    Http::assertNothingSent();
    expect(PerTldFakeRegistrar::$asked)->toBe(['free-one.test'])
        ->and($result)->toMatchArray(['available' => true, 'checked' => true]);
});
