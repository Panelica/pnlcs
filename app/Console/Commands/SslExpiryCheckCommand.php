<?php

namespace App\Console\Commands;

use App\Mail\SslCertificateExpiringMail;
use App\Models\SslOrder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SslExpiryCheckCommand extends Command
{
    protected $signature = 'pnlcs:ssl-expiry-check';
    protected $description = 'Check for expiring SSL certificates and send notification emails';

    protected array $thresholds = [30, 14, 7, 3, 1];

    public function handle(): int
    {
        $this->info('Checking SSL certificate expiry dates...');

        $sent = 0;

        foreach ($this->thresholds as $days) {
            $orders = SslOrder::completed()
                ->whereNotNull('crt_expires')
                ->whereDate('crt_expires', now()->addDays($days)->toDateString())
                ->with('client')
                ->get();

            foreach ($orders as $order) {
                try {
                    if ($order->client && $order->client->email) {
                        Mail::to($order->client->email)->send(
                            new SslCertificateExpiringMail($order, $days)
                        );
                        $this->line("  Sent {$days}-day expiry notice for {$order->domain} to {$order->client->email}");
                        $sent++;
                    }
                } catch (\Throwable $e) {
                    Log::warning("SSL expiry email failed for order #{$order->id}: {$e->getMessage()}");
                    $this->error("  Failed: {$order->domain} - {$e->getMessage()}");
                }
            }
        }

        // Mark expired certificates
        $expired = SslOrder::completed()
            ->whereNotNull('crt_expires')
            ->where('crt_expires', '<', now())
            ->update(['status' => 'Expired']);

        if ($expired > 0) {
            $this->info("Marked {$expired} certificate(s) as expired.");
        }

        $this->info("Done. Sent {$sent} expiry notification(s).");

        return 0;
    }
}
