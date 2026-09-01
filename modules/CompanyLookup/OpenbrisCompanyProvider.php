<?php

namespace Modules\CompanyLookup;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Modules\CompanyLookup\Contracts\CompanyDataProviderInterface;
use Modules\CompanyLookup\Exceptions\ProviderException;

/**
 * OpenBRIS — Open Business Register Information Service.
 *
 * A cross-country business-register aggregator (https://openbris.eu), keyed by
 * an API key sent in the `api-key` header. It is the least authoritative of
 * the four sources, so it only fills gaps left by GUS / CEIDG / MF.
 *
 * Lookup is by VAT number, which for a Polish company equals the NIP:
 *   GET {endpoint}/v1/vat/{nip}
 */
final class OpenbrisCompanyProvider implements CompanyDataProviderInterface
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
            throw new ProviderException('OpenBRIS: no API key configured', ProviderException::NOT_CONFIGURED);
        }

        $url = rtrim($this->endpoint, '/').'/v1/vat/'.$nip;

        try {
            $response = Http::timeout($this->requestTimeout)
                ->connectTimeout($this->connectTimeout)
                ->retry(1, 200)
                ->withHeaders(['api-key' => $this->apiKey])
                ->get($url);
        } catch (ConnectionException $e) {
            throw new ProviderException('OpenBRIS: connection failed — '.$e->getMessage(), ProviderException::API_TIMEOUT);
        }

        if ($response->status() === 429) {
            throw new ProviderException('OpenBRIS: rate limited', ProviderException::RATE_LIMIT);
        }

        if ($response->status() === 404) {
            return null;
        }

        if ($response->failed()) {
            throw new ProviderException('OpenBRIS: HTTP '.$response->status(), ProviderException::OPENBRIS_ERROR);
        }

        $body = $response->json();
        if (! is_array($body)) {
            throw new ProviderException('OpenBRIS: invalid response format', ProviderException::INVALID_RESPONSE);
        }

        return $this->map($body);
    }

    /**
     * Verify the OpenBRIS credentials and reach the API.
     *
     * @return array{success: bool, message: string}
     */
    public function testConnection(): array
    {
        if ($this->apiKey === null || trim($this->apiKey) === '') {
            return ['success' => false, 'message' => __('messages.company_lookup.test_no_key')];
        }

        $url = rtrim($this->endpoint, '/').'/v1/countries';

        try {
            $response = Http::timeout($this->requestTimeout)
                ->connectTimeout($this->connectTimeout)
                ->withHeaders(['api-key' => $this->apiKey])
                ->get($url);
        } catch (ConnectionException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }

        if ($response->successful()) {
            return ['success' => true, 'message' => __('messages.company_lookup.test_ok')];
        }

        if (in_array($response->status(), [401, 403], true)) {
            return ['success' => false, 'message' => __('messages.company_lookup.test_auth_failed')];
        }

        return ['success' => false, 'message' => 'HTTP '.$response->status()];
    }

    /**
     * @param  array<string, mixed>  $company
     */
    private function map(array $company): CompanyData
    {
        $data = new CompanyData();

        $data->name = $this->string($company['name'] ?? null);
        $data->street = $this->string($company['street'] ?? null);
        $data->city = $this->string($company['city'] ?? null);
        $data->postalCode = $this->string($company['zip'] ?? null);
        $data->nip = $this->string($company['vatNumber'] ?? null);

        if (is_array($company['country'] ?? null)) {
            $data->country = $this->string($company['country']['code2'] ?? $company['country']['code3'] ?? null);
        }

        // businessId is ambiguous (KRS vs REGON); only take it when it looks
        // like a 9-digit REGON. Identification already prefers GUS/CEIDG/MF.
        $businessId = $company['businessId'] ?? null;
        if (is_string($businessId) || is_int($businessId)) {
            $id = (string) $businessId;
            if (preg_match('/^\d{9}$/', $id)) {
                $data->regon = $id;
            }
        }

        return $data;
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
