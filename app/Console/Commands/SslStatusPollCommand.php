<?php

namespace App\Console\Commands;

use App\Models\SslOrder;
use App\Services\SslProvisioningService;
use Illuminate\Console\Command;

class SslStatusPollCommand extends Command
{
    protected $signature = 'pnlcs:ssl-status-poll';
    protected $description = 'Poll certificate authorities for pending SSL order statuses';

    public function handle(SslProvisioningService $sslService): int
    {
        $orders = SslOrder::pendingPoll()->get();

        if ($orders->isEmpty()) {
            $this->info('No pending SSL orders to poll.');
            return 0;
        }

        $this->info("Polling {$orders->count()} pending SSL order(s)...");

        $completed = 0;
        $failed = 0;

        foreach ($orders as $order) {
            try {
                $result = $sslService->pollCertificateStatus($order);
                $order->refresh();

                if ($order->status === 'Completed') {
                    $this->line("  [OK] Order #{$order->id} ({$order->domain}) - Certificate issued");
                    $completed++;
                } else {
                    $this->line("  [..] Order #{$order->id} ({$order->domain}) - Status: {$order->status}");
                }
            } catch (\Throwable $e) {
                $this->error("  [!!] Order #{$order->id} - Error: {$e->getMessage()}");
                $failed++;
            }
        }

        $this->info("Done. Completed: {$completed}, Failed: {$failed}, Still pending: " . ($orders->count() - $completed - $failed));

        return 0;
    }
}
