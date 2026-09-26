<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Server;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Servers\Proxmox\ProxmoxImages;

/**
 * The image library of a Proxmox server: official cloud images and
 * container templates, installed from here instead of the host's shell.
 */
class ProxmoxImageController extends Controller
{
    public function show(Server $server)
    {
        abort_unless(strtolower((string) $server->type) === 'proxmox', 404);

        return view('admin.config.server-images', ['server' => $server]);
    }

    public function status(Server $server): JsonResponse
    {
        abort_unless(strtolower((string) $server->type) === 'proxmox', 404);

        try {
            return response()->json(ProxmoxImages::for($server)->overview());
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    public function install(Request $request, Server $server): JsonResponse
    {
        abort_unless(strtolower((string) $server->type) === 'proxmox', 404);
        $v = $request->validate([
            'kind' => 'required|in:kvm,lxc',
            'id' => ['required', 'string', 'max:200', 'regex:/^[A-Za-z0-9._-]+$/'],
            'disk_storage' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9][A-Za-z0-9._-]*$/'],
        ]);

        $started = ProxmoxImages::for($server)->install($v['kind'], $v['id'], (string) ($v['disk_storage'] ?? ''));
        ActivityLog::log(
            "Proxmox image {$v['kind']} '{$v['id']}' install on server #{$server->id}".($started->ok ? ' started' : ' refused: '.$started->error),
            auth('admin')->user()?->full_name ?: 'admin',
        );

        return response()->json(
            ['success' => $started->ok, 'message' => $started->ok ? __('proxmox.images.started') : $started->error],
            $started->ok ? 200 : ($started->status >= 400 && $started->status < 500 ? $started->status : 422),
        );
    }
}
