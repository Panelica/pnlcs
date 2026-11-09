<?php

namespace App\Console\Commands;

use App\Models\Currency;
use App\Models\Setting;
use App\Services\ExchangeRateService;
use App\Services\NbpRateService;
use App\Services\OfficialRateProviders;
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

        $provider = (string) Setting::get('OfficialRateProvider', '');

        // NBP quotes against the złoty: when it is the chosen source and the
        // shop keeps its books in PLN, its table A drives every currency. The
        // commercial providers only fill the codes NBP does not publish.
        $nbp = $provider === 'nbp' && strtoupper($base->code) === 'PLN'
            ? app(NbpRateService::class)->table()
            : null;

        if ($provider === 'nbp' && $nbp === null) {
            $this->warn(strtoupper($base->code) === 'PLN'
                ? 'NBP is unreachable - rates left at the commercial provider values.'
                : sprintf('NBP quotes against PLN, the shop currency is %s - rates left at the commercial provider values.', strtoupper($base->code)));
        }

        $rates = app(ExchangeRateService::class)->rates($base->code);

        if ($rates === null && $nbp === null) {
            Log::error('Currency update: all rate providers failed', ['base' => $base->code]);
            $this->error('All exchange rate providers failed.');
            return self::FAILURE;
        }

        $updated = [];
        $missing = [];
        foreach ($others as $currency) {
            $code = strtoupper($currency->code);

            if ($nbp !== null && isset($nbp['rates'][$code])) {
                $currency->update(['rate' => round(1 / $nbp['rates'][$code], 5)]);
                $updated[] = $code;
                continue;
            }

            if ($rates === null || !isset($rates[$code]) || !is_numeric($rates[$code]) || $rates[$code] <= 0) {
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
        $official = match (true) {
            $provider === 'nbp' => $nbp !== null && $this->applyNbpProvenance($nbp),
            $provider === 'tcmb' => $this->applyOfficialLiraRate($base, $updated, $missing),
            $provider !== '' => $this->applyOperatorProviderRate($provider, $base, $updated, $missing),
            default => false,
        };

        // The lira rate now comes from the commercial provider: the bulletin
        // details of an earlier TCMB run no longer describe it, and invoices
        // kept printing them under a rate they did not come from.
        if (! $official) {
            foreach (['ExchangeRateSource', 'ExchangeRateKind', 'ExchangeRateDate', 'ExchangeRateBulletin', 'ExchangeRateCurrency'] as $key) {
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
        Setting::set('ExchangeRateCurrency', 'TRY');

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

    /**
     * Record the NBP provenance for the billing currency, when it is one NBP
     * publishes. The rates themselves are already NBP's (the loop above used
     * its table); a shop that bills in its own złoty has no foreign invoice to
     * stamp, so there is nothing to record and the caller clears the old
     * provenance.
     *
     * @param  array{date: string, no: string, rates: array<string, float>}  $table
     */
    private function applyNbpProvenance(array $table): bool
    {
        $billing = strtoupper(trim((string) Setting::get('BillingCurrency', '')));

        if ($billing === '' || $billing === 'PLN' || ! isset($table['rates'][$billing])) {
            return false;
        }

        Setting::set('ExchangeRateSource', 'NBP');
        Setting::set('ExchangeRateKind', 'reference');
        Setting::set('ExchangeRateDate', $table['date']);
        Setting::set('ExchangeRateBulletin', (string) $table['no']);
        Setting::set('ExchangeRateCurrency', $billing);

        $this->info(sprintf(
            '%s set from NBP (%s, table %s): 1 PLN = %s %s',
            $billing,
            $table['date'],
            $table['no'] !== '' ? $table['no'] : '-',
            number_format(1 / $table['rates'][$billing], 4),
            $billing
        ));

        return true;
    }

    /**
     * Overwrite one currency's rate with the one an operator-added provider
     * publishes (OfficialRateProviders), with its provenance, the way the
     * TCMB lira rate is applied above.
     *
     * Not fatal either: an unknown, unreachable or refused-as-stale provider
     * leaves the commercial rate in place and the invoice names no source.
     *
     * @param  list<string>  $updated
     * @param  list<string>  $missing
     * @return bool whether the currency's rate is now the official one
     */
    private function applyOperatorProviderRate(string $key, Currency $base, array &$updated, array &$missing): bool
    {
        $provider = OfficialRateProviders::find($key);

        if ($provider === null) {
            $this->warn("Official rate source \"$key\" is not in the provider list - rates left at the commercial provider values.");

            return false;
        }

        if ($provider['base'] !== strtoupper($base->code)) {
            $this->warn(sprintf('%s quotes against %s, the shop currency is %s - rates left at the commercial provider values.', $provider['name'], $provider['base'], strtoupper($base->code)));

            return false;
        }

        $code = (string) $provider['currency'];
        $currency = Currency::where('code', $code)->first();

        if (! $currency) {
            return false;
        }

        $official = app(OfficialRateProviders::class)->rateFor($provider);

        if (is_string($official)) {
            $this->warn(sprintf('%s: %s - %s rate left at the commercial provider value.', $provider['name'], $official, $code));

            return false;
        }

        $currency->update(['rate' => round($official['rate'], 5)]);

        Setting::set('ExchangeRateSource', $official['source']);
        Setting::set('ExchangeRateKind', $official['kind']);
        Setting::set('ExchangeRateDate', $official['date']);
        Setting::set('ExchangeRateBulletin', (string) $official['bulletin']);
        Setting::set('ExchangeRateCurrency', $code);

        if (! in_array($code, $updated, true)) {
            $updated[] = $code;
        }
        $missing = array_values(array_diff($missing, [$code]));

        $this->info(sprintf(
            '%s set from %s (%s, %s): 1 %s = %s %s',
            $code,
            $official['source'],
            $official['kind'],
            $official['date'],
            $provider['base'],
            number_format($official['rate'], 4),
            $code
        ));

        return true;
    }
}
