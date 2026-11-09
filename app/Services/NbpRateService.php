<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Official Polish mid rates, straight from the National Bank of Poland.
 *
 * NBP publishes table A (the major currencies) once per business day, quoted
 * as PLN per one unit of the foreign currency. The site stores the rate the
 * other way round - units of the foreign currency for one PLN - so the value
 * is inverted here, in the one place the direction is written down.
 *
 * The API serves the latest published table, so a weekend or a public holiday
 * correctly answers with the previous business day's rate.
 */
class NbpRateService
{
    private const URL = 'https://api.nbp.pl/api/exchangerates/tables/A/?format=json';

    /**
     * The currencies table A quotes. A code outside this list has no NBP rate
     * and the caller keeps the commercial one.
     */
    public const TABLE_A = [
        'THB', 'USD', 'AUD', 'HKD', 'CAD', 'NZD', 'SGD', 'EUR', 'HUF', 'CHF',
        'GBP', 'UAH', 'JPY', 'CZK', 'DKK', 'ISK', 'NOK', 'SEK', 'RON', 'TRY',
        'ILS', 'CLP', 'PHP', 'MXN', 'ZAR', 'BRL', 'MYR', 'IDR', 'INR', 'KRW',
        'CNY', 'XDR',
    ];

    public static function supports(string $code): bool
    {
        return in_array(strtoupper(trim($code)), self::TABLE_A, true);
    }

    /**
     * The latest table A: the publisher's date and table number, and the mid
     * rate of each currency in PLN per one unit.
     *
     * @return array{date: string, no: string, rates: array<string, float>}|null
     */
    public function table(): ?array
    {
        try {
            $response = Http::timeout(20)->acceptJson()->get(self::URL);
        } catch (\Throwable $e) {
            Log::warning('NBP rate fetch failed: '.$e->getMessage());

            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $json = $response->json();
        $table = is_array($json) ? ($json[0] ?? null) : null;

        if (! is_array($table) || ! is_array($table['rates'] ?? null)) {
            return null;
        }

        $rates = [];
        foreach ($table['rates'] as $row) {
            $code = strtoupper((string) ($row['code'] ?? ''));
            $mid = $row['mid'] ?? null;

            if (preg_match('/^[A-Z]{3}$/', $code) && is_numeric($mid) && (float) $mid > 0) {
                $rates[$code] = (float) $mid;
            }
        }

        if ($rates === []) {
            return null;
        }

        return [
            'date' => (string) ($table['effectiveDate'] ?? ''),
            'no' => (string) ($table['no'] ?? ''),
            'rates' => $rates,
        ];
    }

    /**
     * One currency's rate the way the site stores it: units of the currency
     * for one PLN, with the provenance an invoice prints.
     *
     * @return array{rate: float, date: string, bulletin: ?string, kind: string, source: string}|null
     */
    public function rateFor(string $code): ?array
    {
        $code = strtoupper(trim($code));
        $table = $this->table();

        if ($table === null || ! isset($table['rates'][$code])) {
            return null;
        }

        return [
            'rate' => round(1 / $table['rates'][$code], 6),
            'date' => $table['date'],
            'bulletin' => $table['no'] !== '' ? $table['no'] : null,
            'kind' => 'reference',
            'source' => 'NBP',
        ];
    }
}
