<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Concerns\ResolvesClient;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Service;
use App\Services\ProvisioningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Modules\Servers\Proxmox\ProxmoxModule;
use Modules\Servers\Proxmox\ProxmoxPlan;

/**
 * The customer's own controls for a virtual server: status, power, graphs,
 * root password and reinstall.
 *
 * Reading works while the service is active or suspended; anything that
 * changes the machine only while it is active, so a customer suspended for
 * non-payment cannot switch the server back on from here.
 */
class VpsController extends Controller
{
    use ResolvesClient;

    private function module(Service $service, bool $forChange): ProxmoxModule
    {
        abort_if($service->client_id !== $this->getClientId(), 403);

        $status = strtolower((string) $service->status);
        abort_unless(in_array($status, $forChange ? ['active'] : ['active', 'suspended'], true), 409, __('proxmox.client.not_active'));

        $module = $service->server_id ? app(ProvisioningService::class)->resolveModule($service) : null;
        abort_unless($module instanceof ProxmoxModule, 404);

        return $module;
    }

    public function status(Service $service): JsonResponse
    {
        return response()->json($this->module($service, false)->vmStatus($service));
    }

    public function graphs(Request $request, Service $service): JsonResponse
    {
        return response()->json($this->module($service, false)->graphs($service, (string) $request->query('timeframe', 'hour')));
    }

    public function power(Request $request, Service $service): JsonResponse
    {
        $module = $this->module($service, true);
        $action = (string) $request->validate([
            'action' => 'required|in:'.implode(',', ProxmoxModule::CLIENT_POWER_ACTIONS),
        ])['action'];

        $result = $module->power($service, $action);
        $this->audit($service, "VPS power action '{$action}'", $result['success']);

        return $this->answer($result);
    }

    public function password(Request $request, Service $service): JsonResponse
    {
        $module = $this->module($service, true);
        $request->validate(['password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()]]);

        $result = $module->changePassword($service, (string) $request->input('password'));
        $this->audit($service, 'VPS root password changed', $result['success']);

        return $this->answer($result);
    }

    /**
     * Wipe the server and install it again. The customer types the server's
     * name to confirm: a checkbox is too easy to tick on the way past.
     */
    public function reinstall(Request $request, Service $service): JsonResponse
    {
        $module = $this->module($service, true);
        $choices = collect(ProxmoxPlan::forService($service)->reinstallChoices())->pluck('id')->all();

        $request->validate([
            'image' => ['required', 'string', \Illuminate\Validation\Rule::in($choices)],
            'password' => ['nullable', Password::min(10)->letters()->numbers()],
            'confirm' => ['required', 'string'],
        ]);

        $expected = $this->confirmationWord($service);
        if (strcasecmp(trim((string) $request->input('confirm')), $expected) !== 0) {
            return response()->json(['success' => false, 'message' => __('proxmox.client.confirm_mismatch', ['word' => $expected])], 422);
        }

        $password = (string) $request->input('password') ?: Str::password(16, symbols: false);
        $result = $module->reinstall($service, (string) $request->input('image'), $password);
        $this->audit($service, 'VPS reinstalled with '.ProxmoxPlan::imageName((string) $request->input('image')), $result['success']);

        if ($result['success']) {
            $result['data'] = ['password' => $password];
        }

        return $this->answer($result);
    }

    public function snapshots(Service $service): JsonResponse
    {
        $list = $this->module($service, false)->snapshots($service);

        return response()->json(is_array($list)
            ? ['available' => true, 'snapshots' => $list, 'limit' => ProxmoxPlan::forService($service)->snapshots]
            : ['available' => false, 'error' => $list->error]);
    }

    public function snapshotAction(Request $request, Service $service): JsonResponse
    {
        $module = $this->module($service, true);
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
        $this->audit($service, "VPS snapshot {$v['action']} '{$v['name']}'", $result['success']);

        return $this->answer($result);
    }

    public function backups(Service $service): JsonResponse
    {
        $list = $this->module($service, false)->backups($service);

        return response()->json(is_array($list)
            ? ['available' => true, 'backups' => $list, 'limit' => ProxmoxPlan::forService($service)->backups]
            : ['available' => false, 'error' => $list->error]);
    }

    public function backupAction(Request $request, Service $service): JsonResponse
    {
        $module = $this->module($service, true);
        $v = $request->validate([
            'action' => 'required|in:create,restore,delete',
            'volid' => 'required_unless:action,create|nullable|string|max:255',
        ]);

        // Restoring overwrites the server; the same typed confirmation as a reinstall.
        if ($v['action'] === 'restore' && strcasecmp(trim((string) $request->input('confirm')), self::confirmationWord($service)) !== 0) {
            return response()->json(['success' => false, 'message' => __('proxmox.client.confirm_mismatch', ['word' => self::confirmationWord($service)])], 422);
        }

        $result = match ($v['action']) {
            'create' => $module->backupCreate($service),
            'restore' => $module->backupRestore($service, (string) $v['volid']),
            'delete' => $module->backupDelete($service, (string) $v['volid']),
        };
        $this->audit($service, "VPS backup {$v['action']}".($v['volid'] ?? '' ? ' '.basename((string) $v['volid']) : ''), $result['success']);

        return $this->answer($result);
    }

    public static function confirmationWord(Service $service): string
    {
        return $service->domain ?: 'vps-'.$service->id;
    }

    private function answer(array $result): JsonResponse
    {
        return response()->json([
            'success' => (bool) $result['success'],
            'message' => (string) $result['message'],
            'data' => $result['data'] ?? [],
        ], $result['success'] ? 200 : 422);
    }

    private function audit(Service $service, string $what, bool $ok): void
    {
        ActivityLog::log(
            "{$what} for service #{$service->id}".($ok ? '' : ' (failed)'),
            auth()->user()?->email,
            $service->client_id,
        );
    }
}
