<?php

namespace Modules\Addons\BankPayments\Console;

use App\Services\AddonManager;
use Illuminate\Console\Command;
use Modules\Addons\BankPayments\Models\BankTransaction;
use Modules\Addons\BankPayments\Services\InvoiceMatcher;

class RematchBankPaymentsCommand extends Command
{
    protected $signature = 'pnlcs:bank-payments:rematch
        {--transaction= : Rematch one transaction id}
        {--limit=200 : Maximum transactions to rematch}';

    protected $description = 'Recompute invoice candidates for bank transactions that are still waiting';

    public function handle(AddonManager $addons, InvoiceMatcher $matcher): int
    {
        if (! $addons->isActive('bank_payments')) {
            $this->warn(__('bank-payments::messages.addon_inactive'));

            return self::SUCCESS;
        }

        $statuses = ['pending', 'unmatched', 'manual_currency', 'error'];

        $query = BankTransaction::query()
            ->whereIn('matching_status', $statuses)
            ->when($this->option('transaction') !== null, fn ($query) => $query->whereKey((int) $this->option('transaction')))
            ->orderBy('id');

        $transactions = $query->limit(max(1, (int) $this->option('limit')))->get();

        if ($transactions->isEmpty()) {
            $this->info('No transactions need rematching.');

            return self::SUCCESS;
        }

        foreach ($transactions as $transaction) {
            $result = $matcher->match($transaction);

            $this->line(sprintf(
                '#%d: %s (%d%%)',
                $transaction->id,
                $result['status'],
                (int) round($result['score'] / InvoiceMatcher::MAX_SCORE * 100),
            ));
        }

        return self::SUCCESS;
    }
}
