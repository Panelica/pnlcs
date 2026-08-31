<?php

namespace Modules\CompanyLookup;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Modules\CompanyLookup\Contracts\CompanyDataProviderInterface;
use Modules\CompanyLookup\Exceptions\ProviderException;

/**
 * Ministerstwo Finansów "Biała Lista" VAT taxpayer register.
 *
 * REST, no key required:
 *   GET {endpoint}/search/nip/{nip}?date={YYYY-MM-DD}
 *
 * Primary source for: VAT status, bank accounts, and a fallback for name /
 * NIP / REGON / address. Identification and PKD stay with GUS.
 */
final class MfVatProvider implements CompanyDataProviderInterface
{
    public function __construct(
        private readonly string $endpoint,
        private readonly int $connectTimeout,
        private readonly int $requestTimeout,
    ) {
    }

    public function findByNip(string $nip): ?CompanyData
    {
        $url = rtrim($this->endpoint, '/').'/search/nip/'.$nip;
        $date = now()->format('Y-m-d');

        try {
            $response = Http::timeout($this->requestTimeout)
                ->connectTimeout($this->connectTimeout)
                ->retry(1, 200)
                ->get($url, ['date' => $date]);
        } catch (ConnectionException $e) {
            throw new ProviderException('MF: connection failed — '.$e->getMessage(), ProviderException::MF_ERROR);
        }

        if ($response->status() === 429) {
            throw new ProviderException('MF: rate limited', ProviderException::RATE_LIMIT);
        }

        // 400 (bad request), 404 (not found) and 200 with subject=null all mean
        // "the register has no such NIP" — not a lookup error.
        if ($response->status() === 400 || $response->status() === 404) {
            return null;
        }

        if ($response->failed()) {
            throw new ProviderException('MF: HTTP '.$response->status(), ProviderException::MF_ERROR);
        }

        $body = $response->json();
        if (! is_array($body)) {
            throw new ProviderException('MF: invalid response format', ProviderException::MF_ERROR);
        }

        $subject = $body['result']['subject'] ?? null;

        if (! is_array($subject)) {
            return null;
        }

        return $this->map($subject);
    }

    /**
     * Verify the MF endpoint is reachable (no key required).
     *
     * @return array{success: bool, message: string}
     */
    public function testConnection(): array
    {
        $url = rtrim($this->endpoint, '/').'/search/nip/5261040828';

        try {
            $response = Http::timeout($this->requestTimeout)
                ->connectTimeout($this->connectTimeout)
                ->get($url, ['date' => now()->format('Y-m-d')]);
        } catch (ConnectionException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }

        // 200 (found), 400/404 (reachable but unknown) all prove the API works.
        if ($response->successful() || in_array($response->status(), [400, 404], true)) {
            return ['success' => true, 'message' => __('messages.company_lookup.test_ok')];
        }

        return ['success' => false, 'message' => 'HTTP '.$response->status()];
    }

    /**
     * @param  array<string, mixed>  $subject
     */
    private function map(array $subject): CompanyData
    {
        $data = new CompanyData();

        $data->name = $this->string($subject['name'] ?? null);
        $data->nip = $this->string($subject['nip'] ?? null);
        $data->regon = $this->string($subject['regon'] ?? null);
        $data->vatStatus = $this->string($subject['statusVat'] ?? null);

        // A company carries a workingAddress; a natural person a residenceAddress.
        // Both are a single string: "ULICA 10, 00-000 MIASTO".
        $address = $subject['workingAddress'] ?? $subject['residenceAddress'] ?? null;
        if (is_string($address) && trim($address) !== '') {
            $parsed = $this->parseAddress($address);
            $data->street = $parsed['street'];
            $data->buildingNumber = $parsed['building_number'];
            $data->postalCode = $parsed['postal_code'];
            $data->city = $parsed['city'];
        }

        $data->bankAccounts = array_values(array_filter(
            array_map('trim', (array) ($subject['accountNumbers'] ?? [])),
            fn (string $n) => $n !== ''
        ));

        return $data;
    }

    /**
     * Best-effort parse of the combined MF address string. GUS is the primary
     * address source, so this only has to be good enough when GUS is down.
     *
     * @return array{street: ?string, building_number: ?string, postal_code: ?string, city: ?string}
     */
    private function parseAddress(string $address): array
    {
        $address = trim((string) mb_convert_case($address, MB_CASE_TITLE, 'UTF-8'));
        $parts = array_map('trim', explode(',', $address));

        $postalCode = null;
        $city = null;
        $streetPart = null;

        foreach ($parts as $part) {
            if ($postalCode === null && preg_match('/(\d{2}-\d{3})/', $part, $m)) {
                $postalCode = $m[1];
                $city = trim((string) preg_replace('/\d{2}-\d{3}/', '', $part));
                continue;
            }
            if ($streetPart === null) {
                $streetPart = $part;
            }
        }

        [$street, $buildingNumber] = $this->splitStreetNumber((string) $streetPart);

        return [
            'street' => $street ?: null,
            'building_number' => $buildingNumber ?: null,
            'postal_code' => $postalCode,
            'city' => $city ?: null,
        ];
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function splitStreetNumber(?string $street): array
    {
        if ($street === null || $street === '') {
            return [null, null];
        }

        // "UL. KWIATOWA 12/3" -> street "ul. Kwiatowa", number "12/3"
        if (preg_match('/^(.*?)\s+(\d+[A-Za-z]?(\/\d+[A-Za-z]?)?)$/u', $street, $m)) {
            return [trim($m[1]) ?: null, $m[2]];
        }

        return [trim($street) ?: null, null];
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
