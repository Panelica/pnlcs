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
            // The panel already decides what it is called in one place:
            // company_name() in support/formatting.php reads the white-label
            // override first and falls back to the general CompanyName. The
            // checklist looked only at the override, so an operator who filled
            // the field on the general settings screen - the obvious place, and
            // the one the rest of the product treats as the business identity -
            // still saw nothing move. Both count, exactly as they do everywhere
            // else. The config fallback is deliberately not consulted: it always
            // has a value, and "PNLCS" is not an answer to what your company is
            // called.
            $companyName = trim((string) Setting::get('whitelabel_company_name', '')) !== ''
                || trim((string) Setting::get('CompanyName', '')) !== '';
            $companyLogo = trim((string) Setting::get('custom_logo_path', '')) !== '';
            $companyDone = $companyName && $companyLogo;

            $companyMissing = [];
            if (! $companyName) {
                $companyMissing[] = __('admin.settings.company_name');
            }
            if (! $companyLogo) {
                $companyMissing[] = __('admin.settings.logo');
            }
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
                // Straight at the field that is still missing, and the two
                // halves live on different screens: the name belongs to the
                // general settings, the logo to appearance. Sending the operator
                // to one page for both is what made this step feel impossible.
                [
                    'key'      => 'company',
                    'done'     => $companyDone,
                    'route'    => $companyName ? 'admin.settings.appearance' : 'admin.settings.general',
                    'missing'  => $companyMissing,
                    'fragment' => '',
                ],
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
