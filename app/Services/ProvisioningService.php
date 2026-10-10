<?php

namespace App\Services;

use App\Contracts\RequiresProvisioningLock;
use App\Contracts\ServerModuleInterface;
use App\Enums\ServiceStatus;
use App\Events\ServiceActivated;
use App\Events\ServiceSuspended;
use App\Events\ServiceTerminated;
use App\Events\ServiceUnsuspended;
use App\Models\ModuleQueue;
use App\Models\Product;
use App\Models\Server;
use App\Models\Service;
use App\Services\Module\ModuleRegistry;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ProvisioningService
{
    public function __construct(private ModuleRegistry $registry) {}

    /** @return array<string, mixed> */
    public function createAccount(Service $service, bool $queueOnFail = true): array
    {
        $module = $this->getModuleForService($service);

        if (! $module) {
            return ['success' => false, 'message' => __('messages.error.no_server_module_configured')];
        }

        return $this->withLifecycleLock($service, $module, function () use ($service, $module, $queueOnFail): array {
            run_hook('PreModuleCreate', ['service' => $service]);

            try {
                $result = $module->create($service);

                if ($result['success'] ?? false) {
                    $service->status = ServiceStatus::Active->value;
                    $service->registration_date = $service->registration_date ?? now();
                    $this->saveLifecycleState($service, $module);
                    $this->settleQueue($service, 'create');
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
        });
    }

    /** @return array<string, mixed> */
    public function suspendAccount(Service $service, string $reason = '', bool $queueOnFail = true): array
    {
        $module = $this->getModuleForService($service);

        if (! $module) {
            return ['success' => false, 'message' => __('messages.error.no_server_module_configured')];
        }

        return $this->withLifecycleLock($service, $module, function () use ($service, $module, $reason, $queueOnFail): array {
            run_hook('PreModuleSuspend', ['service' => $service, 'reason' => $reason]);

            try {
                $result = $module->suspend($service, $reason);

                if ($result['success'] ?? false) {
                    $service->status = ServiceStatus::Suspended->value;
                    $service->suspension_date = now();
                    $service->suspension_reason = $reason;
                    $this->saveLifecycleState($service, $module);
                    $this->settleQueue($service, 'suspend');
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
        });
    }

    /** @return array<string, mixed> */
    public function unsuspendAccount(Service $service, bool $queueOnFail = true): array
    {
        $module = $this->getModuleForService($service);

        if (! $module) {
            return ['success' => false, 'message' => __('messages.error.no_server_module_configured')];
        }

        return $this->withLifecycleLock($service, $module, function () use ($service, $module, $queueOnFail): array {
            run_hook('PreModuleUnsuspend', ['service' => $service]);

            try {
                $result = $module->unsuspend($service);

                if ($result['success'] ?? false) {
                    $service->status = ServiceStatus::Active->value;
                    $service->suspension_date = null;
                    $service->suspension_reason = null;
                    $this->saveLifecycleState($service, $module);
                    $this->settleQueue($service, 'unsuspend');
                    run_hook('AfterModuleUnsuspend', ['service' => $service]);
                    event(new ServiceUnsuspended($service));
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
        });
    }

    /** @return array<string, mixed> */
    public function terminateAccount(Service $service, bool $queueOnFail = true): array
    {
        $module = $this->getModuleForService($service);

        if (! $module) {
            return ['success' => false, 'message' => __('messages.error.no_server_module_configured')];
        }

        return $this->withLifecycleLock($service, $module, function () use ($service, $module, $queueOnFail): array {
            run_hook('PreModuleTerminate', ['service' => $service]);

            try {
                $result = $module->terminate($service);

                if ($result['success'] ?? false) {
                    $service->status = ServiceStatus::Terminated->value;
                    $service->termination_date = now();
                    $this->saveLifecycleState($service, $module);
                    $this->settleQueue($service, 'terminate');
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
        });
    }

    /** @return array<string, mixed> */
    public function changePassword(Service $service, string $newPassword): array
    {
        $module = $this->getModuleForService($service);

        if (! $module) {
            return ['success' => false, 'message' => __('messages.error.no_server_module_configured')];
        }

        return $this->withLifecycleLock($service, $module, function () use ($service, $module, $newPassword): array {
            try {
                $result = $module->changePassword($service, $newPassword);

                if ($result['success'] ?? false) {
                    $service->password = $newPassword;
                    $this->saveLifecycleState($service, $module);
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
        });
    }

    /** @return array<string, mixed> */
    public function changePackage(Service $service, Product $newProduct): array
    {
        $module = $this->getModuleForService($service);

        if (! $module) {
            return ['success' => false, 'message' => __('messages.error.no_server_module_configured')];
        }

        return $this->withLifecycleLock($service, $module, function () use ($service, $module, $newProduct): array {
            try {
                $result = $module->changePackage($service, $newProduct->toArray());

                if ($result['success'] ?? false) {
                    $service->product_id = $newProduct->id;
                    $this->saveLifecycleState($service, $module);
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
        });
    }

    /**
     * Opt-in serialization includes the local commit, not only the remote call.
     * A separate key keeps the adapter's own lock non-reentrant. Contention is
     * nonblocking, so hooks cannot deadlock by invoking provisioning recursively.
     *
     * @param  callable(): array<string, mixed>  $callback
     * @return array<string, mixed>
     */
    private function withLifecycleLock(Service $service, ServerModuleInterface $module, callable $callback): array
    {
        if (! $module instanceof RequiresProvisioningLock) {
            return $callback();
        }
        if (! $service->exists || ! $service->id) {
            return ['success' => false, 'message' => 'Save the hosting service before provisioning.'];
        }

        try {
            // The aaPanel adapter permits at most 78 sequential 30-second HTTP
            // calls in one action (39 minutes). This two-hour lease also covers
            // local persistence and ordinary synchronous hooks/queue updates.
            // Operators must bound worker/hook execution below this lease;
            // it is crash recovery, not an exactly-once delivery guarantee.
            $result = Cache::lock('provisioning:service:'.$service->id, 7200)->get(function () use ($service, $module, $callback): array {
                $service->refresh();
                $current = $this->getModuleForService($service);
                if ($current === null || $current::class !== $module::class) {
                    return ['success' => false, 'message' => 'The service module changed. Reload the service before retrying.'];
                }

                return $callback();
            });

            return $result === false
                ? ['success' => false, 'message' => 'Another provisioning action is already running for this service. Retry after it finishes.']
                : $result;
        } catch (\Throwable) {
            return ['success' => false, 'message' => 'Provisioning could not finish locally. Review the service and its remote outcome before retrying.'];
        }
    }

    private function saveLifecycleState(Service $service, ServerModuleInterface $module): void
    {
        $saved = $service->save();
        if (! $saved && $module instanceof RequiresProvisioningLock) {
            throw new \RuntimeException('The local service state could not be saved. Reconcile the remote outcome before retrying.');
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

    /**
     * The action went through, so whatever the queue still holds for it is
     * done - including an entry it had given up on. hasGivenUp() reads failed
     * entries and nothing ever cleared them: once an operator had put things
     * right and suspended by hand, auto-suspend still skipped that service
     * the next time it fell behind, on the strength of a refusal that no
     * longer applied.
     */
    private function settleQueue(Service $service, string $action): void
    {
        try {
            ModuleQueue::where('service_id', $service->id)
                ->where('action', $action)
                ->whereIn('status', ['pending', 'failed'])
                ->update(['status' => 'completed', 'completed_at' => now(), 'last_error' => null]);
        } catch (\Throwable $e) {
            Log::warning('ProvisioningService: could not settle the module queue', [
                'service_id' => $service->id, 'action' => $action, 'error' => $e->getMessage(),
            ]);
        }
    }

    /** @param array<string, mixed> $payload */
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
                $this->updateRetry($existing, $permanent, $error, $payload);

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

            // r117-alert: say which of the two happened. A refusal that cannot
            // change is recorded as failed and never picked up again, so the
            // alert written for the retry case - "queued for automatic retry" -
            // is the only word the operator ever gets, and it tells them to
            // wait for something that is not coming.
            $alert = $permanent
                ? [
                    'event' => 'module.failed_permanently',
                    'subject' => 'Module action failed — will not be retried',
                    'message' => "Module '{$action}' failed for service #{$service->id} ({$service->domain}): {$error}. This cannot be retried and needs attention.",
                ]
                : [
                    'event' => 'module.failed',
                    'subject' => 'Module action failed — queued for retry',
                    'message' => "Module '{$action}' failed for service #{$service->id} ({$service->domain}): {$error}. Queued for automatic retry.",
                ];

            app(NotificationService::class)->dispatch($alert['event'], [
                'event_type' => $alert['event'],
                'subject' => $alert['subject'],
                'message' => $alert['message'],
                'service_id' => $service->id,
                'action' => $action,
            ]);
        } catch (\Throwable $e) {
            Log::error('ProvisioningService::enqueueRetry failed', ['service_id' => $service->id, 'error' => $e->getMessage()]);
        }
    }

    /** @param array<string, mixed> $payload */
    private function updateRetry(ModuleQueue $existing, bool $permanent, string $error, array $payload): void
    {
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

        /** @var Server|null $server */
        $server = $service->getRelationValue('server');
        /** @var Product|null $product */
        $product = $service->getRelationValue('product');
        $serverType = $server !== null ? $server->type : null;
        $serverType ??= $product !== null ? $product->server_type : null;

        if (! $serverType) {
            return null;
        }

        return $this->registry->getServerModule($serverType);
    }
}
