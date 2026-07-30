<?php

namespace App\Console\Commands;

use App\Contracts\SyncsDomainData;
use App\Enums\DomainStatus;
use App\Models\Domain;
use App\Models\DomainPricing;
use App\Services\Module\ModuleRegistry;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Pulls authoritative domain state (expiry, status, lock, nameservers) back
 * from the registrar. This command used to be a stub that counted domains,
 * printed "(Module integration required)" and changed nothing, so a domain
 * renewed or expired at the registry was never reflected in the panel.
 *
 * The registry is authoritative for expiry_date and status. next_due_date is
 * deliberately NOT touched: billing owns it (InvoiceGenerationCommand scans
 * domains.next_due_date), and overwriting it here would silently re-bill or
 * skip renewals.
 */
class DomainSyncCommand extends Command
{
    protected $signature = 'pnlcs:domain-sync
        {--domain= : Sync a single domain name}
        {--dry-run : Report what would change without writing}';

    protected $description = 'Sync domain expiry, status and nameservers with the registrar';

    public function handle(ModuleRegistry $registry): int
    {
        $query = Domain::query()
            ->whereNotNull('registrar')
            ->where('registrar', '!=', '')
            ->whereNotIn('status', ['cancelled', 'transferred-away']);

        if ($name = $this->option('domain')) {
            $query->where('domain', $name);
        }

        $domains = $query->orderBy('id')->get();
        if ($domains->isEmpty()) {
            $this->info('Domain sync: nothing to do.');

            return Command::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $checked = $updated = $skipped = $failed = 0;

        foreach ($domains as $domain) {
            $module = $registry->getRegistrarModule((string) $domain->registrar);

            if (! $module instanceof SyncsDomainData) {
                // Manual (and any third-party module without the capability)
                // has no registry to read from.
                $skipped++;

                continue;
            }

            $checked++;

            try {
                $result = $module->syncDomain($domain);
            } catch (\Throwable $e) {
                $failed++;
                Log::warning("Domain sync failed for {$domain->domain}: {$e->getMessage()}");
                $this->warn("{$domain->domain}: {$e->getMessage()}");

                continue;
            }

            if (! ($result['success'] ?? false)) {
                $failed++;
                $message = $result['message'] ?? 'unknown error';
                Log::warning("Domain sync failed for {$domain->domain}: {$message}");
                $this->warn("{$domain->domain}: {$message}");

                continue;
            }

            $changes = $this->diff($domain, $result);
            if (! $changes) {
                continue;
            }

            $summary = collect($changes)->map(fn ($v, $k) => "{$k}: ".$this->describe($domain->{$k}).' -> '.$this->describe($v))->implode(', ');

            if ($dryRun) {
                $this->line("would update {$domain->domain} ({$summary})");

                continue;
            }

            $domain->update($changes);
            $updated++;
            Log::info("Domain sync updated {$domain->domain}: {$summary}");
        }

        $lapsed = $dryRun ? 0 : $this->applyLifecycle($domains);

        $this->info("Domain sync: {$checked} checked, {$updated} updated, {$skipped} without a syncing module, {$failed} failed, {$lapsed} past expiry.");

        return Command::SUCCESS;
    }

    /**
     * Move a domain along once its expiry date has passed.
     *
     * Grace first, where the registry still renews at the ordinary price and
     * the customer is still invoiced; then redemption, which costs a
     * restoration fee and so is not billed automatically; then expired. The
     * lengths come from the TLD, where the operator set them and nothing had
     * ever read them.
     *
     * @param  Collection<int, Domain>  $domains
     */
    private function applyLifecycle($domains): int
    {
        $periods = DomainPricing::all()->keyBy(fn ($row) => strtolower(ltrim((string) $row->extension, '.')));
        $today = now()->startOfDay();
        $moved = 0;

        foreach ($domains as $domain) {
            if (! $domain->expiry_date) {
                continue;
            }

            // A registrar that reports its own lifecycle status is believed.
            if (! in_array(strtolower((string) $domain->status), ['active', 'grace', 'redemption'], true)) {
                continue;
            }

            $expiry = Carbon::parse($domain->expiry_date)->startOfDay();

            if ($expiry->greaterThanOrEqualTo($today)) {
                continue;
            }

            $tld = strtolower(ltrim(substr((string) $domain->domain, strpos((string) $domain->domain, '.') ?: 0), '.'));
            $row = $periods->get($tld);

            $grace = max(0, (int) ($row->grace_period ?? 0));
            $redemption = max(0, (int) ($row->redemption_grace_period ?? 0));

            $daysPast = $expiry->diffInDays($today);

            $status = match (true) {
                $daysPast <= $grace => DomainStatus::Grace->value,
                $daysPast <= $grace + $redemption => DomainStatus::Redemption->value,
                default => DomainStatus::Expired->value,
            };

            if (strtolower((string) $domain->status) === $status) {
                continue;
            }

            $domain->update(['status' => $status]);
            $moved++;
            Log::info("Domain {$domain->domain} moved to {$status} ({$daysPast} days past expiry)");
        }

        return $moved;
    }

    /**
     * Only report genuine differences so an unchanged domain is never written.
     *
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function diff(Domain $domain, array $result): array
    {
        $changes = [];

        $expiry = $result['expiry_date'] ?? null;
        if ($expiry && optional($domain->expiry_date)->toDateString() !== $expiry) {
            $changes['expiry_date'] = $expiry;
        }

        $status = $result['status'] ?? null;
        if ($status && strtolower((string) $domain->status) !== strtolower($status)) {
            $changes['status'] = $status;
        }

        $nameservers = $result['nameservers'] ?? [];
        if ($nameservers) {
            $encoded = json_encode(array_values($nameservers));
            if ($domain->nameservers !== $encoded) {
                $changes['nameservers'] = $encoded;
            }
        }

        return $changes;
    }

    private function describe(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '(empty)';
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return (string) $value;
    }
}
