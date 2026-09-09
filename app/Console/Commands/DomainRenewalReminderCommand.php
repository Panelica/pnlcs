<?php

namespace App\Console\Commands;

use App\Mail\DomainRenewalReminderMail;
use App\Models\Domain;
use App\Models\DomainPricing;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class DomainRenewalReminderCommand extends Command
{
    protected $signature = 'pnlcs:domain-renewal-reminders {--dry-run : List what would be sent without sending}';

    protected $description = 'Warn customers before their domains expire, and again during the grace period';

    /**
     * The points at which a customer hears from us, furthest away first.
     *
     * The 90 day notice is what the İnternet Alan Adları Yönetmeliği requires
     * of a registrar for .tr — at least three months' warning, by email at
     * minimum. It is sent for every extension rather than only .tr so that one
     * rule covers the whole book and the obligation is provably met.
     *
     * Each send is recorded on the domain, which is what makes the notice
     * evidence: "we told you on this date" rather than an assertion.
     *
     * The stages after expiry matter more than the ones before it. A gTLD in
     * redemption costs a restore fee to recover; a .tr is released outright
     * once its two month suspension ends, and no fee brings it back.
     *
     * @var array<int, array{key: string, days: int, before: bool}>
     */
    private const STAGES = [
        ['key' => 'before90', 'days' => 90, 'before' => true],
        ['key' => 'before15', 'days' => 15, 'before' => true],
        ['key' => 'before1',  'days' => 1,  'before' => true],
        ['key' => 'after1',   'days' => 1,  'before' => false],
        ['key' => 'after15',  'days' => 15, 'before' => false],
        ['key' => 'after45',  'days' => 45, 'before' => false],
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $domains = Domain::with('client')
            ->whereNotNull('expiry_date')
            ->whereNotIn('status', ['cancelled', 'terminated', 'transferred_away'])
            ->get();

        $sent = 0;
        $today = now()->startOfDay();

        foreach ($domains as $domain) {
            $stage = $this->stageFor($domain, $today);

            // Nothing new to say: either too early, or this stage already went
            // out. Matching on the stage rather than the day means a run that
            // is skipped for a week still catches up instead of losing the
            // notice for good.
            if (! $stage || $stage['key'] === $domain->renewal_reminder_stage) {
                continue;
            }

            // Past the point where the domain can still be recovered there is
            // nothing to tell the customer to do. The window is the grace
            // period plus redemption where the registry offers one; .tr has no
            // redemption, so its window closes hard at the end of the two month
            // suspension and the name is gone.
            $daysSinceExpiry = -((int) $today->diffInDays(
                Carbon::parse($domain->expiry_date)->startOfDay(), false
            ));

            if ($daysSinceExpiry > $this->recoveryWindowFor($domain)) {
                continue;
            }

            if (! $domain->client?->email) {
                continue;
            }

            $days = $stage['before'] ? $stage['days'] : -$stage['days'];

            if ($dryRun) {
                $this->line("{$domain->domain} -> {$stage['key']} ({$domain->client->email})");
                $sent++;

                continue;
            }

            try {
                Mail::to($domain->client->email)->queue(new DomainRenewalReminderMail($domain, $days));
                $domain->update([
                    'renewal_reminder_stage'   => $stage['key'],
                    'renewal_reminder_sent_at' => now(),
                ]);
                $sent++;
            } catch (\Throwable $e) {
                Log::error("Domain renewal reminder failed for {$domain->domain}: {$e->getMessage()}");
            }
        }

        $this->info($dryRun
            ? "Would send {$sent} renewal reminder(s)."
            : "Sent {$sent} renewal reminder(s).");

        return Command::SUCCESS;
    }

    /**
     * Days after expiry during which the domain can still be recovered: the
     * grace period, plus redemption where the registry offers one. Falls back
     * to the shortest grace any gTLD gives, so an unknown extension stops
     * nagging early rather than promising time it may not have.
     */
    private function recoveryWindowFor(Domain $domain): int
    {
        $tld = strstr((string) $domain->domain, '.');
        if ($tld === false) {
            return 20;
        }

        $pricing = DomainPricing::where('extension', $tld)->first();
        if (! $pricing) {
            return 20;
        }

        return (int) $pricing->grace_period + (int) $pricing->redemption_grace_period;
    }

    /**
     * The furthest stage this domain has reached, or null if it is too early.
     *
     * @return array{key: string, days: int, before: bool}|null
     */
    private function stageFor(Domain $domain, Carbon $today): ?array
    {
        $expiry = Carbon::parse($domain->expiry_date)->startOfDay();
        $daysUntil = (int) $today->diffInDays($expiry, false);

        $reached = null;

        foreach (self::STAGES as $stage) {
            $hit = $stage['before']
                ? ($daysUntil >= 0 && $daysUntil <= $stage['days'])
                : ($daysUntil <= -$stage['days']);

            if ($hit) {
                $reached = $stage;
            }
        }

        return $reached;
    }
}
