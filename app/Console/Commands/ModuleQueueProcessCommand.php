<?php

namespace App\Console\Commands;

use App\Models\ModuleQueue;
use App\Services\NotificationService;
use App\Services\ProvisioningService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ModuleQueueProcessCommand extends Command
{
    protected $signature = 'pnlcs:module-queue {--id= : Process a single queue entry immediately}';

    protected $description = 'Retry failed server module actions (create/suspend/unsuspend/terminate)';

    public function handle(ProvisioningService $provisioning): int
    {
        $query = ModuleQueue::query()->with('service.product');

        if ($this->option('id')) {
            $query->whereKey((int) $this->option('id'))->where('status', 'pending');
        } else {
            $query->due();
        }

        $entries = $query->orderBy('id')->limit(25)->get();

        foreach ($entries as $entry) {
            $service = $entry->service;

            if (! $service) {
                $entry->update(['status' => 'cancelled', 'last_error' => 'Service no longer exists']);

                continue;
            }

            $entry->increment('attempts');

            $result = match ($entry->action) {
                'create' => $provisioning->createAccount($service, queueOnFail: false),
                'suspend' => $provisioning->suspendAccount($service, (string) ($entry->payload['reason'] ?? ''), queueOnFail: false),
                'unsuspend' => $provisioning->unsuspendAccount($service, queueOnFail: false),
                'terminate' => $provisioning->terminateAccount($service, queueOnFail: false),
                default => ['success' => false, 'message' => "Unknown action '{$entry->action}'"],
            };

            if ($result['success'] ?? false) {
                $entry->update(['status' => 'completed', 'completed_at' => now(), 'last_error' => null]);
                $this->info("Queue #{$entry->id} ({$entry->action}, service #{$service->id}): completed");

                continue;
            }

            $error = $result['message'] ?? 'unknown error';

            // r117-queue: a refusal about the record rather than the connection
            // reads the same on the fifth attempt as on the first. Retrying it
            // four more times calls the panel four more times and delays the
            // alert that says somebody has to look at it.
            $hopeless = ProvisioningService::willNeverSucceed($error);

            if ($hopeless || $entry->attempts >= $entry->max_attempts) {
                $entry->update(['status' => 'failed', 'last_error' => $error]);
                Log::error("ModuleQueue #{$entry->id} permanently failed after {$entry->attempts} attempts", [
                    'service_id' => $service->id, 'action' => $entry->action, 'error' => $error,
                ]);
                app(NotificationService::class)->dispatch('module.failed_permanently', [
                    'event_type' => 'module.failed_permanently',
                    'subject' => 'Module action permanently failed',
                    'message' => $hopeless
                        ? "Module '{$entry->action}' for service #{$service->id} ({$service->domain}) cannot succeed and will NOT be retried: {$error}"
                        : "Module '{$entry->action}' for service #{$service->id} ({$service->domain}) failed {$entry->attempts} times and will NOT be retried: {$error}",
                    'service_id' => $service->id,
                    'action' => $entry->action,
                ]);
            } else {
                // Exponential backoff: 5, 10, 20, 40... minutes
                $delay = 5 * (2 ** max(0, $entry->attempts - 1));
                $entry->update(['next_attempt_at' => now()->addMinutes($delay), 'last_error' => $error]);
                $this->warn("Queue #{$entry->id} ({$entry->action}): retry in {$delay}m — {$error}");
            }
        }

        return self::SUCCESS;
    }
}
