<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Setting;
use Illuminate\Console\Command;

class LateFeeCommand extends Command
{
    protected $signature = 'pnlcs:apply-late-fees';
    protected $description = 'Apply late fees to overdue invoices';

    public function handle(): int
    {
        $lateFeeType = Setting::get('LateFeeType', 'none'); // none, flat, percent
        $lateFeeAmount = (float) Setting::get('LateFeeAmount', 0);
        $lateFeeMinDays = (int) Setting::get('LateFeeMinDays', 3);

        if ($lateFeeType === 'none' || $lateFeeAmount <= 0) {
            $this->info('Late fees are disabled.');
            return Command::SUCCESS;
        }

        $invoices = Invoice::with('items')
            ->where('status', 'overdue')
            ->where('due_date', '<=', now()->subDays($lateFeeMinDays))
            // A payment invoice for several invoices carries no fee of its
            // own: the invoices it lists already do.
            ->where('type', '!=', \App\Services\MassPaymentService::TYPE)
            ->get();

        $applied = 0;

        foreach ($invoices as $invoice) {
            // Check if late fee already applied
            $hasLateFee = $invoice->items->contains(fn ($item) => $item->type === 'LateFee');
            if ($hasLateFee) {
                continue;
            }

            // A percentage of what the lines add up to. With prices that
            // include tax the subtotal has the tax taken out, so a fee on it
            // came to less than the same percentage of the invoice; with tax
            // added on top the two are the same figure.
            $linesAmount = (float) $invoice->items->sum(fn ($item) => (float) $item->amount * (int) $item->qty);

            $fee = $lateFeeType === 'percent'
                ? round($linesAmount * ($lateFeeAmount / 100), 2)
                : $lateFeeAmount;

            if ($fee <= 0) {
                continue;
            }

            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'client_id' => $invoice->client_id,
                'type' => 'LateFee',
                'description' => "Late Fee ({$lateFeeMinDays}+ days overdue)",
                'amount' => $fee,
                'taxed' => false,
            ]);

            $invoice->update([
                'subtotal' => (float) $invoice->subtotal + $fee,
                'total' => (float) $invoice->total + $fee,
            ]);

            $applied++;
        }

        $this->info("Applied late fees to {$applied} invoice(s).");
        return Command::SUCCESS;
    }
}
