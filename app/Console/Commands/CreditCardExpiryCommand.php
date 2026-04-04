<?php

namespace App\Console\Commands;

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
        $now = now();

        // Find payment methods expiring in the next 30 days
        $methods = PaymentMethod::with('client')
            ->where('payment_type', 'cc')
            ->whereNotNull('last_four')
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '>=', $now->startOfMonth()->toDateString())
            ->where('expiry_date', '<=', $now->copy()->addDays(30)->endOfMonth()->toDateString())
            ->get();

        foreach ($methods as $method) {
            $client = $method->client;
            if (!$client?->email) {
                continue;
            }

            try {
                Mail::to($client->email)->send(
                    new \App\Mail\CreditCardExpiryMail($client, $method)
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
