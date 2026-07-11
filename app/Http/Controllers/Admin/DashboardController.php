<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GatewaySettings;
use App\Models\Product;
use App\Models\Server;
use App\Models\Setting;
use App\Services\WidgetManager;

class DashboardController extends Controller
{
    public function __construct(protected WidgetManager $widgets) {}

    public function index()
    {
        $widgetOutput = $this->widgets->renderAll();

        return view('admin.dashboard', [
            'widgetOutput' => $widgetOutput,
            'setup'        => $this->setupChecklist(),
        ]);
    }

    /**
     * Getting-started checklist shown on the dashboard until setup is complete.
     * Each item detects its own completion so it ticks off automatically.
     *
     * @return array{items: array<int, array>, done: int, total: int, complete: bool}
     */
    private function setupChecklist(): array
    {
        try {
            $companyDone  = trim((string) Setting::get('whitelabel_company_name', '')) !== '';
            $emailDone    = config('mail.default') !== 'log' && config('mail.default') !== null;
            $gatewayDone  = GatewaySettings::query()->exists();
            $serverDone   = Server::query()->exists();
            $productDone  = Product::query()->exists();

            $items = [
                ['key' => 'company',  'done' => $companyDone,  'route' => 'admin.settings.general'],
                ['key' => 'email',    'done' => $emailDone,    'route' => 'admin.settings.general'],
                ['key' => 'gateway',  'done' => $gatewayDone,  'route' => 'admin.config.gateways'],
                ['key' => 'server',   'done' => $serverDone,   'route' => 'admin.config.servers'],
                ['key' => 'product',  'done' => $productDone,  'route' => 'admin.products.create'],
            ];

            $done = count(array_filter($items, fn ($i) => $i['done']));

            return [
                'items'    => $items,
                'done'     => $done,
                'total'    => count($items),
                'complete' => $done === count($items),
            ];
        } catch (\Throwable $e) {
            // Never let the checklist break the dashboard.
            return ['items' => [], 'done' => 0, 'total' => 0, 'complete' => true];
        }
    }
}
