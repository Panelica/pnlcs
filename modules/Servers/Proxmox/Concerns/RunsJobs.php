<?php

namespace Modules\Servers\Proxmox\Concerns;

use App\Models\Service;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Modules\Servers\Proxmox\ProxmoxClient;
use Modules\Servers\Proxmox\ProxmoxPlan;
use Modules\Servers\Proxmox\ProxmoxResult;

/**
 * Work that takes longer than a web request may: reinstalling and restoring.
 *
 * A web request has thirty seconds; cloning a large template or restoring a
 * backup can take many minutes, and a request killed half way through leaves
 * a customer with no server and nothing to say why. So each of these is a
 * chain of Proxmox tasks recorded in module_data.pve_job. Starting one only
 * sends the first task; advanceJob() looks at the task and sends the next
 * when it is done. The status the client area polls and the pnlcs:proxmox-tasks
 * command both call it, so the work finishes whether or not anybody watches.
 */
trait RunsJobs
{
    /**
     * Longest a caller waits for a task before leaving it to the job: all of
     * it from the console, twenty seconds in a web request.
     * config('pnlcs.proxmox_wait_cap') overrides both.
     */
    private function waitSeconds(int $wanted): int
    {
        $cap = config('pnlcs.proxmox_wait_cap');
        $cap = $cap === null ? (app()->runningInConsole() ? $wanted : 20) : (int) $cap;

        return max(0, min($wanted, $cap));
    }

    public function currentJob(Service $service): ?array
    {
        $job = $this->getModuleData($service)['pve_job'] ?? null;

        return is_array($job) && ! empty($job['kind']) ? $job : null;
    }

    private function startJob(Service $service, string $kind, string $step, mixed $upid, array $extra = []): void
    {
        $this->setModuleData($service, [
            'pve_job' => ['kind' => $kind, 'step' => $step, 'upid' => $upid, 'started' => time()] + $extra,
            'pve_state' => $kind === 'reinstall' ? 'reinstalling' : ($kind === 'restore' ? 'restoring' : 'busy'),
            'pve_error' => null,
        ]);
    }

    private function moveJob(Service $service, array $job, string $step, mixed $upid): void
    {
        $this->setModuleData($service, ['pve_job' => ['step' => $step, 'upid' => $upid] + $job]);
    }

    private function endJob(Service $service, bool $ok, ?string $error = null, array $data = []): void
    {
        $job = $this->currentJob($service);
        $this->setModuleData($service, $data + [
            'pve_job' => null,
            'pve_state' => $ok ? 'ready' : (($job['kind'] ?? 'job').'_failed'),
            'pve_error' => $error,
        ]);
        $this->logAction($service, (string) ($job['kind'] ?? 'job'), $this->buildResult($ok, $error ?? 'done'));
    }

    /**
     * Take a job one step further if its current task has finished.
     *
     * @return string running, done, failed or none
     */
    public function advanceJob(Service $service): string
    {
        if (! $this->currentJob($service) || ! $service->server) {
            return 'none';
        }

        $lock = Cache::lock("pve-job-{$service->id}", 60);
        if (! $lock->get()) {
            return 'running';
        }

        try {
            $service->refresh();
            $job = $this->currentJob($service);
            if (! $job) {
                return 'none';
            }
            $api = $this->client($service->server);
            $node = (string) $job['node'];

            if (is_string($job['upid'] ?? null) && str_starts_with($job['upid'], 'UPID:')) {
                $task = $api->get("nodes/{$node}/tasks/".rawurlencode($job['upid']).'/status');
                if ($task->ok && ($task->data['status'] ?? '') === 'running') {
                    if (time() - (int) $job['started'] > 7200) {
                        $this->endJob($service, false, __('proxmox.error.job_timeout'));

                        return 'failed';
                    }

                    return 'running';
                }
                $exit = (string) ($task->data['exitstatus'] ?? '');
                if (! $task->ok || ! (str_starts_with($exit, 'OK') || str_starts_with($exit, 'WARNINGS'))) {
                    $this->failJob($service, $api, $job, $task->ok ? $exit : $task->error);

                    return 'failed';
                }
            }

            return $this->nextStep($service, $api, $job);
        } catch (\Throwable $e) {
            Log::error('Proxmox job step failed', ['service' => $service->id, 'error' => $e->getMessage()]);
            $this->endJob($service, false, $e->getMessage());

            return 'failed';
        } finally {
            $lock->release();
        }
    }

    private function nextStep(Service $service, ProxmoxClient $api, array $job): string
    {
        $node = (string) $job['node'];
        $vmid = (int) $job['vmid'];
        $type = (string) $job['type'];
        $plan = $this->jobPlan($service, $job);

        $path = "nodes/{$node}/{$type}/{$vmid}";

        switch ($job['kind'].'/'.$job['step']) {
            case 'reinstall/stopping':
            case 'restore/stopping':
                // Protection blocks both a delete and a restore over the guest.
                $unprotect = $api->put("{$path}/config", ['protection' => 0]);
                if (! $unprotect->ok) {
                    $this->endJob($service, false, $unprotect->error);

                    return 'failed';
                }
                $sent = $job['kind'] === 'reinstall'
                    ? $api->delete($path, ['purge' => 1, 'destroy-unreferenced-disks' => 1])
                    : ($type === 'lxc'
                        ? $api->post("nodes/{$node}/lxc", ['vmid' => $vmid, 'ostemplate' => $job['volid'], 'restore' => 1, 'force' => 1, 'storage' => $plan->storage])
                        : $api->post("nodes/{$node}/qemu", ['vmid' => $vmid, 'archive' => $job['volid'], 'force' => 1, 'storage' => $plan->storage]));
                if (! $sent->ok) {
                    $api->put("{$path}/config", ['protection' => 1]);
                    $this->endJob($service, false, $job['kind'] === 'reinstall'
                        ? __('proxmox.error.remove_failed', ['vmid' => $vmid, 'error' => $sent->error])
                        : $sent->error);

                    return 'failed';
                }
                $this->moveJob($service, $job, $job['kind'] === 'reinstall' ? 'removing' : 'restoring', $sent->data);

                return 'running';

            case 'reinstall/removing':
                $sent = $type === 'lxc'
                    ? $api->post("nodes/{$node}/lxc", ['net0' => $this->lxcNet($plan, $this->ipFromModuleData($service), $job['mac'] ?? null)]
                        + $this->lxcCreateBody($service, $service->server, $plan, $vmid, $this->ipFromModuleData($service)))
                    : $this->sendQemuCreate($service, $service->server, $api, $plan, $node, $vmid);
                if (! $sent->ok) {
                    $this->endJob($service, false, __('proxmox.error.create_failed', ['vmid' => $vmid, 'error' => $sent->error]));

                    return 'failed';
                }
                $this->moveJob($service, $job, 'creating', $sent->data);

                return 'running';

            case 'reinstall/creating':
                $configured = $type === 'lxc'
                    ? $this->configureLxc($service, $api, $plan, $node, $vmid)
                    : $this->configureQemu($service, $api, $plan, $node, $vmid, (string) $service->password);
                if ($configured->ok && $type === 'qemu' && ! empty($job['mac'])) {
                    $api->put("nodes/{$node}/qemu/{$vmid}/config", ['net0' => $this->qemuNet($plan, $job['mac'])]);
                }
                if (! $configured->ok) {
                    $this->endJob($service, false, __('proxmox.error.configure_failed', ['vmid' => $vmid, 'error' => $configured->error]));

                    return 'failed';
                }
                $this->startAndWait($api, $node, "nodes/{$node}/{$type}/{$vmid}");
                $this->endJob($service, true, null, ['pve_image' => $job['image']]);

                return 'done';

            case 'restore/restoring':
                // The backup brings its own settings back, protection included.
                if (! empty($job['was_running'])) {
                    $this->startAndWait($api, $node, "nodes/{$node}/{$type}/{$vmid}");
                }
                $this->endJob($service, true);

                return 'done';

            case 'rollback/rolling_back':
                // Without saved memory a rolled-back guest is left switched off.
                if (! empty($job['was_running'])) {
                    $this->startAndWait($api, $node, "nodes/{$node}/{$type}/{$vmid}");
                }
                $this->endJob($service, true);

                return 'done';
        }

        $this->endJob($service, false, "Unknown step {$job['kind']}/{$job['step']}");

        return 'failed';
    }

    /**
     * Start the guest and give the start a moment, so the status read straight
     * after says running rather than the stopped it was a second ago.
     */
    private function startAndWait(ProxmoxClient $api, string $node, string $path): void
    {
        $start = $api->post("{$path}/status/start");
        if ($start->ok) {
            $api->waitForTask($node, $start->data, $this->waitSeconds(30));
        }
    }

    /** A failed step: say why, and put the protection back on whatever is left. */
    private function failJob(Service $service, ProxmoxClient $api, array $job, string $why): void
    {
        $path = "nodes/{$job['node']}/{$job['type']}/{$job['vmid']}";
        if ($api->get("{$path}/config")->ok) {
            $api->put("{$path}/config", ['protection' => 1]);
        }
        $this->endJob($service, false, __('proxmox.error.job_failed', ['step' => __('proxmox.job.'.$job['kind']), 'error' => $why]));
    }

    private function jobPlan(Service $service, array $job): ProxmoxPlan
    {
        $plan = ProxmoxPlan::forService($service);
        $values = $plan->values;
        $values['type'] = $job['type'];
        if (! empty($job['image'])) {
            $values[$job['type'] === 'lxc' ? 'ostemplate' : 'template'] = $job['image'];
        }

        return new ProxmoxPlan($values);
    }

    /** Refuse a second piece of work while one runs. */
    private function busy(Service $service): ?array
    {
        if ($this->currentJob($service) && $this->advanceJob($service) === 'running') {
            return $this->buildResult(false, __('proxmox.error.busy', ['job' => __('proxmox.job.'.$this->currentJob($service)['kind'])]));
        }

        return null;
    }

    /**
     * Start work that replaces the guest's disks: switch it off first (a hard
     * stop, the disks are about to go anyway), then the job takes over.
     */
    private function beginReplace(Service $service, string $kind, ProxmoxClient $api, array $guest, array $extra): ?string
    {
        $running = ($api->get("{$guest['path']}/status/current")->data['status'] ?? 'stopped') !== 'stopped';
        $upid = null;
        if ($running) {
            $stop = $api->post("{$guest['path']}/status/stop");
            if (! $stop->ok) {
                return $stop->error;
            }
            $upid = $stop->data;
        }
        $this->startJob($service, $kind, 'stopping', $upid, [
            'node' => $guest['node'], 'vmid' => $guest['vmid'], 'type' => $guest['type'], 'was_running' => $running,
        ] + $extra);

        return null;
    }

    /**
     * Wipe the guest and install it again from a template, keeping its VM id,
     * address and MAC so DNS records and DHCP reservations stay valid.
     *
     * Returns as soon as the removal has started; see advanceJob().
     */
    public function reinstall(Service $service, string $image, string $password): array
    {
        if ($busy = $this->busy($service)) {
            return $busy;
        }
        $server = $service->server;
        $api = $server ? $this->client($server) : null;
        $guest = $api ? $this->guest($service, $api) : ProxmoxResult::failed(__('proxmox.error.no_server'));
        if ($guest instanceof ProxmoxResult) {
            return $this->buildResult(false, $guest->error);
        }

        $plan = ProxmoxPlan::forService($service);
        if (! collect($plan->reinstallChoices())->contains('id', $image)) {
            return $this->buildResult(false, __('proxmox.error.image_not_offered'));
        }
        if ($guest['type'] === 'qemu' && ! ctype_digit($image)) {
            return $this->buildResult(false, __('proxmox.error.no_template'));
        }

        $service->forceFill(['password' => $password])->save();
        $error = $this->beginReplace($service, 'reinstall', $api, $guest, [
            'image' => $image, 'mac' => $this->macOf((string) ($guest['config']['net0'] ?? '')),
        ]);
        if ($error !== null) {
            return $this->buildResult(false, __('proxmox.error.stop_failed', ['error' => $error]));
        }

        $state = $this->advanceUntil($service, $this->waitSeconds(900));

        return match ($state) {
            'done' => $this->buildResult(true, __('proxmox.reinstalled', ['vmid' => $guest['vmid']])),
            'failed' => $this->buildResult(false, (string) ($this->getModuleData($service)['pve_error'] ?? '')),
            default => $this->buildResult(true, __('proxmox.reinstall_started', ['vmid' => $guest['vmid']]), ['job' => 'reinstall']),
        };
    }
}
