<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Server;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Servers\Proxmox\Ipv4Pool;
use Modules\Servers\Proxmox\ProxmoxCapacity;
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

    /** What the server's nodes have and what has been given out of it (#303). */
    public function capacity(Server $server)
    {
        abort_unless(strtolower((string) $server->type) === 'proxmox', 404);

        try {
            $overview = ProxmoxCapacity::for($server)->overview();
        } catch (\Throwable $e) {
            $overview = ['ok' => false, 'error' => $e->getMessage(), 'threshold' => 0, 'nodes' => [], 'services' => []];
        }

        try {
            $holders = Ipv4Pool::holders($server);
            (new EloquentCollection(array_column($holders, 'service')))->load('client:id,first_name,last_name,company_name');
            $ipv4 = ['holders' => $holders, 'free' => Ipv4Pool::freeCount($server), 'pool' => trim((string) $server->setting('ipv4_pool')) !== ''];
        } catch (\Throwable) {
            $ipv4 = ['holders' => [], 'free' => 0, 'pool' => false];
        }

        return view('admin.config.server-capacity', ['server' => $server, 'overview' => $overview, 'ipv4' => $ipv4]);
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
