<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Market exchange rates from free, keyless providers.
 *
 * pnlcs:currency-update stores them on the site's currencies; a screen that
 * needs a rate the site has no currency for (Panelica prices in dollars on a
 * euro site) asks here instead.
 */
class ExchangeRateService
{
    /**
     * Try the providers in order.
     *
     * @return array<string, float>|null code => units of that currency for one $base
     */
    public function rates(string $base, int $timeout = 20): ?array
    {
        $base = strtoupper($base);

        // Provider 1: open.er-api.com (170+ currencies)
        try {
            $response = Http::timeout($timeout)->get("https://open.er-api.com/v6/latest/{$base}");
            if ($response->successful() && ($response->json('result') === 'success') && is_array($response->json('rates'))) {
                return $response->json('rates');
            }
        } catch (\Throwable $e) {
            Log::warning('Currency update: er-api failed: '.$e->getMessage());
        }

        // Provider 2: frankfurter.app (ECB reference rates)
        try {
            $response = Http::timeout($timeout)->get('https://api.frankfurter.app/latest', ['from' => $base]);
            if ($response->successful() && is_array($response->json('rates'))) {
                return $response->json('rates');
            }
        } catch (\Throwable $e) {
            Log::warning('Currency update: frankfurter failed: '.$e->getMessage());
        }

        // Provider 3: allratestoday.com (ECB reference rates, re-based to $base server-side)
        try {
            $response = Http::timeout($timeout)->get("https://allratestoday.com/api/open/latest/{$base}");
            if ($response->successful() && ($response->json('result') === 'success') && is_array($response->json('rates'))) {
                return $response->json('rates');
            }
        } catch (\Throwable $e) {
            Log::warning('Currency update: allratestoday failed: '.$e->getMessage());
        }

        return null;
    }

    /**
     * Units of $to for one $from, or null when no provider answers.
     */
    public function rate(string $from, string $to, int $timeout = 20): ?float
    {
        $rates = $this->rates($from, $timeout);
        $rate = $rates[strtoupper($to)] ?? null;

        return is_numeric($rate) && (float) $rate > 0 ? (float) $rate : null;
    }
}
