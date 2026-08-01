<?php

namespace App\Console\Commands;

use App\Mail\CreditCardExpiryMail;
use App\Models\PaymentMethod;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class CreditCardExpiryCommand extends Command
{
    protected $signature = 'pnlcs:cc-expiry-alerts';

    protected $description = 'Send alerts for credit cards expiring soon';

    public function handle(): int
    {
        $sent = 0;

        // This month and next. Counting days instead put the far end of the
        // window inside the current month whenever the command ran early in a
        // 31-day month - and it is scheduled monthly, so it always runs on the
        // first: nobody was ever warned about a card dying next month, only
        // about one dying this month.
        //
        // expiry_date holds a year and a month (2026-08), which compares
        // correctly as text.
        $from = now()->format('Y-m');
        $to = now()->addMonthNoOverflow()->format('Y-m');

        $methods = PaymentMethod::with('client')
            ->where('payment_type', 'cc')
            ->whereNotNull('last_four')
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '>=', $from)
            ->where('expiry_date', '<=', $to)
            ->get();

        foreach ($methods as $method) {
            $client = $method->client;
            if (! $client?->email) {
                continue;
            }

            try {
                Mail::to($client->email)->send(
                    new CreditCardExpiryMail($client, $method)
                );
                $sent++;
            } catch (\Throwable $e) {
                Log::error("CC expiry alert failed for client #{$client->id}: {$e->getMessage()}");
            }
        }

        $this->info("Sent {$sent} credit card expiry alert(s).");

        return Command::SUCCESS;
    }
}
