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

    /** Days remaining at which a customer is told, nearest deadline first. */
    protected array $thresholds = [30, 14, 7, 3, 1];

    public function handle(): int
    {
        $this->info('Checking SSL certificate expiry dates...');

        $sent = 0;

        // Everything inside the widest threshold, rather than the certificates
        // expiring on one exact day. Matching a day meant a missed run - a
        // deploy, a stopped scheduler - lost that notice for good, and a
        // certificate that arrived with twenty days left never got the
        // thirty-day notice because that day was already behind it.
        $orders = SslOrder::completed()
            ->whereNotNull('crt_expires')
            ->where('crt_expires', '>=', now())
            ->where('crt_expires', '<=', now()->addDays(max($this->thresholds)))
            ->with('client')
            ->get();

        foreach ($orders as $order) {
            $daysLeft = (int) ceil(now()->diffInDays($order->crt_expires, false));
            $threshold = $this->thresholdFor($daysLeft);

            if ($threshold === null) {
                continue;
            }

            // Already told about this deadline or a nearer one.
            $alreadySent = $order->expiry_notice_days;

            if ($alreadySent !== null && (int) $alreadySent <= $threshold) {
                continue;
            }

            if (! $order->client || ! $order->client->email) {
                continue;
            }

            try {
                Mail::to($order->client->email)->send(
                    new SslCertificateExpiringMail($order, $threshold)
                );

                $order->update([
                    'expiry_notice_days' => $threshold,
                    'expiry_notice_sent_at' => now(),
                ]);

                $this->line("  Sent {$threshold}-day expiry notice for {$order->domain} to {$order->client->email}");
                $sent++;
            } catch (\Throwable $e) {
                Log::warning("SSL expiry email failed for order #{$order->id}: {$e->getMessage()}");
                $this->error("  Failed: {$order->domain} - {$e->getMessage()}");
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

    /**
     * The threshold a certificate with this many days left falls into.
     *
     * Thirteen days left belongs to the fourteen-day notice: the customer is
     * inside that window, whether or not anybody was watching on the day.
     */
    private function thresholdFor(int $daysLeft): ?int
    {
        $candidates = array_filter($this->thresholds, fn (int $t) => $daysLeft <= $t);

        return $candidates === [] ? null : min($candidates);
    }
}
