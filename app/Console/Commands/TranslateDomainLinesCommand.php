<?php

namespace App\Console\Commands;

use App\Models\InvoiceItem;
use Illuminate\Console\Command;

/**
 * Rewrites the English domain wording in already issued invoice lines
 * ("Domain Registration: example.test — 1 Year(s)") to the document's
 * language.
 *
 * New lines are written translated by InvoiceLineDescription. This command
 * exists for the documents issued before that. It only changes the label and
 * the year count; the domain, period, amounts and invoice numbers stay as
 * they were. Settled invoices are included only when --include-paid is given,
 * because their descriptions are part of the historical document.
 */
class TranslateDomainLinesCommand extends Command
{
    protected $signature = 'pnlcs:translate-domain-lines
        {--dry-run : Report changes without saving them}
        {--include-paid : Also update paid invoices}';

    protected $description = 'Translate legacy English domain lines into the invoice language';

    /** The old generated prefix and the domain it names. */
    private const PREFIX_PATTERN = '/^\s*Domain\s+(Registration|Renewal|Transfer)\s*:\s*(?<domain>[^\s—]+)(?<rest>.*)$/iu';

    /** "1 Year(s)" in the first domain-order format. */
    private const LONG_YEARS_PATTERN = '/\b(\d{1,2})\s+Year\(s\)/i';

    /** "(1y)" in the first domain-renewal format. */
    private const SHORT_YEARS_PATTERN = '/\((\d{1,2})y\)/i';

    private const WHOIS_PATTERN = '/\+\s*WHOIS privacy\b/i';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $statuses = ['unpaid', 'overdue', 'draft'];

        if ($this->option('include-paid')) {
            $statuses[] = 'paid';
        }

        $scanned = 0;
        $changed = 0;

        InvoiceItem::query()
            ->with('invoice.client')
            ->where('type', 'Domain')
            ->whereHas('invoice', fn ($q) => $q->whereIn('status', $statuses))
            ->orderBy('id')
            ->chunkById(200, function ($items) use ($dryRun, &$scanned, &$changed) {
                foreach ($items as $item) {
                    $scanned++;
                    $description = (string) $item->description;
                    $locale = $item->invoice?->language
                        ?: $item->invoice?->client?->language
                        ?: app()->getLocale();

                    $new = $this->translate($description, $locale);

                    if ($new === null || $new === $description) {
                        continue;
                    }

                    $changed++;

                    if (! $dryRun) {
                        $item->update(['description' => $new]);
                    }
                }
            });

        $this->info(($dryRun ? 'Would update ' : 'Updated ').$changed.' of '.$scanned.' domain invoice line(s).');

        return Command::SUCCESS;
    }

    /**
     * Translate one old line, or return null when it is not in the legacy
     * English format. Keeping this pure makes dry-run and the real run use
     * exactly the same text.
     */
    private function translate(string $description, string $locale): ?string
    {
        if (! preg_match(self::PREFIX_PATTERN, $description, $match)) {
            return null;
        }

        $key = match (strtolower($match[1])) {
            'registration' => 'client.invoices.domain_registration_line',
            'renewal' => 'client.invoices.domain_renewal_line',
            'transfer' => 'client.invoices.domain_transfer_line',
        };

        $new = __($key, ['domain' => $match['domain']], $locale).$match['rest'];

        $new = preg_replace_callback(
            self::LONG_YEARS_PATTERN,
            fn (array $m) => trans_choice('client.invoices.domain_years', (int) $m[1], ['count' => (int) $m[1]], $locale),
            $new
        ) ?? $new;

        $new = preg_replace_callback(
            self::SHORT_YEARS_PATTERN,
            fn (array $m) => '('.trans_choice('client.invoices.domain_years', (int) $m[1], ['count' => (int) $m[1]], $locale).')',
            $new
        ) ?? $new;

        return preg_replace(
            self::WHOIS_PATTERN,
            '+ '.__('client.cart.privacy_label', [], $locale),
            $new
        ) ?? $new;
    }
}
