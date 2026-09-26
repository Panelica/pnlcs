<?php

namespace Modules\Servers\Proxmox\Concerns;

use App\Models\Service;
use Modules\Servers\Proxmox\ProxmoxPlan;
use Modules\Servers\Proxmox\ProxmoxResult;

/**
 * Snapshots the customer takes and rolls back to, up to the plan's number.
 *
 * Measured on Proxmox VE 9.1: the protection flag PNLCS sets does not block
 * snapshots or rollbacks, and a KVM guest rolled back to a snapshot taken
 * without its memory is left switched off - so a rollback of a running guest
 * starts it again afterwards.
 */
trait ManagesSnapshots
{
    public const SNAPSHOT_NAME = '/^[A-Za-z][A-Za-z0-9_-]{1,39}$/';

    /** @return array<int, array{name: string, description: string, time: int}>|ProxmoxResult */
    public function snapshots(Service $service): array|ProxmoxResult
    {
        $api = $service->server ? $this->client($service->server) : null;
        $guest = $api ? $this->guest($service, $api) : ProxmoxResult::failed(__('proxmox.error.no_server'));
        if ($guest instanceof ProxmoxResult) {
            return $guest;
        }

        $list = $api->get("{$guest['path']}/snapshot");
        if (! $list->ok) {
            return $list;
        }

        return collect($list->list())
            ->reject(fn ($s) => ($s['name'] ?? '') === 'current')
            ->map(fn ($s) => [
                'name' => (string) $s['name'],
                'description' => trim((string) ($s['description'] ?? '')),
                'time' => (int) ($s['snaptime'] ?? 0),
            ])
            ->sortByDesc('time')->values()->all();
    }

    public function snapshotCreate(Service $service, string $name, string $description = ''): array
    {
        $limit = ProxmoxPlan::forService($service)->snapshots;
        if ($limit < 1) {
            return $this->buildResult(false, __('proxmox.error.snapshots_off'));
        }
        if (! preg_match(self::SNAPSHOT_NAME, $name)) {
            return $this->buildResult(false, __('proxmox.error.snapshot_name'));
        }
        if ($busy = $this->busy($service)) {
            return $busy;
        }

        $existing = $this->snapshots($service);
        if ($existing instanceof ProxmoxResult) {
            return $this->buildResult(false, $existing->error);
        }
        if (count($existing) >= $limit) {
            return $this->buildResult(false, __('proxmox.error.snapshot_limit', ['limit' => $limit]));
        }
        if (collect($existing)->contains('name', $name)) {
            return $this->buildResult(false, __('proxmox.error.snapshot_exists', ['name' => $name]));
        }

        $api = $this->client($service->server);
        $guest = $this->guest($service, $api);
        $sent = $api->post("{$guest['path']}/snapshot", ['snapname' => $name, 'description' => mb_substr($description, 0, 200)]);
        if (! $sent->ok) {
            return $this->buildResult(false, $sent->error);
        }
        $done = $api->waitForTask($guest['node'], $sent->data, $this->waitSeconds(120));
        if (! $done->ok && $done->status !== 504) {
            return $this->buildResult(false, $done->error);
        }

        return $this->buildResult(true, __($done->ok ? 'proxmox.snapshot_created' : 'proxmox.snapshot_pending', ['name' => $name]));
    }

    public function snapshotRollback(Service $service, string $name): array
    {
        if ($busy = $this->busy($service)) {
            return $busy;
        }
        $existing = $this->snapshots($service);
        if ($existing instanceof ProxmoxResult) {
            return $this->buildResult(false, $existing->error);
        }
        if (! collect($existing)->contains('name', $name)) {
            return $this->buildResult(false, __('proxmox.error.snapshot_missing', ['name' => $name]));
        }

        $api = $this->client($service->server);
        $guest = $this->guest($service, $api);
        $running = ($api->get("{$guest['path']}/status/current")->data['status'] ?? '') === 'running';

        $sent = $api->post("{$guest['path']}/snapshot/".rawurlencode($name).'/rollback');
        if (! $sent->ok) {
            return $this->buildResult(false, $sent->error);
        }
        $this->startJob($service, 'rollback', 'rolling_back', $sent->data, [
            'node' => $guest['node'], 'vmid' => $guest['vmid'], 'type' => $guest['type'], 'was_running' => $running,
        ]);

        $state = $this->advanceUntil($service, $this->waitSeconds(120));

        return $state === 'failed'
            ? $this->buildResult(false, (string) ($this->getModuleData($service)['pve_error'] ?? ''))
            : $this->buildResult(true, __($state === 'done' ? 'proxmox.snapshot_rolled_back' : 'proxmox.snapshot_rollback_started', ['name' => $name]));
    }

    public function snapshotDelete(Service $service, string $name): array
    {
        if ($busy = $this->busy($service)) {
            return $busy;
        }
        $existing = $this->snapshots($service);
        if ($existing instanceof ProxmoxResult) {
            return $this->buildResult(false, $existing->error);
        }
        if (! collect($existing)->contains('name', $name)) {
            return $this->buildResult(false, __('proxmox.error.snapshot_missing', ['name' => $name]));
        }

        $api = $this->client($service->server);
        $guest = $this->guest($service, $api);
        $sent = $api->delete("{$guest['path']}/snapshot/".rawurlencode($name));
        if (! $sent->ok) {
            return $this->buildResult(false, $sent->error);
        }
        $done = $api->waitForTask($guest['node'], $sent->data, $this->waitSeconds(120));

        return ! $done->ok && $done->status !== 504
            ? $this->buildResult(false, $done->error)
            : $this->buildResult(true, __('proxmox.snapshot_deleted', ['name' => $name]));
    }

    /** Advance a job for up to $seconds; what state it reached. */
    private function advanceUntil(Service $service, int $seconds): string
    {
        $deadline = time() + $seconds;
        while (true) {
            $state = $this->advanceJob($service);
            if ($state !== 'running' || time() >= $deadline) {
                return $state;
            }
            \Illuminate\Support\Sleep::for(2)->seconds();
        }
    }
}
