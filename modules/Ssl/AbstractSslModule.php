<?php

namespace Modules\Ssl;

use App\Contracts\SslModuleInterface;
use App\Models\ModuleLog;
use App\Models\SslModuleSettings;
use App\Models\SslOrder;
use Illuminate\Support\Facades\Log;

abstract class AbstractSslModule implements SslModuleInterface
{
    protected function buildResult(bool $success, string $message, array $data = []): array
    {
        return ['success' => $success, 'message' => $message, 'data' => $data];
    }

    protected function getModuleSettings(): array
    {
        return SslModuleSettings::getForModule($this->getModuleName());
    }

    protected function getSetting(string $key, mixed $default = null): mixed
    {
        return SslModuleSettings::getSetting($this->getModuleName(), $key, $default);
    }

    protected function logAction(SslOrder $order, string $action, array $result): void
    {
        $name = $this->getModuleName();

        try {
            ModuleLog::create([
                'module' => $name,
                'action' => $action,
                'request' => json_encode([
                    'ssl_order_id' => $order->id,
                    'domain' => $order->domain,
                    'remote_id' => $order->remote_id,
                ]),
                'response' => json_encode($result),
                'service_id' => $order->service_id,
            ]);
        } catch (\Throwable $e) {
            Log::warning("Failed to log SSL module action: {$e->getMessage()}");
        }

        Log::channel('daily')->info("SSL Module [{$name}] {$action}", [
            'ssl_order_id' => $order->id,
            'domain' => $order->domain,
            'success' => $result['success'] ?? false,
            'message' => $result['message'] ?? '',
        ]);
    }

    public function getWebServerTypes(): array
    {
        return [
            1 => 'Apache + MOD SSL',
            2 => 'Apache + OpenSSL',
            3 => 'Apache + Raven',
            14 => 'Nginx',
            33 => 'cPanel',
            34 => 'Plesk',
            18 => 'Microsoft IIS 7+',
            -1 => 'Other / Not Listed',
        ];
    }
}
