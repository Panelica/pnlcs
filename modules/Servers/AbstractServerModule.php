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
    /**
     * The plans this server offers, for the product form to choose from.
     *
     * A panel owns its own plans; the product picks one by name. Modules that
     * cannot list them return nothing and the form says so rather than
     * pretending there is a choice.
     *
     * @return array<int, array{id: string, name: string}>
     */
    public function listPackages(Server $server): array
    {
        return [];
    }

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

        $resolved = @gethostbynamel($hostname) ?: [];

        if ($resolved === []) {
            Log::warning('Server hostname does not resolve; using the recorded address', [
                'server_id' => $server->id,
                'hostname' => $hostname,
                'ip' => $ip,
            ]);

            return $ip;
        }

        if (! in_array($ip, $resolved, true)) {
            Log::warning('Server hostname resolves elsewhere; using the recorded address', [
                'server_id' => $server->id,
                'hostname' => $hostname,
                'resolved' => $resolved,
                'ip' => $ip,
            ]);

            return $ip;
        }

        return $hostname;
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
            : $this->pickWithoutGroup($service);

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

        [$withCapacity, $counts] = $this->withFreeCapacity($candidates);
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

    /**
     * A product without a server group takes the first server of its type,
     * with the same rules a group applies: active, not disabled, and below
     * its max_accounts. It used to take the first active server whatever its
     * capacity, so a full or disabled server kept receiving accounts.
     */
    private function pickWithoutGroup(Service $service): ?Server
    {
        $candidates = Server::where('type', $this->getModuleName())
            ->where('active', true)
            ->where('disabled', false)
            ->orderBy('id')
            ->get();
        if ($candidates->isEmpty()) {
            return null;
        }

        [$withCapacity] = $this->withFreeCapacity($candidates);
        if ($withCapacity->isEmpty()) {
            Log::warning('No server with free capacity', [
                'service_id' => $service->id, 'module' => $this->getModuleName(),
            ]);

            return null;
        }

        return $withCapacity->sortBy('id')->first();
    }

    /**
     * The candidates below their max_accounts (0 = no limit), and how many
     * live services each one hosts.
     *
     * @param  \Illuminate\Support\Collection<int, Server>  $candidates
     * @return array{0: \Illuminate\Support\Collection<int, Server>, 1: \Illuminate\Support\Collection<int, int>}
     */
    private function withFreeCapacity($candidates): array
    {
        $counts = Service::whereIn('server_id', $candidates->pluck('id'))
            ->whereIn('status', ['active', 'suspended'])
            ->selectRaw('server_id, count(*) as aggregate')
            ->groupBy('server_id')
            ->pluck('aggregate', 'server_id');

        $withCapacity = $candidates->filter(
            fn ($s) => (int) $s->max_accounts === 0 || ($counts[$s->id] ?? 0) < (int) $s->max_accounts
        );

        return [$withCapacity, $counts];
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

        return self::planFrom($config, ['package_name', 'panelica_plan_id', 'cpanel_package']);
    }

    /**
     * The first of these config keys that holds a value. A blank one is not a
     * value: the product form saves "" when no package is picked, and `??`
     * took that "" over the keys after it and over each module's default.
     */
    protected static function planFrom(array $config, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = trim((string) ($config[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }
}
