<?php

use App\Models\RegistrarSettings;
use App\Services\DomainAvailability;
use App\Services\WhoisLookup;
use Illuminate\Support\Facades\Http;
use Modules\Registrars\DomainNameApi\DomainNameApiRegistrar;

/*
 * DomainNameAPI answers ERROR, or NOTAVAILABLE with the reason "Unauthorized
 * TLD", when it did not look the name up. Both were read as "taken": against
 * the OTE on 2026-10-01 a made-up .io and .co name showed as registered. They
 * are no answer, so the caller asks WHOIS - and an unanswered name still never
 * reads as available.
 */

function dnaUsSettings(): void
{
    RegistrarSettings::updateOrCreate(['registrar' => 'domainnameapi', 'setting' => 'reseller_id'], ['value' => 'reseller-1']);
    RegistrarSettings::updateOrCreate(['registrar' => 'domainnameapi', 'setting' => 'api_key'], ['value' => 'key-1']);
}

function dnaUsWhois(bool $checked, bool $available = false): object
{
    $fake = new class($checked, $available) extends WhoisLookup
    {
        public array $asked = [];

        public function __construct(private bool $checkedResult, private bool $availableResult) {}

        public function check(string $domain, ?string $server): array
        {
            $this->asked[] = $domain;

            return ['available' => $this->availableResult, 'checked' => $this->checkedResult, 'response' => ''];
        }
    };
    app()->instance(WhoisLookup::class, $fake);

    return $fake;
}

it('asks WHOIS about the names the registry gave no answer for', function () {
    dnaUsSettings();
    $whois = dnaUsWhois(checked: true, available: true);
    Http::fake(['*domains/bulk-search*' => Http::response(['infos' => [
        ['domainName' => 'name.com', 'status' => 'AVAILABLE'],
        ['domainName' => 'name.net', 'status' => 'NOTAVAILABLE', 'reason' => 'Domain exists'],
        ['domainName' => 'name.io', 'status' => 'NOTAVAILABLE', 'reason' => 'Unauthorized TLD'],
        ['domainName' => 'name.co', 'status' => 'ERROR'],
    ]])]);

    $results = app(DomainAvailability::class)->checkMany(['name.com', 'name.net', 'name.io', 'name.co']);

    expect($results['name.com'])->toMatchArray(['available' => true, 'checked' => true])
        ->and($results['name.net'])->toMatchArray(['available' => false, 'checked' => true])
        ->and($whois->asked)->toBe(['name.io', 'name.co'])
        ->and($results['name.io']['checked'])->toBeTrue()
        ->and($results['name.co']['checked'])->toBeTrue();
});

it('never reads an unanswered name as available when WHOIS cannot answer either', function () {
    dnaUsSettings();
    dnaUsWhois(checked: false);
    Http::fake(['*domains/bulk-search*' => Http::response(['infos' => [
        ['domainName' => 'name.io', 'status' => 'NOTAVAILABLE', 'reason' => 'Unauthorized TLD'],
        ['domainName' => 'name.co', 'status' => 'ERROR'],
    ]])]);

    $results = app(DomainAvailability::class)->checkMany(['name.io', 'name.co']);

    expect($results['name.io'])->toMatchArray(['available' => false, 'checked' => false])
        ->and($results['name.co'])->toMatchArray(['available' => false, 'checked' => false]);
});

it('reports an ERROR status on a single check as an error, not as taken', function () {
    dnaUsSettings();
    Http::fake(['*domains/search*' => Http::response(['info' => ['domainName' => 'name.co', 'status' => 'ERROR']])]);

    $result = (new DomainNameApiRegistrar)->checkAvailability('name.co');

    expect($result['available'])->toBeFalse()
        ->and($result)->toHaveKey('error');
});

it('still reads a registered name as taken', function () {
    dnaUsSettings();
    Http::fake(['*domains/search*' => Http::response(['info' => ['domainName' => 'google.com', 'status' => 'NOTAVAILABLE']])]);

    $result = (new DomainNameApiRegistrar)->checkAvailability('google.com');

    expect($result['available'])->toBeFalse()
        ->and($result)->not->toHaveKey('error');
});
