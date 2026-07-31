<?php

namespace App\Console\Commands;

use App\Enums\InvoiceStatus;
use App\Mail\PaymentReminderMail;
use App\Models\Invoice;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class PaymentReminderCommand extends Command
{
    protected $signature = 'pnlcs:payment-reminders';

    protected $description = 'Remind customers about invoices coming due and invoices already past due';

    /**
     * The points at which a customer hears from us, furthest away first.
     *
     * Reminders used to be sent by matching the due date to an exact day, so a
     * run that missed a day skipped that reminder for good — and nothing at all
     * went out once an invoice was more than a week overdue. Each invoice now
     * carries the last stage it was told about, so a late run still catches up
     * and nothing is sent twice.
     *
     * @var array<int, array{key: string, days: int, before: bool}>
     */
    private const STAGES = [
        ['key' => 'due7', 'days' => 7, 'before' => true],
        ['key' => 'due3', 'days' => 3, 'before' => true],
        ['key' => 'due1', 'days' => 1, 'before' => true],
        ['key' => 'late1', 'days' => 1, 'before' => false],
        ['key' => 'late3', 'days' => 3, 'before' => false],
        ['key' => 'late7', 'days' => 7, 'before' => false],
        ['key' => 'late30', 'days' => 30, 'before' => false],
    ];

    public function handle(): int
    {
        $invoices = Invoice::with('client')
            ->whereIn('status', [InvoiceStatus::Unpaid->value, InvoiceStatus::Overdue->value])
            ->whereNotNull('due_date')
            ->get();

        $sent = 0;
        $today = now()->startOfDay();

        foreach ($invoices as $invoice) {
            $stage = $this->stageFor($invoice, $today);

            if (! $stage || $stage['key'] === $invoice->reminder_stage) {
                continue;
            }

            if (! $invoice->client?->email) {
                continue;
            }

            $days = $stage['before'] ? $stage['days'] : -$stage['days'];

            try {
                Mail::to($invoice->client->email)->queue(new PaymentReminderMail($invoice, $days));
                $invoice->update(['reminder_stage' => $stage['key'], 'reminder_sent_at' => now()]);
                $sent++;
            } catch (\Throwable $e) {
                Log::error("Payment reminder failed for invoice #{$invoice->id}: {$e->getMessage()}");
            }
        }

        $this->info("Sent {$sent} payment reminder(s).");

        return Command::SUCCESS;
    }

    /**
     * The furthest stage this invoice has reached, or null if it is too early.
     *
     * @return array{key: string, days: int, before: bool}|null
     */
    private function stageFor(Invoice $invoice, Carbon $today): ?array
    {
        $due = Carbon::parse($invoice->due_date)->startOfDay();
        $daysUntil = (int) $today->diffInDays($due, false);

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
