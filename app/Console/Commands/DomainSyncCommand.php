<?php

namespace App\Console\Commands;

use App\Contracts\RegistrarModuleInterface;
use App\Contracts\SyncsDomainData;
use App\Enums\DomainStatus;
use App\Models\Domain;
use App\Models\DomainPricing;
use App\Models\RegistrarSettings;
use App\Models\Setting;
use App\Services\DomainSyncChanges;
use App\Services\Module\ModuleRegistry;
use App\Services\RdapRegistrationDate;
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
 * The registry is authoritative for expiry_date and status. next_due_date
 * belongs to billing (InvoiceGenerationCommand scans domains.next_due_date),
 * and a due date that billing or an operator set apart from the expiry is
 * never touched: overwriting it here would silently re-bill or skip renewals.
 *
 * One case is not that. An order and every renewal write the two dates as
 * one - the domain falls due the day it expires - and for a transfer both are
 * only a guess, today plus the years ordered, until the registry is asked.
 * While the due date is still the expiry date, a corrected expiry takes the
 * due date with it (DomainSyncChanges). Left behind, a transfer the registry added no
 * time to was invoiced months after the domain had expired.
 */
class DomainSyncCommand extends Command
{
    protected $signature = 'pnlcs:domain-sync
        {--domain= : Sync a single domain name}
        {--dry-run : Report what would change without writing}';

    protected $description = 'Sync domain expiry, status and nameservers with the registrar';

    /**
     * The required settings a registrar module is missing.
     *
     * The module declares what it needs in getConfigFields(); this reads the
     * same list rather than knowing anything about a particular registrar.
     *
     * @return array<int, string>
     */
    private function missingRegistrarSettings(RegistrarModuleInterface $module): array
    {
        $stored = RegistrarSettings::where('registrar', $module->getModuleName())
            ->get()
            ->filter(fn ($row) => trim((string) $row->value) !== '')
            ->pluck('setting')
            ->all();

        $missing = [];

        foreach ($module->getConfigFields() as $field) {
            if (! ($field['required'] ?? false)) {
                continue;
            }

            if (! in_array($field['name'], $stored, true)) {
                $missing[] = $field['name'];
            }
        }

        return $missing;
    }

    public function handle(ModuleRegistry $registry): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $name = $this->option('domain');
        $batchSetting = Setting::get('DomainSyncBatchSize', '100');
        $batch = is_numeric($batchSetting) && (int) $batchSetting >= 1
            ? min(1000, (int) $batchSetting)
            : 100;

        $delaySetting = Setting::get('DomainSyncDelayMs', '500');
        $delayMs = is_numeric($delaySetting) ? max(0, min(10000, (int) $delaySetting)) : 500;

        // Only registrars with a sync implementation belong in a batch. Manual
        // and a module without SyncsDomainData would otherwise occupy a slot on
        // every run and never leave it.
        $syncable = [];
        foreach ($registry->getRegistrarModules() as $key) {
            if ($registry->getRegistrarModule($key) instanceof SyncsDomainData) {
                $syncable[] = strtolower((string) $key);
            }
        }

        $query = Domain::query()
            ->whereNotNull('registrar')
            ->where('registrar', '!=', '')
            ->whereNotIn('status', ['cancelled', 'transferred-away']);

        if ($name) {
            $query->where('domain', $name);
        } elseif ($syncable === []) {
            // Nothing to ask a registrar, but a domain whose expiry has passed
            // still has to be moved along, so the run goes on with an empty
            // batch instead of returning here.
            $query->whereRaw('1 = 0');
        } else {
            $query->where(function ($q) use ($syncable) {
                foreach ($syncable as $registrar) {
                    $q->orWhereRaw('LOWER(registrar) = ?', [$registrar]);
                }
            });

            // A domain is checked once a day at most. The 23-hour window leaves
            // room for the 03:00–06:00 schedule to drift by a few minutes.
            $query->where(fn ($q) => $q->whereNull('last_sync_at')
                ->orWhere('last_sync_at', '<=', now()->subHours(23)))
                ->orderBy('last_sync_at')
                ->orderBy('id')
                ->limit($batch);
        }

        $domains = $query->get();
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

            // A registrar nobody has given credentials to still gets called,
            // refuses, and leaves "lookup failed" against every domain - which
            // sends the operator looking for a network problem instead of the
            // setting they never filled in.
            $missing = $this->missingRegistrarSettings($module);

            if ($missing !== []) {
                $failed++;
                Log::warning("Domain sync skipped for {$domain->domain}: {$module->getModuleName()} is not configured (missing ".implode(', ', $missing).').');

                if (! $dryRun) {
                    $domain->update($this->syncStamp('error'));
                }

                continue;
            }

            try {
                $result = $module->syncDomain($domain);
            } catch (\Throwable $e) {
                $failed++;
                Log::warning("Domain sync failed for {$domain->domain}: {$e->getMessage()}");
                $this->warn("{$domain->domain}: {$e->getMessage()}");

                if (! $dryRun) {
                    $domain->update($this->syncStamp('error'));
                }

                $this->pause($delayMs);

                continue;
            }

            if (! ($result['success'] ?? false)) {
                $failed++;
                $message = $result['message'] ?? 'unknown error';
                Log::warning("Domain sync failed for {$domain->domain}: {$message}");
                $this->warn("{$domain->domain}: {$message}");

                // An active domain its registrar has never heard of is not a
                // sync hiccup: the customer paid and the name is not registered.
                // That used to leave one log line a day and nothing else.
                // Reported by ENA Hosting.
                if (! $dryRun) {
                    $domain->update($this->syncStamp('error'));
                }

                if (strtolower((string) $domain->status) === 'active' && self::unknownAtRegistrar((string) $message)) {
                    $this->alertMissingAtRegistrar($domain, (string) $message);
                }

                $this->pause($delayMs);

                continue;
            }

            $changes = DomainSyncChanges::between($domain, $result);
            $summary = $changes
                ? collect($changes)->map(fn ($v, $k) => "{$k}: ".$this->describe($domain->{$k}).' -> '.$this->describe($v))->implode(', ')
                : '';

            if ($dryRun) {
                if ($changes) {
                    $this->line("would update {$domain->domain} ({$summary})");
                }

                $this->pause($delayMs);

                continue;
            }

            $domain->update($changes + $this->syncStamp('ok'));

            if ($changes) {
                $updated++;
                Log::info("Domain sync updated {$domain->domain}: {$summary}");
            }

            $this->pause($delayMs);
        }

        $datesFilled = $this->fillMissingRegistrationDates($domains, $dryRun);

        $lapsed = $dryRun ? 0 : $this->applyLifecycle($this->lapsedDomains($name));

        $this->info("Domain sync: {$checked} checked, {$updated} updated, {$skipped} without a syncing module, {$failed} failed, {$datesFilled} registration dates filled from RDAP, {$lapsed} past expiry.");

        return Command::SUCCESS;
    }

    /**
     * The domains the lifecycle may move: every one whose expiry has passed,
     * not only the ones in this run's batch.
     *
     * Moving a lapsed domain along reads the TLD's grace and redemption
     * periods and writes a status. It calls no registrar, so there is nothing
     * to pace and no reason to tie it to the batch - and tying it to the batch
     * is what stopped it: the batch holds only registrars that can sync, so a
     * manual domain was never moved out of "active" again, and the renewal run
     * kept invoicing it.
     *
     * @return Collection<int, Domain>
     */
    private function lapsedDomains(?string $name)
    {
        return Domain::query()
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<', now()->startOfDay())
            ->whereNotIn('status', ['cancelled', 'transferred-away'])
            ->when($name, fn ($q) => $q->where('domain', $name))
            ->orderBy('id')
            ->get();
    }

    /** The visible record of a nightly sync attempt, successful or not. */
    private function syncStamp(string $status): array
    {
        return [
            'last_sync_at' => now(),
            'last_sync_status' => $status,
        ];
    }

    /** Pace registrar calls; tests do not need to wait for the wall clock. */
    private function pause(int $milliseconds): void
    {
        if ($milliseconds > 0 && ! app()->runningUnitTests()) {
            usleep($milliseconds * 1000);
        }
    }

    /**
     * Fill a missing registration date from RDAP, once, for every domain the
     * sync runs over. The date is immutable, so once it is stored it is never
     * asked for again; the setting caps how many lookups one run makes so a
     * first run over a long list does not hammer the registry.
     *
     * @param  Collection<int, Domain>  $domains
     */
    private function fillMissingRegistrationDates(Collection $domains, bool $dryRun): int
    {
        $limit = max(0, (int) Setting::get('DomainSyncRdapMaxPerRun', '100'));
        if ($limit === 0) {
            return 0;
        }

        $rdap = app(RdapRegistrationDate::class);
        $filled = 0;

        foreach ($domains as $domain) {
            if ($filled >= $limit) {
                break;
            }

            $date = $rdap->forMissing($domain);
            if (! $date) {
                continue;
            }

            $filled++;

            if ($dryRun) {
                $this->line("would set registration_date {$date} for {$domain->domain} (RDAP)");

                continue;
            }

            $domain->update(['registration_date' => $date]);
            Log::info("Domain {$domain->domain} registration date filled from RDAP: {$date}");
        }

        return $filled;
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
            event(new \App\Events\DomainExpired($domain, $status));
            $moved++;
            Log::info("Domain {$domain->domain} moved to {$status} ({$daysPast} days past expiry)");
        }

        return $moved;
    }

    private function describe(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '(empty)';
        }
        if (is_bool($value)) {
            return $value ? 'on' : 'off';
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return (string) $value;
    }

    /** Registrars word "no such domain" in several ways; these are the ones seen. */
    private static function unknownAtRegistrar(string $message): bool
    {
        return (bool) preg_match('/could not be found|not be found|not found|no such domain|does not exist/i', $message);
    }

    private function alertMissingAtRegistrar(Domain $domain, string $message): void
    {
        // The sync runs every day and the domain stays missing until somebody
        // acts: once a week is a reminder, every day is noise people mute.
        if (! \Illuminate\Support\Facades\Cache::add('domain-missing-at-registrar:'.$domain->id, true, now()->addDays(7))) {
            return;
        }

        try {
            app(\App\Services\NotificationService::class)->dispatch('domain.registration_failed', [
                'event_type' => 'domain.registration_failed',
                'subject' => 'Domain is active here but missing at the registrar',
                'message' => "{$domain->domain} is active in PNLCS, but {$domain->registrar} does not know it: \"{$message}\". "
                    .'If the customer has paid, the domain is not registered and needs attention.',
                'domain_id' => $domain->id,
            ]);
        } catch (\Throwable $e) {
            Log::error('Domain sync alert failed: '.$e->getMessage());
        }
    }
}
