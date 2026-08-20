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
            // The logo, and only the logo. This step used to demand the
            // company name too, but the seeder gives every installation a
            // CompanyName and the install wizard asks for the application name
            // besides - so the name half was always already satisfied and only
            // ever confused: what actually blocks a new installation from
            // looking finished is the logo.
            $companyDone = trim((string) Setting::get('custom_logo_path', '')) !== '';
            // "Not the log driver" was too generous: the array driver discards
            // mail just as completely, and an smtp mailer with no host set is a
            // transport in name only. MailConfigProvider has already applied
            // whatever the operator chose on the settings screen by this point,
            // so what is read here is what would actually carry an email.
            $mailer    = (string) config('mail.default');
            $emailDone = ! in_array($mailer, ['', 'log', 'array'], true);
            if ($emailDone && $mailer === 'smtp') {
                $emailDone = trim((string) config('mail.mailers.smtp.host')) !== '';
            }
            $gatewayDone  = GatewaySettings::query()->exists();
            $serverDone   = Server::query()->exists();
            $productDone  = Product::query()->exists();

            $items = [
                ['key' => 'company',  'done' => $companyDone,  'route' => 'admin.settings.appearance'],
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
