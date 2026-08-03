<?php

namespace Modules\Servers;

use App\Contracts\ServerModuleInterface;
use App\Models\Server;
use App\Models\ServerGroup;
use App\Models\Service;
use Illuminate\Support\Facades\Log;

abstract class AbstractServerModule implements ServerModuleInterface
{
    /**
     * The address to talk to.
     *
     * The hostname when it resolves, and the recorded IP when it does not.
     * A server record carries both and only the hostname was ever used, so a
     * stale or mistyped hostname sent the call somewhere else with no sign of
     * what had happened.
     */
    protected function serverHost(Server $server): string
    {
        $hostname = trim((string) $server->hostname);
        $ip = trim((string) $server->ip_address);

        if ($hostname === '') {
            return $ip;
        }

        if ($ip === '' || filter_var($hostname, FILTER_VALIDATE_IP)) {
            return $hostname;
        }

        return @gethostbyname($hostname) === $hostname && ! filter_var($hostname, FILTER_VALIDATE_IP)
            ? $ip          // did not resolve at all
            : $hostname;
    }

    protected function getServer(Service $service): ?Server
    {
        if ($service->server) {
            return $service->server;
        }

        // When the product is tied to a server group, the group is authoritative:
        // an exhausted or misconfigured group must fail provisioning (module
        // queue + admin notification) instead of overflowing onto an arbitrary
        // server outside the group.
        $group = $service->product?->serverGroup;
        $server = $group
            ? $this->pickFromGroup($group, $service)
            : Server::where('type', $this->getModuleName())->where('active', true)->first();

        // Stick the service to the chosen server so later lifecycle actions
        // (suspend, terminate, usage polling) hit the same machine.
        if ($server && $service->exists) {
            $service->forceFill(['server_id' => $server->id])->save();
            $service->setRelation('server', $server);
        }

        return $server;
    }

    /**
     * Select a server from the product's server group, honouring the group's
     * fill strategy and each server's max_accounts capacity.
     *
     * fill        → lowest-id server that still has capacity (fill one at a time)
     * round_robin → server currently hosting the fewest services
     */
    private function pickFromGroup(ServerGroup $group, Service $service): ?Server
    {
        $candidates = $group->servers()
            ->where('active', true)
            ->where('disabled', false)
            ->where('type', $this->getModuleName())
            ->get();
        if ($candidates->isEmpty()) {
            return null;
        }

        $counts = Service::whereIn('server_id', $candidates->pluck('id'))
            ->whereIn('status', ['active', 'suspended'])
            ->selectRaw('server_id, count(*) as aggregate')
            ->groupBy('server_id')
            ->pluck('aggregate', 'server_id');

        $withCapacity = $candidates->filter(
            fn ($s) => (int) $s->max_accounts === 0 || ($counts[$s->id] ?? 0) < (int) $s->max_accounts
        );
        if ($withCapacity->isEmpty()) {
            Log::warning('Server group has no server with free capacity', [
                'group_id' => $group->id, 'service_id' => $service->id, 'module' => $this->getModuleName(),
            ]);

            return null;
        }

        return $group->fill_type === 'round_robin'
            ? $withCapacity->sortBy(fn ($s) => [($counts[$s->id] ?? 0), $s->id])->first()
            : $withCapacity->sortBy('id')->first();
    }

    protected function buildResult(bool $success, string $message, array $data = []): array
    {
        return ['success' => $success, 'message' => $message, 'data' => $data];
    }

    /**
     * Module data (remote account ids and the like) lives in its own column.
     * It used to share services.notes with the customer's own note, so the
     * first module write replaced whatever the customer had typed — and a
     * human note made the module data unreadable. Legacy rows that still hold
     * JSON in notes are read here for backwards compatibility.
     */
    protected function getModuleData(Service $service): array
    {
        $data = $service->module_data;
        if (is_array($data) && $data !== []) {
            return $data;
        }

        $legacy = json_decode((string) $service->getRawOriginal('notes'), true);

        return is_array($legacy) ? $legacy : [];
    }

    protected function setModuleData(Service $service, array $data): void
    {
        $merged = array_merge($this->getModuleData($service), $data);
        $attributes = ['module_data' => $merged];

        // Drop a legacy JSON payload from notes so it cannot shadow the real
        // column later; a genuine customer note is left untouched.
        if (is_array(json_decode((string) $service->getRawOriginal('notes'), true))) {
            $attributes['notes'] = null;
        }

        $service->forceFill($attributes)->save();
    }

    protected function logAction(Service $service, string $action, array $result): void
    {
        $name = $this->getModuleName();
        Log::channel('daily')->info("Module [{$name}] {$action}", [
            'service_id' => $service->id,
            'server_id' => $service->server_id,
            'success' => $result['success'] ?? false,
            'message' => $result['message'] ?? '',
        ]);
    }

    protected function getRemotePackage(Service $service): ?string
    {
        $product = $service->product;
        if (! $product) {
            return null;
        }
        $config = is_string($product->config_options)
            ? json_decode($product->config_options, true)
            : ($product->config_options ?? []);

        return $config['package_name'] ?? $config['panelica_plan_id'] ?? $config['cpanel_package'] ?? null;
    }
}
