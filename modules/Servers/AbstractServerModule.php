<?php
namespace Modules\Servers;

use App\Contracts\ServerModuleInterface;
use App\Models\Server;
use App\Models\Service;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

abstract class AbstractServerModule implements ServerModuleInterface
{
    protected function getServer(Service $service): ?Server
    {
        return $service->server ?? Server::where('type', $this->getModuleName())->where('active', true)->first();
    }

    protected function buildResult(bool $success, string $message, array $data = []): array
    {
        return ['success' => $success, 'message' => $message, 'data' => $data];
    }

    protected function getModuleData(Service $service): array
    {
        $notes = $service->notes ?? '';
        $json = json_decode($notes, true);
        return is_array($json) ? $json : [];
    }

    protected function setModuleData(Service $service, array $data): void
    {
        $existing = $this->getModuleData($service);
        $merged = array_merge($existing, $data);
        $service->update(['notes' => json_encode($merged)]);
    }

    protected function logAction(Service $service, string $action, array $result): void
    {
        $name = $this->getModuleName();
        Log::channel('daily')->info("Module [{$name}] {$action}", [
            'service_id' => $service->id,
            'server_id'  => $service->server_id,
            'success'    => $result['success'] ?? false,
            'message'    => $result['message'] ?? '',
        ]);
    }

    protected function getRemotePackage(Service $service): ?string
    {
        $product = $service->product;
        if (!$product) return null;
        $config = is_string($product->config_options)
            ? json_decode($product->config_options, true)
            : ($product->config_options ?? []);
        return $config['package_name'] ?? $config['panelica_plan_id'] ?? $config['cpanel_package'] ?? null;
    }
}
