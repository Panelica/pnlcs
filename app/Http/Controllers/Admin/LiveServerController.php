<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Server;
use App\Services\Module\ModuleRegistry;
use Modules\Servers\Panelica\PanelicaModule;

/**
 * The Panelica servers, one click from their control panel.
 *
 * Only Panelica servers are listed: the one-click login uses the panel's own
 * single-use login URL (see PanelicaModule::operatorLogin), which the other
 * server modules have no equivalent of.
 */
class LiveServerController extends Controller
{
    public function index()
    {
        return view('admin.live-servers.index', [
            'servers' => $this->panelicaServers()->orderBy('name')->get(),
        ]);
    }

    public function login(Server $server)
    {
        // Route model binding would accept any server id; only Panelica ones
        // have a login to hand out.
        abort_unless($this->panelicaServers()->whereKey($server->id)->exists(), 404);

        $module = app(ModuleRegistry::class)->getServerModule('panelica');
        abort_unless($module instanceof PanelicaModule, 404);

        $result = $module->operatorLogin($server);
        $admin = auth('admin')->user();

        ActivityLog::log(
            ($result['success'] ? 'Live Servers: logged in to ' : 'Live Servers: login failed for ')
                .$server->name.' ('.$server->hostname.')',
            $admin?->username
        );

        if (! $result['success']) {
            return redirect()->route('admin.live-servers.index')->with('error', $result['message']);
        }

        return redirect()->away($result['url']);
    }

    private function panelicaServers()
    {
        return Server::whereRaw('LOWER(type) = ?', ['panelica']);
    }
}
