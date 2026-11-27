<?php

namespace Modules\Addons\BankPayments\Console;

use App\Services\AddonManager;
use Illuminate\Console\Command;
use Modules\Addons\BankPayments\Models\BankMailbox;
use Modules\Addons\BankPayments\Services\BankSyncService;

class SyncBankPaymentsCommand extends Command
{
    protected $signature = 'pnlcs:bank-payments:sync
        {--mailbox= : Mailbox id or name; omit to sync every active mailbox}
        {--days= : First-sync history limit in days}
        {--limit= : Maximum messages read in this run}';

    protected $description = 'Read active bank mailboxes and store their transactions in test mode';

    public function handle(AddonManager $addons, BankSyncService $sync): int
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

        foreach ($mailboxes as $mailbox) {
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
