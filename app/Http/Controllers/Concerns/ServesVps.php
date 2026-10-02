<?php

namespace App\Http\Controllers\Concerns;

use App\Models\ActivityLog;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Modules\Servers\Proxmox\ProxmoxModule;
use Modules\Servers\Proxmox\ProxmoxPlan;

/**
 * The virtual server endpoints behind the VPS panel. The customer's page and
 * the admin's page draw the same panel from the same answers; the two
 * controllers differ only in who may reach a service and in what state.
 */
trait ServesVps
{
    /** The module for a service the caller may see ($forChange = false) or change. */
    abstract protected function vpsModule(Service $service, bool $forChange): ProxmoxModule;

    abstract protected function vpsActor(): ?string;

    public function status(Service $service): JsonResponse
    {
        return response()->json($this->vpsModule($service, false)->vmStatus($service));
    }

    public function graphs(Request $request, Service $service): JsonResponse
    {
        return response()->json($this->vpsModule($service, false)->graphs($service, (string) $request->query('timeframe', 'hour')));
    }

    public function power(Request $request, Service $service): JsonResponse
    {
        $module = $this->vpsModule($service, true);
        $action = (string) $request->validate([
            'action' => ['required', Rule::in(ProxmoxModule::CLIENT_POWER_ACTIONS)],
        ])['action'];

        $result = $module->power($service, $action);
        $this->vpsAudit($service, "VPS power action '{$action}'", $result['success']);

        return $this->vpsAnswer($result);
    }

    public function password(Request $request, Service $service): JsonResponse
    {
        $module = $this->vpsModule($service, true);
        $request->validate(['password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()]]);

        $result = $module->changePassword($service, (string) $request->input('password'));
        $this->vpsAudit($service, 'VPS root password changed', $result['success']);

        return $this->vpsAnswer($result);
    }

    /**
     * Wipe the server and install it again. The server's name is typed to
     * confirm: a checkbox is too easy to tick on the way past.
     */
    public function reinstall(Request $request, Service $service): JsonResponse
    {
        $module = $this->vpsModule($service, true);
        $choices = collect(ProxmoxPlan::forService($service)->reinstallChoices())->pluck('id')->all();

        $request->validate([
            'image' => ['required', 'string', Rule::in($choices)],
            'password' => ['nullable', Password::min(10)->letters()->numbers()],
            'confirm' => ['required', 'string'],
            'ssh_keys' => ['nullable', 'string', 'max:8000'],
        ]);

        // The keys the fresh install is given; the form shows the current
        // ones, so sending it back unchanged keeps them and emptying it
        // removes them.
        $sshKeys = null;
        if ($request->has('ssh_keys')) {
            $sshKeys = ProxmoxModule::normaliseSshKeys($request->input('ssh_keys'));
            if ($sshKeys === null) {
                throw ValidationException::withMessages(['ssh_keys' => __('proxmox.client.ssh_keys_invalid')]);
            }
        }

        if ($refused = $this->vpsConfirmRefused($request, $service)) {
            return $refused;
        }

        if ($sshKeys !== null) {
            $module->setSshKeys($service, $sshKeys);
        }

        $password = (string) $request->input('password') ?: Str::password(16, symbols: false);
        $result = $module->reinstall($service, (string) $request->input('image'), $password);
        $this->vpsAudit($service, 'VPS reinstalled with '.ProxmoxPlan::imageName((string) $request->input('image')), $result['success']);

        if ($result['success']) {
            $result['data'] = ['password' => $password];
        }

        return $this->vpsAnswer($result);
    }

    public function snapshots(Service $service): JsonResponse
    {
        $list = $this->vpsModule($service, false)->snapshots($service);

        return response()->json(is_array($list)
            ? ['available' => true, 'snapshots' => $list, 'limit' => ProxmoxPlan::forService($service)->snapshots]
            : ['available' => false, 'error' => $list->error]);
    }

    public function snapshotAction(Request $request, Service $service): JsonResponse
    {
        $module = $this->vpsModule($service, true);
        $v = $request->validate([
            'action' => 'required|in:create,rollback,delete',
            'name' => ['required', 'string', 'regex:'.ProxmoxModule::SNAPSHOT_NAME],
            'description' => 'nullable|string|max:200',
        ]);

        $result = match ($v['action']) {
            'create' => $module->snapshotCreate($service, $v['name'], (string) ($v['description'] ?? '')),
            'rollback' => $module->snapshotRollback($service, $v['name']),
            'delete' => $module->snapshotDelete($service, $v['name']),
        };
        $this->vpsAudit($service, "VPS snapshot {$v['action']} '{$v['name']}'", $result['success']);

        return $this->vpsAnswer($result);
    }

    public function backups(Service $service): JsonResponse
    {
        $list = $this->vpsModule($service, false)->backups($service);

        return response()->json(is_array($list)
            ? ['available' => true, 'backups' => $list, 'limit' => ProxmoxPlan::forService($service)->backups]
            : ['available' => false, 'error' => $list->error]);
    }

    public function backupAction(Request $request, Service $service): JsonResponse
    {
        $module = $this->vpsModule($service, true);
        $v = $request->validate([
            'action' => 'required|in:create,restore,delete',
            'volid' => 'required_unless:action,create|nullable|string|max:255',
        ]);

        // Restoring overwrites the server; the same typed confirmation as a reinstall.
        if ($v['action'] === 'restore' && ($refused = $this->vpsConfirmRefused($request, $service))) {
            return $refused;
        }

        $result = match ($v['action']) {
            'create' => $module->backupCreate($service),
            'restore' => $module->backupRestore($service, (string) $v['volid']),
            'delete' => $module->backupDelete($service, (string) $v['volid']),
        };
        $this->vpsAudit($service, "VPS backup {$v['action']}".(($v['volid'] ?? '') !== '' ? ' '.basename((string) $v['volid']) : ''), $result['success']);

        return $this->vpsAnswer($result);
    }

    /** The word typed to confirm a reinstall or a restore: the server's name. */
    public static function confirmationWord(Service $service): string
    {
        return $service->domain ?: 'vps-'.$service->id;
    }

    private function vpsConfirmRefused(Request $request, Service $service): ?JsonResponse
    {
        $expected = self::confirmationWord($service);
        if (strcasecmp(trim((string) $request->input('confirm')), $expected) === 0) {
            return null;
        }

        return response()->json(['success' => false, 'message' => __('proxmox.client.confirm_mismatch', ['word' => $expected])], 422);
    }

    private function vpsAnswer(array $result): JsonResponse
    {
        return response()->json([
            'success' => (bool) $result['success'],
            'message' => (string) $result['message'],
            'data' => $result['data'] ?? [],
        ], $result['success'] ? 200 : 422);
    }

    private function vpsAudit(Service $service, string $what, bool $ok): void
    {
        ActivityLog::log("{$what} for service #{$service->id}".($ok ? '' : ' (failed)'), $this->vpsActor(), $service->client_id);
    }
}
