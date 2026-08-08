<?php

namespace Modules\Servers\Vultr;

use App\Models\Server;
use App\Models\Service;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Servers\AbstractServerModule;

class VultrModule extends AbstractServerModule
{
    protected string $apiUrl = 'https://api.vultr.com/v2';

    public function getModuleName(): string
    {
        return 'vultr';
    }

    public function getConfigFields(): array
    {
        return [
            ['name' => 'api_key', 'label' => 'Vultr API Key', 'type' => 'password', 'required' => true],
            ['name' => 'default_region', 'label' => 'Default Region (e.g. ewr)', 'type' => 'text', 'default' => 'ewr'],
            ['name' => 'default_plan', 'label' => 'Default Plan (e.g. vc2-1c-1gb)', 'type' => 'text', 'default' => 'vc2-1c-1gb'],
            ['name' => 'default_os', 'label' => 'Default OS ID (e.g. 1743 for Ubuntu 24.04)', 'type' => 'text', 'default' => '1743'],
        ];
    }

    private function getApiKey(Server $server): string
    {
        return $server->access_hash ?? '';
    }

    private function api(Server $server, string $method, string $endpoint, array $data = []): array
    {
        $apiKey = $this->getApiKey($server);
        if (! $apiKey) {
            return ['success' => false, 'message' => 'Vultr API key not configured.'];
        }

        try {
            $request = Http::withToken($apiKey)
                ->timeout(60)
                ->acceptJson();

            $response = match (strtoupper($method)) {
                'POST' => $request->post("{$this->apiUrl}/{$endpoint}", $data),
                'PATCH' => $request->patch("{$this->apiUrl}/{$endpoint}", $data),
                'DELETE' => $request->delete("{$this->apiUrl}/{$endpoint}"),
                default => $request->get("{$this->apiUrl}/{$endpoint}", $data),
            };

            if (! $response->successful()) {
                $error = $response->json('error', "HTTP {$response->status()}");

                return ['success' => false, 'message' => is_string($error) ? $error : json_encode($error), 'raw' => $response->json()];
            }

            return ['success' => true, 'message' => 'OK', 'raw' => $response->json() ?? []];
        } catch (\Throwable $e) {
            Log::error("VultrModule API error: {$e->getMessage()}");

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function create(Service $service): array
    {
        $server = $this->getServer($service);
        if (! $server) {
            return $this->buildResult(false, 'No Vultr server configured.');
        }

        $client = $service->client;
        $product = $service->product;

        $config = $product ? (is_string($product->config_options) ? json_decode($product->config_options, true) : ($product->config_options ?? [])) : [];

        $region = $config['vultr_region'] ?? $config['region'] ?? 'ewr';
        $plan = $config['vultr_plan'] ?? $config['plan'] ?? 'vc2-1c-1gb';
        $osId = (int) ($config['vultr_os_id'] ?? $config['os_id'] ?? 1743);

        $label = $service->domain ?: "pnlcs-{$service->id}";
        $hostname = $service->domain ?: "vps-{$service->id}.pnlcs.local";

        $result = $this->api($server, 'POST', 'instances', [
            'region' => $region,
            'plan' => $plan,
            'os_id' => $osId,
            'label' => $label,
            'hostname' => $hostname,
            'tags' => ["pnlcs-service-{$service->id}"],
        ]);

        if (! $result['success']) {
            return $this->buildResult(false, "VPS creation failed: {$result['message']}");
        }

        $instance = $result['raw']['instance'] ?? [];
        $instanceId = $instance['id'] ?? null;
        $mainIp = $instance['main_ip'] ?? 'pending';
        $defaultPassword = $instance['default_password'] ?? '';

        $this->setModuleData($service, [
            'vultr_instance_id' => $instanceId,
            'vultr_main_ip' => $mainIp,
        ]);

        $service->update([
            'status' => 'active',
            'username' => 'root',
            'password' => $defaultPassword,
        ]);

        $out = $this->buildResult(true, 'Vultr VPS created.', [
            'instance_id' => $instanceId,
            'main_ip' => $mainIp,
        ]);
        $this->logAction($service, 'create', $out);

        return $out;
    }

    public function suspend(Service $service, string $reason = ''): array
    {
        // Vultr doesn't have native suspend — we halt the instance
        $server = $this->getServer($service);
        if (! $server) {
            return $this->buildResult(false, 'No Vultr server configured.');
        }

        $data = $this->getModuleData($service);
        $instanceId = $data['vultr_instance_id'] ?? null;
        if (! $instanceId) {
            return $this->buildResult(false, 'Vultr instance ID not found.');
        }

        $result = $this->api($server, 'POST', "instances/{$instanceId}/halt");
        if (! $result['success']) {
            return $this->buildResult(false, "Halt failed: {$result['message']}");
        }

        $service->update(['status' => 'suspended', 'suspension_date' => now(), 'suspension_reason' => $reason]);
        $out = $this->buildResult(true, 'Vultr instance halted (suspended).');
        $this->logAction($service, 'suspend', $out);

        return $out;
    }

    public function unsuspend(Service $service): array
    {
        $server = $this->getServer($service);
        if (! $server) {
            return $this->buildResult(false, 'No Vultr server configured.');
        }

        $data = $this->getModuleData($service);
        $instanceId = $data['vultr_instance_id'] ?? null;
        if (! $instanceId) {
            return $this->buildResult(false, 'Vultr instance ID not found.');
        }

        $result = $this->api($server, 'POST', "instances/{$instanceId}/start");
        if (! $result['success']) {
            return $this->buildResult(false, "Start failed: {$result['message']}");
        }

        $service->update(['status' => 'active', 'suspension_date' => null, 'suspension_reason' => null]);
        $out = $this->buildResult(true, 'Vultr instance started (unsuspended).');
        $this->logAction($service, 'unsuspend', $out);

        return $out;
    }

    public function terminate(Service $service): array
    {
        $server = $this->getServer($service);
        if (! $server) {
            return $this->buildResult(false, 'No Vultr server configured.');
        }

        $data = $this->getModuleData($service);
        $instanceId = $data['vultr_instance_id'] ?? null;
        if (! $instanceId) {
            return $this->buildResult(false, 'Vultr instance ID not found.');
        }

        $result = $this->api($server, 'DELETE', "instances/{$instanceId}");
        if (! $result['success']) {
            return $this->buildResult(false, "Destroy failed: {$result['message']}");
        }

        $service->update(['status' => 'terminated', 'termination_date' => now()]);
        $out = $this->buildResult(true, 'Vultr instance destroyed.');
        $this->logAction($service, 'terminate', $out);

        return $out;
    }

    public function changePassword(Service $service, string $newPassword): array
    {
        // Vultr doesn't have a direct password change API
        // Would need to use SSH or reinstall
        return $this->buildResult(false, 'Password change via API is not supported for Vultr VPS. Use SSH or reinstall.');
    }

    public function changePackage(Service $service, array $newPackage): array
    {
        $server = $this->getServer($service);
        if (! $server) {
            return $this->buildResult(false, 'No Vultr server configured.');
        }

        $data = $this->getModuleData($service);
        $instanceId = $data['vultr_instance_id'] ?? null;
        if (! $instanceId) {
            return $this->buildResult(false, 'Vultr instance ID not found.');
        }

        $config = is_string($newPackage['config_options'] ?? null)
            ? json_decode($newPackage['config_options'], true)
            : ($newPackage['config_options'] ?? []);
        $plan = $config['vultr_plan'] ?? $config['plan'] ?? null;

        if (! $plan) {
            return $this->buildResult(false, 'No vultr_plan configured in product.');
        }

        $result = $this->api($server, 'PATCH', "instances/{$instanceId}", ['plan' => $plan]);
        if (! $result['success']) {
            return $this->buildResult(false, "Upgrade failed: {$result['message']}");
        }

        $out = $this->buildResult(true, 'Vultr instance plan changed.');
        $this->logAction($service, 'changePackage', $out);

        return $out;
    }

    /**
     * What Vultr says about the instances behind this server's services.
     *
     * The instance id is recorded with setModuleData(), which writes
     * services.module_data - the column module data was moved to precisely
     * because it used to share services.notes with the customer's own note.
     * This still searched notes with a LIKE, so it matched nothing and a Vultr
     * service was never updated; on a legacy row that did match, it wrote the
     * module data back into notes over whatever an operator had typed.
     */
    public function usageUpdate(Server $server): array
    {
        $result = $this->api($server, 'GET', 'instances', ['per_page' => 500]);

        if (! $result['success']) {
            return ['updated' => 0, 'errors' => 1];
        }

        $instances = [];

        foreach ($result['raw']['instances'] ?? [] as $instance) {
            $id = $instance['id'] ?? null;

            if ($id !== null) {
                $instances[(string) $id] = $instance;
            }
        }

        $services = Service::where('server_id', $server->id)
            ->where('status', 'active')
            ->get();

        $updated = 0;
        $errors = 0;

        foreach ($services as $service) {
            $instanceId = (string) ($this->getModuleData($service)['vultr_instance_id'] ?? '');
            $instance = $instanceId !== '' ? ($instances[$instanceId] ?? null) : null;

            if (! $instance) {
                $errors++;

                continue;
            }

            // The plan's disk, which is the limit; Vultr does not report used
            // disk on the instance itself.
            if (isset($instance['disk']) && is_numeric($instance['disk'])) {
                $service->update(['disk_limit' => ((int) $instance['disk']) * 1024]);
            }

            $this->setModuleData($service, [
                'vultr_ram' => $instance['ram'] ?? null,
                'vultr_vcpu' => $instance['vcpu_count'] ?? 0,
                'vultr_main_ip' => $instance['main_ip'] ?? '',
            ]);

            $updated++;
        }

        return ['updated' => $updated, 'errors' => $errors];
    }

    public function testConnection(Server $server): bool
    {
        try {
            $result = $this->api($server, 'GET', 'account');

            return $result['success'];
        } catch (\Throwable $e) {
            Log::error("VultrModule::testConnection: {$e->getMessage()}");

            return false;
        }
    }
}
