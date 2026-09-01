<?php

use App\Models\AddonSetting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Modules\CompanyLookup\CeidgCompanyProvider;
use Modules\CompanyLookup\CompanyLookupService;
use Modules\CompanyLookup\Exceptions\ProviderException;

/*
 * Company lookup (GUS REGON / MF Biała Lista / CEIDG).
 *
 * The providers are faked at the HTTP layer; only the normalised result and
 * the source/error flags are asserted, never the raw registry responses.
 */

function mfBody(?array $subject, int $status = 200)
{
    return Http::response(json_encode(['result' => ['subject' => $subject]]), $status, ['Content-Type' => 'application/json']);
}

function mfFound(): array
{
    return [
        'name' => 'PRZYKŁADOWA FIRMA SP. Z O.O.',
        'nip' => '5261040828',
        'statusVat' => 'Czynny',
        'regon' => '000331501',
        'workingAddress' => 'Przykładowa 10, 00-001 Warszawa',
        'accountNumbers' => ['11101010100000000000000000'],
    ];
}

function gusFound(): array
{
    return [
        'Nazwa' => 'GUS NAZWA SP. Z O.O.',
        'Nip' => '5261040828',
        'Regon' => '000331501',
        'AdSiedzUlica' => 'Gusowa',
        'AdSiedzNrNieruchomosci' => '7',
        'AdSiedzNrLokalu' => '3',
        'AdSiedzKodPocztowy' => '00-001',
        'AdSiedzMiejscowosc' => 'Warszawa',
        'AdSiedzWojewodztwo' => 'MAZOWIECKIE',
        'FormaPrawna' => 'spółka z ograniczoną odpowiedzialnością',
        'PKD' => '62.01.Z',
    ];
}

function ceidgFound(): array
{
    return [
        'nazwa' => '24BOX IGOR RYBICKI',
        'wlasciciel' => ['imie' => 'IGOR', 'nazwisko' => 'RYBICKI', 'nip' => '5261040828', 'regon' => '000331501'],
        'adresDzialalnosci' => ['ulica' => 'Ceidgowa', 'budynek' => '5', 'lokal' => '2', 'miasto' => 'Warszawa', 'wojewodztwo' => 'MAZOWIECKIE', 'kod' => '00-001', 'kraj' => 'PL'],
        'status' => 'AKTYWNY',
        'dataRozpoczecia' => '2020-01-01',
        'dataZawieszenia' => '2021-05-05',
        'pkd' => [['kod' => '62.01.Z', 'nazwa' => 'x']],
    ];
}

function ceidgBody(?array $firma, int $status = 200)
{
    $payload = $firma === null ? ['firmy' => []] : ['firmy' => [$firma]];

    return Http::response(json_encode($payload), $status, ['Content-Type' => 'application/json']);
}

function openbrisFound(): array
{
    return [
        'name' => 'OPENBRIS COMPANY',
        'street' => 'Openbrisowa 10',
        'city' => 'Kraków',
        'zip' => '30-001',
        'country' => ['code2' => 'PL', 'code3' => 'POL', 'name' => 'Poland'],
        'vatNumber' => '5261040828',
        'businessId' => 123456789,
    ];
}

function fakeGusSoap(array $fields): callable
{
    $xml = '<root><dane>';
    foreach ($fields as $tag => $value) {
        $xml .= '<'.$tag.'>'.$value.'</'.$tag.'>';
    }
    $xml .= '</dane></root>';

    return function ($request) use ($xml) {
        $body = (string) $request->body();

        if (str_contains($body, 'Zaloguj')) {
            $out = '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Body>'
                .'<ZalogujResponse xmlns="http://CIS/BIR/PUBL/2014/7"><ZalogujResult>SESSION123</ZalogujResult></ZalogujResponse>'
                .'</soap:Body></soap:Envelope>';

            return Http::response($out, 200, ['Content-Type' => 'text/xml']);
        }

        if (str_contains($body, 'Wyloguj')) {
            $out = '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Body>'
                .'<WylogujResponse xmlns="http://CIS/BIR/PUBL/2014/7"><WylogujResult>true</WylogujResult></WylogujResponse>'
                .'</soap:Body></soap:Envelope>';

            return Http::response($out, 200, ['Content-Type' => 'text/xml']);
        }

        $out = '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Body>'
            .'<DaneSzukajPodmiotyResponse xmlns="http://CIS/BIR/PUBL/2014/7"><DaneSzukajPodmiotyResult>'
            .base64_encode($xml)
            .'</DaneSzukajPodmiotyResult></DaneSzukajPodmiotyResponse>'
            .'</soap:Body></soap:Envelope>';

        return Http::response($out, 200, ['Content-Type' => 'text/xml']);
    };
}

function fakeAll(callable $mf, callable $gus, callable $ceidg): void
{
    Http::fake(function ($request) use ($mf, $gus, $ceidg) {
        if (str_contains($request->url(), 'wl-api.mf.gov.pl')) {
            return $mf($request);
        }
        if (str_contains($request->url(), 'dane.biznes.gov.pl')) {
            return $ceidg($request);
        }

        return $gus($request);
    });
}

function configureKey(string $setting, string $class): void
{
    AddonSetting::setSetting('company_lookup', $setting, 'test-key');
    app()->forgetInstance($class);
    app()->forgetInstance(CompanyLookupService::class);
}

test('lookup merges all three sources with the documented priorities', function () {
    Cache::flush();
    configureKey('gus_api_key', \Modules\CompanyLookup\GusCompanyProvider::class);
    configureKey('ceidg_api_key', \Modules\CompanyLookup\CeidgCompanyProvider::class);

    fakeAll(
        fn ($r) => mfBody(mfFound()),
        fakeGusSoap(gusFound()),
        fn ($r) => ceidgBody(ceidgFound()),
    );

    $result = app(CompanyLookupService::class)->lookup('5261040828');

    expect($result['success'])->toBeTrue()
        ->and($result['sources'])->toBe(['gus' => true, 'mf' => true, 'ceidg' => true, 'openbris' => false])
        ->and($result['company']['name'])->toBe('GUS NAZWA SP. Z O.O.') // GUS wins identification
        ->and($result['company']['vat']['status'])->toBe('Czynny') // MF wins VAT
        ->and($result['company']['bank_accounts'])->toBe(['11101010100000000000000000'])
        ->and($result['company']['pkd'])->toContain('62.01.Z')
        ->and($result['company']['business_status'])->toBe('AKTYWNY') // CEIDG wins status
        ->and($result['company']['activity_start_date'])->toBe('2020-01-01')
        ->and($result['company']['suspension_start_date'])->toBe('2021-05-05')
        ->and($result['warnings'])->not->toBeEmpty();
});

test('a company found only in GUS still succeeds with a warning', function () {
    Cache::flush();
    configureKey('gus_api_key', \Modules\CompanyLookup\GusCompanyProvider::class);

    Http::fake(function ($request) {
        if (str_contains($request->url(), 'wl-api.mf.gov.pl')) {
            throw new ConnectionException('MF down');
        }

        return fakeGusSoap(gusFound())($request);
    });

    $result = app(CompanyLookupService::class)->lookup('5261040828');

    expect($result['success'])->toBeTrue()
        ->and($result['sources']['gus'])->toBeTrue()
        ->and($result['sources']['mf'])->toBeFalse()
        ->and($result['company']['name'])->toBe('GUS NAZWA SP. Z O.O.')
        ->and($result['warnings'])->not->toBeEmpty();
});

test('an unconfigured optional source is skipped without a warning', function () {
    Cache::flush();
    configureKey('gus_api_key', \Modules\CompanyLookup\GusCompanyProvider::class);

    fakeAll(
        fn ($r) => mfBody(mfFound()),
        fakeGusSoap(gusFound()),
        fn ($r) => throw new ConnectionException('CEIDG should not be called'),
    );

    $result = app(CompanyLookupService::class)->lookup('5261040828');

    // CEIDG has no key configured → skipped silently (ceidg: false, no warning).
    expect($result['success'])->toBeTrue()
        ->and($result['sources'])->toBe(['gus' => true, 'mf' => true, 'ceidg' => false, 'openbris' => false])
        ->and(array_filter($result['warnings'], 'is_string'))->toBe([]);
});

test('a NIP unknown to every register is reported as not found', function () {
    Cache::flush();
    configureKey('gus_api_key', \Modules\CompanyLookup\GusCompanyProvider::class);
    configureKey('ceidg_api_key', \Modules\CompanyLookup\CeidgCompanyProvider::class);

    fakeAll(
        fn ($r) => mfBody(null),
        fakeGusSoap([]),
        fn ($r) => ceidgBody(null),
    );

    $result = app(CompanyLookupService::class)->lookup('5261040828');

    expect($result['success'])->toBeFalse()
        ->and($result['error'])->toBe('COMPANY_NOT_FOUND');
});

test('an invalid NIP never reaches the registries', function () {
    Cache::flush();
    Http::preventStrayRequests();

    $result = app(CompanyLookupService::class)->lookup('1234567890');

    expect($result['success'])->toBeFalse()
        ->and($result['error'])->toBe('INVALID_NIP');

    Http::assertNothingSent();
});

test('when every source errors the result is a failure, not not-found', function () {
    Cache::flush();
    configureKey('gus_api_key', \Modules\CompanyLookup\GusCompanyProvider::class);
    configureKey('ceidg_api_key', \Modules\CompanyLookup\CeidgCompanyProvider::class);

    Http::fake(fn ($request) => throw new ConnectionException('all down'));

    $result = app(CompanyLookupService::class)->lookup('5261040828');

    expect($result['success'])->toBeFalse()
        ->and($result['error'])->toBe('UNKNOWN_ERROR')
        ->and($result['warnings'])->toHaveCount(3);
});

test('a successful result is cached and the second call skips the network', function () {
    Cache::flush();
    configureKey('gus_api_key', \Modules\CompanyLookup\GusCompanyProvider::class);

    $mfCalls = 0;
    Http::fake(function ($request) use (&$mfCalls) {
        if (str_contains($request->url(), 'wl-api.mf.gov.pl')) {
            $mfCalls++;

            return mfBody(mfFound());
        }

        return fakeGusSoap(gusFound())($request);
    });

    $service = app(CompanyLookupService::class);

    $first = $service->lookup('5261040828');
    $second = $service->lookup('5261040828');

    expect($first['success'])->toBeTrue()
        ->and($second)->toBe($first)
        ->and($mfCalls)->toBe(1);
});

test('the endpoint returns the normalised shape', function () {
    Cache::flush();
    configureKey('gus_api_key', \Modules\CompanyLookup\GusCompanyProvider::class);
    configureKey('ceidg_api_key', \Modules\CompanyLookup\CeidgCompanyProvider::class);

    fakeAll(
        fn ($r) => mfBody(mfFound()),
        fakeGusSoap(gusFound()),
        fn ($r) => ceidgBody(ceidgFound()),
    );

    $this->postJson('/api/company/lookup', ['nip' => '526-104-08-28'])
        ->assertOk()
        ->assertJson([
            'success' => true,
            'company' => [
                'nip' => '5261040828',
                'vat' => ['status' => 'Czynny'],
                'business_status' => 'AKTYWNY',
            ],
            'sources' => ['gus' => true, 'mf' => true, 'ceidg' => true, 'openbris' => false],
        ]);
});

test('the endpoint rejects a missing nip', function () {
    $this->postJson('/api/company/lookup', [])
        ->assertStatus(422);
});

test('the CEIDG provider maps a firm found in the register', function () {
    Http::fake(['dane.biznes.gov.pl/*' => ceidgBody(ceidgFound())]);

    $provider = new CeidgCompanyProvider('https://dane.biznes.gov.pl/api/ceidg/v3', 'test-key', 5, 10);

    $data = $provider->findByNip('5261040828');

    expect($data)->not->toBeNull()
        ->and($data->name)->toBe('24BOX IGOR RYBICKI')
        ->and($data->nip)->toBe('5261040828')
        ->and($data->regon)->toBe('000331501')
        ->and($data->city)->toBe('Warszawa')
        ->and($data->businessStatus)->toBe('AKTYWNY')
        ->and($data->pkd)->toContain('62.01.Z');
});

test('the CEIDG provider returns null when the firm is not registered', function () {
    Http::fake(['dane.biznes.gov.pl/*' => ceidgBody(null)]);

    $provider = new CeidgCompanyProvider('https://dane.biznes.gov.pl/api/ceidg/v3', 'test-key', 5, 10);

    expect($provider->findByNip('5261040828'))->toBeNull();
});

test('the CEIDG provider throws NOT_CONFIGURED without a key', function () {
    $provider = new CeidgCompanyProvider('https://dane.biznes.gov.pl/api/ceidg/v3', null, 5, 10);

    try {
        $provider->findByNip('5261040828');
        $this->fail('Expected ProviderException');
    } catch (ProviderException $e) {
        expect($e->codeName())->toBe(ProviderException::NOT_CONFIGURED);
    }
});

test('the OpenBRIS provider maps a company found in the register', function () {
    Http::fake(['api.openbris.eu/*' => Http::response(json_encode(openbrisFound()), 200, ['Content-Type' => 'application/json'])]);

    $provider = new \Modules\CompanyLookup\OpenbrisCompanyProvider('https://api.openbris.eu', 'test-key', 5, 10);

    $data = $provider->findByNip('5261040828');

    expect($data)->not->toBeNull()
        ->and($data->name)->toBe('OPENBRIS COMPANY')
        ->and($data->city)->toBe('Kraków')
        ->and($data->nip)->toBe('5261040828')
        ->and($data->regon)->toBe('123456789');
});

test('the OpenBRIS provider returns null when the firm is not found', function () {
    Http::fake(['api.openbris.eu/*' => Http::response('', 404)]);

    $provider = new \Modules\CompanyLookup\OpenbrisCompanyProvider('https://api.openbris.eu', 'test-key', 5, 10);

    expect($provider->findByNip('5261040828'))->toBeNull();
});

test('the OpenBRIS provider throws NOT_CONFIGURED without a key', function () {
    $provider = new \Modules\CompanyLookup\OpenbrisCompanyProvider('https://api.openbris.eu', null, 5, 10);

    try {
        $provider->findByNip('5261040828');
        $this->fail('Expected ProviderException');
    } catch (ProviderException $e) {
        expect($e->codeName())->toBe(ProviderException::NOT_CONFIGURED);
    }
});
