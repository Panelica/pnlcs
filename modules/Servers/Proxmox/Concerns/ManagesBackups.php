<?php

namespace Modules\Servers\Proxmox\Concerns;

use App\Models\Service;
use Modules\Servers\Proxmox\ProxmoxPlan;
use Modules\Servers\Proxmox\ProxmoxResult;

/**
 * Backups on the server's backup storage (a directory, NFS or Proxmox Backup
 * Server), up to the plan's number.
 *
 * A backup belongs to a guest by its name - vzdump-qemu-<vmid>-... - and every
 * restore or delete checks that before touching it, so a customer cannot name
 * another customer's backup. Measured on Proxmox VE 9.1: a restore over a
 * protected guest fails ("protection mode enabled"), so the protection is
 * lifted first; the backup brings its own settings back, protection, tags and
 * MAC included, and the guest's snapshots are gone afterwards.
 */
trait ManagesBackups
{
    private function backupStorage(Service $service): string
    {
        return (string) ($service->server?->setting('backup_storage', '') ?? '');
    }

    /** vzdump-qemu-101-... on a directory or NFS; backup/vm/101/... on Proxmox Backup Server. */
    private function ownsBackup(array $guest, string $volid): bool
    {
        $pbs = $guest['type'] === 'lxc' ? 'ct' : 'vm';

        return (bool) preg_match('#(^|/)vzdump-'.preg_quote($guest['type'], '#').'-'.$guest['vmid'].'-#', $volid)
            || (bool) preg_match('#:backup/'.$pbs.'/'.$guest['vmid'].'/#', $volid);
    }

    /** @return array<int, array{volid: string, size: int, time: int, notes: string, protected: bool}>|ProxmoxResult */
    public function backups(Service $service): array|ProxmoxResult
    {
        $storage = $this->backupStorage($service);
        if ($storage === '') {
            return ProxmoxResult::failed(__('proxmox.error.backups_off'));
        }
        $api = $service->server ? $this->client($service->server) : null;
        $guest = $api ? $this->guest($service, $api) : ProxmoxResult::failed(__('proxmox.error.no_server'));
        if ($guest instanceof ProxmoxResult) {
            return $guest;
        }

        $list = $api->get("nodes/{$guest['node']}/storage/{$storage}/content", ['content' => 'backup', 'vmid' => $guest['vmid']]);
        if (! $list->ok) {
            return $list;
        }

        return collect($list->list())
            ->filter(fn ($v) => $this->ownsBackup($guest, (string) ($v['volid'] ?? '')))
            ->map(fn ($v) => [
                'volid' => (string) $v['volid'],
                'size' => (int) ($v['size'] ?? 0),
                'time' => (int) ($v['ctime'] ?? 0),
                'notes' => trim((string) ($v['notes'] ?? '')),
                'protected' => ! empty($v['protected']),
            ])
            ->sortByDesc('time')->values()->all();
    }

    /** Start a backup. It runs on its own and appears in the list when done. */
    public function backupCreate(Service $service): array
    {
        $limit = ProxmoxPlan::forService($service)->backups;
        $storage = $this->backupStorage($service);
        if ($limit < 1 || $storage === '') {
            return $this->buildResult(false, __('proxmox.error.backups_off'));
        }
        if ($busy = $this->busy($service)) {
            return $busy;
        }
        $existing = $this->backups($service);
        if ($existing instanceof ProxmoxResult) {
            return $this->buildResult(false, $existing->error);
        }
        if (count($existing) >= $limit) {
            return $this->buildResult(false, __('proxmox.error.backup_limit', ['limit' => $limit]));
        }

        $api = $this->client($service->server);
        $guest = $this->guest($service, $api);

        // One backup at a time per guest.
        $active = collect($api->get("nodes/{$guest['node']}/tasks", ['vmid' => $guest['vmid'], 'source' => 'active'])->list())
            ->contains(fn ($t) => ($t['type'] ?? '') === 'vzdump');
        if ($active) {
            return $this->buildResult(false, __('proxmox.error.backup_running'));
        }

        $sent = $api->post("nodes/{$guest['node']}/vzdump", [
            'vmid' => $guest['vmid'], 'storage' => $storage, 'mode' => 'snapshot', 'compress' => 'zstd',
            'notes-template' => 'PNLCS service '.$service->id,
        ]);

        return $sent->ok
            ? $this->buildResult(true, __('proxmox.backup_started'), ['upid' => $sent->data])
            : $this->buildResult(false, $sent->error);
    }

    public function backupRestore(Service $service, string $volid): array
    {
        if ($busy = $this->busy($service)) {
            return $busy;
        }
        $existing = $this->backups($service);
        if ($existing instanceof ProxmoxResult) {
            return $this->buildResult(false, $existing->error);
        }
        if (! collect($existing)->contains('volid', $volid)) {
            return $this->buildResult(false, __('proxmox.error.backup_missing'));
        }

        $api = $this->client($service->server);
        $guest = $this->guest($service, $api);
        $error = $this->beginReplace($service, 'restore', $api, $guest, ['volid' => $volid]);
        if ($error !== null) {
            return $this->buildResult(false, __('proxmox.error.stop_failed', ['error' => $error]));
        }
        $state = $this->advanceUntil($service, $this->waitSeconds(3600));

        return $state === 'failed'
            ? $this->buildResult(false, (string) ($this->getModuleData($service)['pve_error'] ?? ''))
            : $this->buildResult(true, __($state === 'done' ? 'proxmox.backup_restored' : 'proxmox.backup_restore_started'));
    }

    public function backupDelete(Service $service, string $volid): array
    {
        $existing = $this->backups($service);
        if ($existing instanceof ProxmoxResult) {
            return $this->buildResult(false, $existing->error);
        }
        $backup = collect($existing)->firstWhere('volid', $volid);
        if (! $backup) {
            return $this->buildResult(false, __('proxmox.error.backup_missing'));
        }
        if ($backup['protected']) {
            return $this->buildResult(false, __('proxmox.error.backup_protected'));
        }

        $api = $this->client($service->server);
        $guest = $this->guest($service, $api);
        $storage = $this->backupStorage($service);
        $sent = $api->delete("nodes/{$guest['node']}/storage/{$storage}/content/".rawurlencode($volid));
        if (! $sent->ok) {
            return $this->buildResult(false, $sent->error);
        }
        $done = $api->waitForTask($guest['node'], $sent->data, $this->waitSeconds(60));

        return ! $done->ok && $done->status !== 504
            ? $this->buildResult(false, $done->error)
            : $this->buildResult(true, __('proxmox.backup_deleted'));
    }
}
