<?php

namespace App\Console\Commands;

use App\Models\Currency;
use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CurrencyUpdateCommand extends Command
{
    protected $signature = 'pnlcs:currency-update {--force : Run even when auto-update is disabled}';

    protected $description = 'Update currency exchange rates from the default currency base';

    public function handle(): int
    {
        if (!$this->option('force') && Setting::get('currency_auto_update', '1') !== '1') {
            $this->info('Currency auto-update is disabled (setting currency_auto_update).');
            return self::SUCCESS;
        }

        $base = Currency::getDefault();
        if (!$base) {
            $this->warn('No default currency configured — nothing to update.');
            return self::SUCCESS;
        }

        $others = Currency::where('is_default', false)->get();
        if ($others->isEmpty()) {
            $this->info('Only the default currency exists — nothing to update.');
            return self::SUCCESS;
        }

        $rates = $this->fetchRates(strtoupper($base->code));
        if ($rates === null) {
            Log::error('Currency update: all rate providers failed', ['base' => $base->code]);
            $this->error('All exchange rate providers failed.');
            return self::FAILURE;
        }

        $updated = [];
        $missing = [];
        foreach ($others as $currency) {
            $code = strtoupper($currency->code);
            if (!isset($rates[$code]) || !is_numeric($rates[$code]) || $rates[$code] <= 0) {
                $missing[] = $code;
                continue;
            }
            $currency->update(['rate' => round((float) $rates[$code], 5)]);
            $updated[] = $code;
        }

        // Base rate is 1.0 by definition.
        if ((float) $base->rate !== 1.0) {
            $base->update(['rate' => 1.0]);
        }

        run_hook('CurrencyRatesUpdated', ['base' => $base->code, 'updated' => $updated, 'missing' => $missing]);

        Log::info('Currency rates updated', ['base' => $base->code, 'updated' => $updated, 'missing' => $missing]);
        $this->info(sprintf(
            'Rates updated from %s — updated: %s%s',
            strtoupper($base->code),
            $updated ? implode(', ', $updated) : 'none',
            $missing ? ' | no rate found for: ' . implode(', ', $missing) : ''
        ));

        return self::SUCCESS;
    }

    /**
     * Try providers in order; both are free and keyless.
     *
     * @return array<string, float>|null code => rate relative to $base
     */
    private function fetchRates(string $base): ?array
    {
        // Provider 1: open.er-api.com (170+ currencies)
        try {
            $response = Http::timeout(20)->get("https://open.er-api.com/v6/latest/{$base}");
            if ($response->successful() && ($response->json('result') === 'success') && is_array($response->json('rates'))) {
                return $response->json('rates');
            }
        } catch (\Throwable $e) {
            Log::warning('Currency update: er-api failed: ' . $e->getMessage());
        }

        // Provider 2: frankfurter.app (ECB reference rates)
        try {
            $response = Http::timeout(20)->get('https://api.frankfurter.app/latest', ['from' => $base]);
            if ($response->successful() && is_array($response->json('rates'))) {
                return $response->json('rates');
            }
        } catch (\Throwable $e) {
            Log::warning('Currency update: frankfurter failed: ' . $e->getMessage());
        }

        return null;
    }
}
