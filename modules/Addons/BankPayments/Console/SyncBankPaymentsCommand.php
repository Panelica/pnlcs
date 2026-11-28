<?php

namespace Modules\Addons\BankPayments\Console;

use App\Services\AddonManager;
use Illuminate\Console\Command;
use Modules\Addons\BankPayments\Models\BankMailbox;
use Modules\Addons\BankPayments\Services\BankBookingSchedule;
use Modules\Addons\BankPayments\Services\BankPaymentsSettings;
use Modules\Addons\BankPayments\Services\BankSyncService;

class SyncBankPaymentsCommand extends Command
{
    protected $signature = 'pnlcs:bank-payments:sync
        {--mailbox= : Mailbox id or name; omit to sync every active mailbox}
        {--days= : First-sync history limit in days}
        {--limit= : Maximum messages read in this run}
        {--force : Ignore the bank booking window and sync now}';

    protected $description = 'Read active bank mailboxes, parse transfers and match open invoices';

    public function handle(AddonManager $addons, BankSyncService $sync, BankBookingSchedule $schedule): int
    {
        if (! $addons->isActive('bank_payments')) {
            $this->warn(__('bank-payments::messages.addon_inactive'));

            return self::SUCCESS;
        }

        $mailboxes = BankMailbox::query()
            ->where('active', true)
            ->when($this->option('mailbox'), function ($query, string $mailbox): void {
                if (ctype_digit($mailbox)) {
                    $query->whereKey((int) $mailbox);

                    return;
                }

                $query->where('name', $mailbox);
            })
            ->orderBy('id')
            ->get();

        if ($mailboxes->isEmpty()) {
            $this->info(__('bank-payments::messages.no_active_mailboxes'));

            return self::SUCCESS;
        }

        $days = $this->option('days') !== null ? (int) $this->option('days') : null;
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;
        $failed = false;

        $force = (bool) $this->option('force');
        $mode = BankPaymentsSettings::mode();

        foreach ($mailboxes as $mailbox) {
            if (! $force && $mode !== 'test' && ! $schedule->isSyncWindowOpen($mailbox->bank)) {
                if ($this->output->isVerbose()) {
                    $this->line($mailbox->name.': skipped outside the bank booking window');
                }

                continue;
            }

            $result = $sync->sync($mailbox, $days, $limit);

            $this->line(sprintf(
                '%s: %s (fetched %d, parsed %d, new %d, duplicates %d, errors %d)',
                $mailbox->name,
                $result['status'],
                $result['fetched'],
                $result['parsed'],
                $result['new_transactions'],
                $result['duplicates'],
                $result['errors'],
            ));

            if ($result['status'] === 'error') {
                $failed = true;
                $this->error($mailbox->name.': '.$result['message']);
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
