<?php

namespace App\Console\Commands;

use App\Models\InvoiceItem;
use Illuminate\Console\Command;

/**
 * Rewrites the raw English cycle in already issued, still-open invoice lines
 * ("Hosting (monthly)") to the document's language ("Hosting (Miesięcznie)").
 *
 * New lines are written translated by the generators. This command exists for
 * the documents issued before that, and deliberately leaves settled invoices
 * alone: their descriptions are part of the historical document.
 */
class TranslateInvoiceCyclesCommand extends Command
{
    protected $signature = 'pnlcs:translate-invoice-cycles {--dry-run : Report changes without saving them}';

    protected $description = 'Translate billing cycles in the descriptions of open invoice lines';

    /** A raw cycle inside parentheses: (monthly), (semi-annually), (7months)… */
    private const CYCLE_PATTERN = '/\(\s*(monthly|quarterly|semi[-_\s]?annually|annually|biennially|triennially|one[-_\s]?time|free|\d{1,2}\s*months?)\s*\)/i';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $scanned = 0;
        $changed = 0;

        InvoiceItem::query()
            ->with('invoice.client')
            ->whereIn('type', ['Hosting', 'Addon', 'Upgrade'])
            ->whereHas('invoice', fn ($q) => $q->whereIn('status', ['unpaid', 'overdue', 'draft']))
            ->orderBy('id')
            ->chunkById(200, function ($items) use ($dryRun, &$scanned, &$changed) {
                foreach ($items as $item) {
                    $scanned++;
                    $description = (string) $item->description;
                    $locale = $item->invoice?->language
                        ?: $item->invoice?->client?->language
                        ?: app()->getLocale();

                    $new = preg_replace_callback(
                        self::CYCLE_PATTERN,
                        function (array $match) use ($locale) {
                            $label = cycle_label($match[1], $locale);

                            return $label !== '' ? '('.$label.')' : $match[0];
                        },
                        $description,
                        1,
                        $count
                    );

                    if ($count === 0 || $new === null || $new === $description) {
                        continue;
                    }

                    $changed++;

                    if (! $dryRun) {
                        $item->update(['description' => $new]);
                    }
                }
            });

        $this->info(($dryRun ? 'Would update ' : 'Updated ').$changed.' of '.$scanned.' open invoice line(s).');

        return Command::SUCCESS;
    }
}
