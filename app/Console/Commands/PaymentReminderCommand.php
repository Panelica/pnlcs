<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class PaymentReminderCommand extends Command
{
    protected $signature = 'pnlcs:payment-reminders';
    protected $description = 'Send payment reminder emails for upcoming and overdue invoices';

    public function handle(): int
    {
        $sent = 0;

        // Invoices due in 1, 3, 7 days
        foreach ([1, 3, 7] as $days) {
            $invoices = Invoice::with('client')
                ->whereIn('status', ['Unpaid', 'unpaid'])
                ->whereDate('due_date', now()->addDays($days)->toDateString())
                ->get();

            foreach ($invoices as $invoice) {
                if (!$invoice->client?->email) {
                    continue;
                }

                try {
                    Mail::to($invoice->client->email)->send(
                        new \App\Mail\PaymentReminderMail($invoice, $days)
                    );
                    $sent++;
                } catch (\Throwable $e) {
                    Log::error("Payment reminder failed for invoice #{$invoice->id}: {$e->getMessage()}");
                }
            }
        }

        // Overdue reminders (1, 3, 7 days past due)
        foreach ([1, 3, 7] as $days) {
            $invoices = Invoice::with('client')
                ->whereIn('status', ['Overdue', 'overdue'])
                ->whereDate('due_date', now()->subDays($days)->toDateString())
                ->get();

            foreach ($invoices as $invoice) {
                if (!$invoice->client?->email) {
                    continue;
                }

                try {
                    Mail::to($invoice->client->email)->send(
                        new \App\Mail\PaymentReminderMail($invoice, -$days)
                    );
                    $sent++;
                } catch (\Throwable $e) {
                    Log::error("Overdue reminder failed for invoice #{$invoice->id}: {$e->getMessage()}");
                }
            }
        }

        $this->info("Sent {$sent} payment reminder(s).");
        return Command::SUCCESS;
    }
}
