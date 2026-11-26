<?php

namespace App\Console\Commands;

use App\Models\Domain;
use App\Models\Invoice;
use App\Models\Service;
use App\Models\ServiceAddon;
use App\Services\BillingCycleChange;
use App\Services\BillingCycleHelper;
use App\Services\InvoiceLineDescription;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Rewrite the line descriptions of open proformas to the current
 * InvoiceLinePeriodDisplay setting and the inclusive period rule.
 *
 * Proformas raised before those changes still carry "(Miesięcznie)" and
 * "2026/12/01 - 2027/01/01". This command keeps the product name and domain
 * as they were written; only the cycle/period suffix is rebuilt. Amounts,
 * invoice dates, due dates and numbers are untouched.
 */
class RefreshProformaLinesCommand extends Command
{
    protected $signature = 'pnlcs:refresh-proforma-lines {--dry-run : Report changes without saving them}';

    protected $description = 'Rebuild open proforma line descriptions from the current period setting';

    private const PERIOD_PATTERN = '/\s*\d{4}\/\d{2}\/\d{2}\s*-\s*\d{4}\/\d{2}\/\d{2}\s*$/u';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $mode = app(InvoiceLineDescription::class)->mode();
        $scanned = 0;
        $changed = 0;

        Invoice::query()
            ->where('type', 'proforma')
            ->where('status', 'unpaid')
            ->with('items')
            ->orderBy('id')
            ->chunkById(100, function ($invoices) use ($dryRun, $mode, &$scanned, &$changed) {
                foreach ($invoices as $invoice) {
                    $locale = $invoice->language
                        ?: $invoice->client?->language
                        ?: app()->getLocale();

                    foreach ($invoice->items as $item) {
                        $scanned++;
                        $description = (string) $item->description;
                        $new = $this->rebuild($item, $description, $mode, $locale);

                        if ($new === null || $new === $description) {
                            continue;
                        }

                        $changed++;

                        if ($dryRun) {
                            $this->line("#{$invoice->invoice_num} / item {$item->id}:");
                            $this->line('  - '.$description);
                            $this->line('  + '.$new);
                        } else {
                            $item->update(['description' => $new]);
                        }
                    }
                }
            });

        $this->info(($dryRun ? 'Would update ' : 'Updated ').$changed.' of '.$scanned.' open proforma line(s).');

        return Command::SUCCESS;
    }

    private function rebuild(\App\Models\InvoiceItem $item, string $description, string $mode, string $locale): ?string
    {
        $start = $item->due_date
            ? Carbon::parse($item->due_date)->startOfDay()
            : $this->periodStart($description);

        if (! $start) {
            return null;
        }

        $type = (string) $item->type;
        $cycle = $type === 'Domain'
            ? null
            : $this->cycleFor($item, $description, $locale);
        $years = $type === 'Domain' ? $this->domainYears($item, $description, $locale) : null;

        $base = preg_replace(self::PERIOD_PATTERN, '', $description) ?? $description;
        $base = trim($base);

        if ($mode === InvoiceLineDescription::PERIOD) {
            $base = $this->stripCycleLabels($base, $locale);
        }

        if ($mode === InvoiceLineDescription::PERIOD) {
            $base = $this->stripDomainYears($base, $locale);
        }

        // A description with an empty domain was written as "Name —  —".
        $base = preg_replace('/\s*—\s*—\s*/u', ' — ', $base) ?? $base;
        $base = preg_replace('/\s*—\s*$/u', '', $base) ?? $base;
        $base = trim($base);

        if ($base === '') {
            return null;
        }

        if ($mode === InvoiceLineDescription::CYCLE) {
            return $base;
        }

        $end = $type === 'Domain'
            ? $start->copy()->addYearsNoOverflow($years)->subDay()
            : BillingCycleHelper::advance($start, $cycle)->subDay();

        return $base.' — '.$start->format('Y/m/d').' - '.$end->format('Y/m/d');
    }

    private function periodStart(string $description): ?Carbon
    {
        if (preg_match('/(\d{4})\/(\d{2})\/(\d{2})\s*-\s*\d{4}\/\d{2}\/\d{2}/u', $description, $m)) {
            return Carbon::createFromDate((int) $m[1], (int) $m[2], (int) $m[3])->startOfDay();
        }

        return null;
    }

    private function cycleFor(\App\Models\InvoiceItem $item, string $description, string $locale): string
    {
        $cycle = match ((string) $item->type) {
            'Hosting' => Service::find($item->rel_id)?->billing_cycle,
            'Addon' => ServiceAddon::find($item->rel_id)?->billing_cycle,
            default => null,
        };

        return $cycle ?: $this->cycleFromDescription($description, $locale) ?: 'Monthly';
    }

    private function cycleFromDescription(string $description, string $locale): ?string
    {
        foreach (array_values(BillingCycleChange::CYCLES) as $cycle) {
            $label = cycle_label($cycle, $locale);

            if ($label !== '' && str_contains($description, '('.$label.')')) {
                return $cycle;
            }
        }

        return null;
    }

    private function domainYears(\App\Models\InvoiceItem $item, string $description, string $locale): int
    {
        $years = (int) (Domain::find($item->rel_id)?->registration_period ?? 0);

        if ($years > 0) {
            return $years;
        }

        for ($n = 1; $n <= 12; $n++) {
            $label = trans_choice('client.invoices.domain_years', $n, ['count' => $n], $locale);

            if ($label !== '' && str_contains($description, '('.$label.')')) {
                return $n;
            }
        }

        return 1;
    }

    private function stripCycleLabels(string $text, string $locale): string
    {
        foreach (array_values(BillingCycleChange::CYCLES) as $cycle) {
            $label = cycle_label($cycle, $locale);

            if ($label !== '') {
                $text = preg_replace('/\s*\(\s*'.preg_quote($label, '/').'\s*\)/u', '', $text) ?? $text;
            }
        }

        return $text;
    }

    private function stripDomainYears(string $text, string $locale): string
    {
        for ($n = 1; $n <= 12; $n++) {
            $label = trans_choice('client.invoices.domain_years', $n, ['count' => $n], $locale);

            if ($label !== '') {
                $text = preg_replace('/\s*\(\s*'.preg_quote($label, '/').'\s*\)/u', '', $text) ?? $text;
            }
        }

        return $text;
    }
}
