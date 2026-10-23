<?php

namespace App\Console\Commands;

use App\Models\Currency;
use App\Models\Setting;
use App\Services\ExchangeRateService;
use App\Services\TcmbRateService;
use Illuminate\Console\Command;
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

        $rates = app(ExchangeRateService::class)->rates($base->code);
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

        // The billing currency's rate comes from an official source instead,
        // when the operator named one: invoices print the rate they were
        // struck at, and a customer can check a central bank bulletin number
        // but not a commercial API's.
        $official = (string) Setting::get('OfficialRateProvider', '') === 'tcmb'
            && $this->applyOfficialLiraRate($base, $updated, $missing);

        // The lira rate now comes from the commercial provider: the bulletin
        // details of an earlier TCMB run no longer describe it, and invoices
        // kept printing them under a rate they did not come from.
        if (! $official) {
            foreach (['ExchangeRateSource', 'ExchangeRateKind', 'ExchangeRateDate', 'ExchangeRateBulletin'] as $key) {
                Setting::set($key, '');
            }
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
     * Overwrite the lira rate with the official TCMB one and record its
     * provenance, so an invoice can print where the number came from.
     *
     * A failure here is not fatal - the commercial rate already fetched stays,
     * and the invoice simply names that source instead.
     *
     * @return bool whether the lira rate is now the official one
     */
    private function applyOfficialLiraRate(Currency $base, array &$updated, array &$missing): bool
    {
        if (strtoupper($base->code) !== 'USD') {
            return false;                 // TCMB quotes against the lira only
        }

        $try = Currency::where('code', 'TRY')->first();

        if (! $try) {
            return false;
        }

        $kind = (string) Setting::get('TcmbRateKind', 'ForexSelling');
        $official = app(TcmbRateService::class)->rateFor('USD', $kind);

        if ($official === null) {
            $this->warn('TCMB unavailable - lira rate left at the commercial provider value.');

            return false;
        }

        $try->update(['rate' => round($official['rate'], 5)]);

        Setting::set('ExchangeRateSource', $official['source']);
        Setting::set('ExchangeRateKind', $official['kind']);
        Setting::set('ExchangeRateDate', $official['date']);
        Setting::set('ExchangeRateBulletin', (string) $official['bulletin']);

        if (! in_array('TRY', $updated, true)) {
            $updated[] = 'TRY';
        }
        $missing = array_values(array_diff($missing, ['TRY']));

        $this->info(sprintf(
            'TRY set from TCMB %s (%s, bulletin %s): 1 USD = %s TRY',
            $official['kind'],
            $official['date'],
            $official['bulletin'] ?? '-',
            number_format($official['rate'], 4)
        ));

        return true;
    }
}
