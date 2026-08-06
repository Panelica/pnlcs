<?php

namespace App\Services;

use App\Contracts\ServerModuleInterface;
use App\Enums\ServiceStatus;
use App\Events\ServiceActivated;
use App\Events\ServiceSuspended;
use App\Events\ServiceTerminated;
use App\Models\ModuleQueue;
use App\Models\Product;
use App\Models\Service;
use App\Services\Module\ModuleRegistry;
use Illuminate\Support\Facades\Log;

class ProvisioningService
{
    public function __construct(private ModuleRegistry $registry) {}

    public function createAccount(Service $service, bool $queueOnFail = true): array
    {
        $module = $this->getModuleForService($service);

        if (! $module) {
            return ['success' => false, 'message' => __('messages.error.no_server_module_configured')];
        }

        run_hook('PreModuleCreate', ['service' => $service]);

        try {
            $result = $module->create($service);

            if ($result['success'] ?? false) {
                $service->status = ServiceStatus::Active->value;
                $service->registration_date = $service->registration_date ?? now();
                $service->save();
                run_hook('AfterModuleCreate', ['service' => $service, 'result' => $result]);
                event(new ServiceActivated($service));
            } elseif ($queueOnFail) {
                $this->enqueueRetry($service, 'create', $result['message'] ?? 'Module create failed');
            }

            return $result;
        } catch (\Throwable $e) {
            Log::error('ProvisioningService::createAccount failed', [
                'service_id' => $service->id,
                'error' => $e->getMessage(),
            ]);
            if ($queueOnFail) {
                $this->enqueueRetry($service, 'create', $e->getMessage());
            }

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function suspendAccount(Service $service, string $reason = '', bool $queueOnFail = true): array
    {
        $module = $this->getModuleForService($service);

        if (! $module) {
            return ['success' => false, 'message' => __('messages.error.no_server_module_configured')];
        }

        run_hook('PreModuleSuspend', ['service' => $service, 'reason' => $reason]);

        try {
            $result = $module->suspend($service, $reason);

            if ($result['success'] ?? false) {
                $service->status = ServiceStatus::Suspended->value;
                $service->suspension_date = now();
                $service->suspension_reason = $reason;
                $service->save();
                run_hook('AfterModuleSuspend', ['service' => $service, 'reason' => $reason]);
                event(new ServiceSuspended($service, $reason));
            } elseif ($queueOnFail) {
                $this->enqueueRetry($service, 'suspend', $result['message'] ?? 'Module suspend failed', ['reason' => $reason]);
            }

            return $result;
        } catch (\Throwable $e) {
            Log::error('ProvisioningService::suspendAccount failed', [
                'service_id' => $service->id,
                'error' => $e->getMessage(),
            ]);
            if ($queueOnFail) {
                $this->enqueueRetry($service, 'suspend', $e->getMessage(), ['reason' => $reason]);
            }

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function unsuspendAccount(Service $service, bool $queueOnFail = true): array
    {
        $module = $this->getModuleForService($service);

        if (! $module) {
            return ['success' => false, 'message' => __('messages.error.no_server_module_configured')];
        }

        run_hook('PreModuleUnsuspend', ['service' => $service]);

        try {
            $result = $module->unsuspend($service);

            if ($result['success'] ?? false) {
                $service->status = ServiceStatus::Active->value;
                $service->suspension_date = null;
                $service->suspension_reason = null;
                $service->save();
                run_hook('AfterModuleUnsuspend', ['service' => $service]);
            } elseif ($queueOnFail) {
                $this->enqueueRetry($service, 'unsuspend', $result['message'] ?? 'Module unsuspend failed');
            }

            return $result;
        } catch (\Throwable $e) {
            Log::error('ProvisioningService::unsuspendAccount failed', [
                'service_id' => $service->id,
                'error' => $e->getMessage(),
            ]);
            if ($queueOnFail) {
                $this->enqueueRetry($service, 'unsuspend', $e->getMessage());
            }

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function terminateAccount(Service $service, bool $queueOnFail = true): array
    {
        $module = $this->getModuleForService($service);

        if (! $module) {
            return ['success' => false, 'message' => __('messages.error.no_server_module_configured')];
        }

        run_hook('PreModuleTerminate', ['service' => $service]);

        try {
            $result = $module->terminate($service);

            if ($result['success'] ?? false) {
                $service->status = ServiceStatus::Terminated->value;
                $service->termination_date = now();
                $service->save();
                run_hook('AfterModuleTerminate', ['service' => $service]);
                event(new ServiceTerminated($service));
            } elseif ($queueOnFail) {
                $this->enqueueRetry($service, 'terminate', $result['message'] ?? 'Module terminate failed');
            }

            return $result;
        } catch (\Throwable $e) {
            Log::error('ProvisioningService::terminateAccount failed', [
                'service_id' => $service->id,
                'error' => $e->getMessage(),
            ]);
            if ($queueOnFail) {
                $this->enqueueRetry($service, 'terminate', $e->getMessage());
            }

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function changePassword(Service $service, string $newPassword): array
    {
        $module = $this->getModuleForService($service);

        if (! $module) {
            return ['success' => false, 'message' => __('messages.error.no_server_module_configured')];
        }

        try {
            $result = $module->changePassword($service, $newPassword);

            if ($result['success'] ?? false) {
                $service->password = $newPassword;
                $service->save();
                run_hook('AfterModulePassword', ['service' => $service]);
            }

            return $result;
        } catch (\Throwable $e) {
            Log::error('ProvisioningService::changePassword failed', [
                'service_id' => $service->id,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function changePackage(Service $service, Product $newProduct): array
    {
        $module = $this->getModuleForService($service);

        if (! $module) {
            return ['success' => false, 'message' => __('messages.error.no_server_module_configured')];
        }

        try {
            $result = $module->changePackage($service, $newProduct->toArray());

            if ($result['success'] ?? false) {
                $service->product_id = $newProduct->id;
                $service->save();
                run_hook('AfterModuleChangePackage', ['service' => $service, 'newProduct' => $newProduct]);
            }

            return $result;
        } catch (\Throwable $e) {
            Log::error('ProvisioningService::changePackage failed', [
                'service_id' => $service->id,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Queue a failed module action for automatic retry (see pnlcs:module-queue).
     * Deduplicates on (service, action, pending) and notifies admins once.
     */
    /**
     * Whether a refusal is one no amount of trying will get past.
     *
     * A server that did not answer may answer later. A service the panel has
     * no account for will not grow one because the queue asks again: those
     * refusals are about the record, not the connection, and repeating them
     * only fills the log - four services on this installation produced the
     * same line every half hour for a fortnight.
     */
    /**
     * Whether the queue has already given up on this work for good.
     *
     * A failed entry whose reason cannot change - no account to act on, no
     * module configured - is the queue's answer, and running the job again
     * does not make it a different one. A failure that could come right is not
     * counted here, so it keeps being retried.
     */
    public function hasGivenUp(Service $service, string $action): bool
    {
        $entry = ModuleQueue::where('service_id', $service->id)
            ->where('action', $action)
            ->where('status', 'failed')
            ->latest('id')
            ->first();

        return $entry !== null && self::willNeverSucceed((string) $entry->last_error);
    }

    public static function willNeverSucceed(string $error): bool
    {
        foreach ([
            'not found in service notes',
            'no account',
            'account does not exist',
            'no such account',
            'username not set',
            'no server module configured',
        ] as $phrase) {
            if (stripos($error, $phrase) !== false) {
                return true;
            }
        }

        return false;
    }

    private function enqueueRetry(Service $service, string $action, string $error, array $payload = []): void
    {
        run_hook('ModuleActionFailed', ['service' => $service, 'action' => $action, 'error' => $error]);

        try {
            // Anything not finished counts, including an entry the queue has
            // already given up on. Looking only at pending ones meant a nightly
            // job that keeps failing wrote a fresh row - and raised the same
            // alert - every single night, for as long as it stayed broken.
            $existing = ModuleQueue::where('service_id', $service->id)
                ->where('action', $action)
                ->whereIn('status', ['pending', 'failed'])
                ->first();

            $permanent = self::willNeverSucceed($error);

            if ($existing) {
                $reopened = ! $permanent && $existing->status === 'failed';

                $existing->update([
                    'last_error' => $error,
                    // Given up on before, but the work is still wanted: let it
                    // try again rather than leaving the row dead. A server that
                    // was unreachable last night may answer tonight. Something
                    // that cannot come right is left alone.
                    'status' => $permanent ? 'failed' : 'pending',
                    'attempts' => $reopened ? 0 : $existing->attempts,
                    'next_attempt_at' => $reopened ? now()->addMinutes(5) : $existing->next_attempt_at,
                    'payload' => $payload ?: $existing->payload,
                ]);

                return;
            }

            ModuleQueue::create([
                'service_id' => $service->id,
                'action' => $action,
                'status' => $permanent ? 'failed' : 'pending',
                'attempts' => 0,
                'next_attempt_at' => $permanent ? null : now()->addMinutes(5),
                'last_error' => $error,
                'payload' => $payload ?: null,
            ]);

            app(NotificationService::class)->dispatch('module.failed', [
                'event_type' => 'module.failed',
                'subject' => 'Module action failed — queued for retry',
                'message' => "Module '{$action}' failed for service #{$service->id} ({$service->domain}): {$error}. Queued for automatic retry.",
                'service_id' => $service->id,
                'action' => $action,
            ]);
        } catch (\Throwable $e) {
            Log::error('ProvisioningService::enqueueRetry failed', ['service_id' => $service->id, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Public resolver — which server module would handle this service.
     */
    public function resolveModule(Service $service): ?ServerModuleInterface
    {
        return $this->getModuleForService($service);
    }

    private function getModuleForService(Service $service): ?ServerModuleInterface
    {
        $service->loadMissing('product', 'server');

        $serverType = $service->server?->type ?? $service->product?->server_type ?? null;

        if (! $serverType) {
            return null;
        }

        return $this->registry->getServerModule($serverType);
    }
}
