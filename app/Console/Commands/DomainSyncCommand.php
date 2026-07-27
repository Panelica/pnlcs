<?php

namespace App\Console\Commands;

use App\Contracts\SyncsDomainData;
use App\Models\Domain;
use App\Services\Module\ModuleRegistry;
use Illuminate\Console\Command;
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

        $this->info("Domain sync: {$checked} checked, {$updated} updated, {$skipped} without a syncing module, {$failed} failed.");

        return Command::SUCCESS;
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
