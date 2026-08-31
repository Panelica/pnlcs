<?php

namespace Modules\CompanyLookup;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Modules\CompanyLookup\Contracts\CompanyDataProviderInterface;
use Modules\CompanyLookup\Exceptions\ProviderException;

/**
 * CEIDG — Centralna Ewidencja i Informacja o Działalności Gospodarczej.
 *
 * The "Hurtownia Danych" API v3 (https://dane.biznes.gov.pl/api/ceidg/v3/).
 * It covers natural persons registered in CEIDG; a legal entity (sp. z o.o.)
 * will simply not be found, which is a valid outcome — not an error.
 *
 * Identification priority is GUS → CEIDG → MF, so this provider is the second
 * opinion for name/REGON/address/PKD and the primary source for the business
 * status and activity/suspension dates.
 */
final class CeidgCompanyProvider implements CompanyDataProviderInterface
{
    public function __construct(
        private readonly string $endpoint,
        private readonly ?string $apiKey,
        private readonly int $connectTimeout,
        private readonly int $requestTimeout,
    ) {
    }

    public function findByNip(string $nip): ?CompanyData
    {
        if ($this->apiKey === null || trim($this->apiKey) === '') {
            throw new ProviderException('CEIDG: no API key configured', ProviderException::NOT_CONFIGURED);
        }

        $url = rtrim($this->endpoint, '/').'/firmy';

        try {
            $response = Http::timeout($this->requestTimeout)
                ->connectTimeout($this->connectTimeout)
                ->retry(1, 200)
                ->withHeaders(['Authorization' => 'Bearer '.$this->apiKey])
                ->get($url, ['nip' => '["'.$nip.'"]']);
        } catch (ConnectionException $e) {
            throw new ProviderException('CEIDG: connection failed — '.$e->getMessage(), ProviderException::API_TIMEOUT);
        }

        if ($response->status() === 429) {
            throw new ProviderException('CEIDG: rate limited', ProviderException::RATE_LIMIT);
        }

        // 204 means "no matching firm" — a valid, expected outcome.
        if ($response->status() === 204) {
            return null;
        }

        if ($response->failed()) {
            throw new ProviderException('CEIDG: HTTP '.$response->status(), ProviderException::CEIDG_ERROR);
        }

        $body = $response->json();
        if (! is_array($body)) {
            throw new ProviderException('CEIDG: invalid response format', ProviderException::INVALID_RESPONSE);
        }

        $firmy = $body['firmy'] ?? [];
        if (! is_array($firmy) || $firmy === []) {
            return null;
        }

        return $this->map($firmy[0]);
    }

    /**
     * @param  array<string, mixed>  $f
     */
    private function map(array $f): CompanyData
    {
        $data = new CompanyData();

        $data->name = $this->string($f['nazwa'] ?? null);

        $wlasciciel = is_array($f['wlasciciel'] ?? null) ? $f['wlasciciel'] : [];
        $data->nip = $this->string($wlasciciel['nip'] ?? null);
        $data->regon = $this->string($wlasciciel['regon'] ?? null);

        $adres = is_array($f['adresDzialalnosci'] ?? null) ? $f['adresDzialalnosci'] : [];
        $data->street = $this->string($adres['ulica'] ?? null);
        $data->buildingNumber = $this->string($adres['budynek'] ?? null);
        $data->apartmentNumber = $this->string($adres['lokal'] ?? null);
        $data->postalCode = $this->string($adres['kod'] ?? null);
        $data->city = $this->string($adres['miasto'] ?? null);
        $data->voivodeship = $this->string($adres['wojewodztwo'] ?? null);
        $data->country = $this->string($adres['kraj'] ?? null);

        $data->businessStatus = $this->string($f['status'] ?? null);
        $data->activityStartDate = $this->string($f['dataRozpoczecia'] ?? null);
        $data->activityEndDate = $this->string($f['dataZakonczenia'] ?? null);
        $data->suspensionStartDate = $this->string($f['dataZawieszenia'] ?? null);
        $data->suspensionEndDate = $this->string($f['dataWznowienia'] ?? null);

        $data->pkd = $this->pkdCodes($f);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $f
     * @return array<int, string>
     */
    private function pkdCodes(array $f): array
    {
        $codes = [];

        foreach ((array) ($f['pkd'] ?? []) as $entry) {
            $code = is_array($entry) ? ($entry['kod'] ?? null) : $entry;
            if (is_string($code) && trim($code) !== '') {
                $codes[] = trim($code);
            }
        }

        if ($codes === []) {
            $main = $f['pkdGlowny'] ?? null;
            if (is_array($main) && ! empty($main['kod'])) {
                $codes[] = (string) $main['kod'];
            }
        }

        return array_values(array_unique($codes));
    }

    private function string(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
