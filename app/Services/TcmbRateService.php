<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Official lira rates, straight from the central bank.
 *
 * Invoices raised in lira against a dollar price have to name the rate they
 * were struck at, and a customer has to be able to check it. A commercial
 * rate API cannot be checked - the TCMB bulletin can, by date and number.
 *
 * The bank publishes on business days only, around 15:30 Istanbul time, so
 * the lookup walks backwards to the most recent published bulletin. That is
 * also the correct answer: on a Sunday the valid official rate *is* Friday's.
 */
class TcmbRateService
{
    private const BASE = 'https://www.tcmb.gov.tr/kurlar';

    /** How far back to look before giving up (covers a long public holiday). */
    private const MAX_LOOKBACK_DAYS = 12;

    /**
     * The official rate for one unit of $code in Turkish lira.
     *
     * @return array{rate: float, date: string, bulletin: ?string, kind: string, source: string}|null
     */
    public function rateFor(string $code = 'USD', string $kind = 'ForexSelling'): ?array
    {
        $code = strtoupper($code);

        foreach ($this->candidateUrls() as [$url, $day]) {
            $xml = $this->fetch($url);

            if ($xml === null) {
                continue;
            }

            $currency = null;
            foreach ($xml->Currency as $node) {
                if (strtoupper((string) $node['Kod']) === $code) {
                    $currency = $node;
                    break;
                }
            }

            if ($currency === null) {
                continue;
            }

            $value = (float) str_replace(',', '.', trim((string) $currency->{$kind}));
            $unit = (int) trim((string) $currency->Unit) ?: 1;

            if ($value <= 0) {
                continue;
            }

            return [
                'rate' => round($value / $unit, 6),
                'date' => (string) ($xml['Tarih'] ?: $day->format('d.m.Y')),
                'bulletin' => trim((string) $xml['Bulten_No']) ?: null,
                'kind' => $kind,
                'source' => 'TCMB',
            ];
        }

        Log::warning('TCMB rate lookup failed', ['code' => $code, 'kind' => $kind]);

        return null;
    }

    /**
     * today.xml first, then dated bulletins going backwards.
     *
     * today.xml is the live one but only exists after the daily publication;
     * before that it still serves the previous business day, which is what we
     * want anyway.
     *
     * @return list<array{0: string, 1: Carbon}>
     */
    private function candidateUrls(): array
    {
        $today = Carbon::now('Europe/Istanbul');
        $urls = [[self::BASE.'/today.xml', $today]];

        for ($i = 0; $i <= self::MAX_LOOKBACK_DAYS; $i++) {
            $day = $today->copy()->subDays($i);

            if ($day->isWeekend()) {
                continue;
            }

            $urls[] = [
                sprintf('%s/%s/%s.xml', self::BASE, $day->format('Ym'), $day->format('dmY')),
                $day,
            ];
        }

        return $urls;
    }

    private function fetch(string $url): ?\SimpleXMLElement
    {
        try {
            $response = Http::timeout(15)
                ->withHeaders(['User-Agent' => 'pnlcs/1.0 (+enahosting.com)'])
                ->get($url);

            if (! $response->successful()) {
                return null;
            }

            $previous = libxml_use_internal_errors(true);
            $xml = simplexml_load_string($response->body());
            libxml_clear_errors();
            libxml_use_internal_errors($previous);

            return $xml === false ? null : $xml;
        } catch (\Throwable $e) {
            Log::warning('TCMB fetch failed: '.$e->getMessage(), ['url' => $url]);

            return null;
        }
    }
}
