<?php

namespace App\Providers;

use App\Services\Module\ModuleRegistry;
use App\Support\GuardedProviders;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Modules\Gateways\AuthorizeNet\AuthorizeNetModule;
use Modules\Gateways\BankTransfer\BankTransferModule;
use Modules\Gateways\Iyzico\IyzicoModule;
use Modules\Gateways\Mollie\MollieModule;
use Modules\Gateways\PayPal\PayPalModule;
use Modules\Gateways\Razorpay\RazorpayModule;
use Modules\Gateways\Stripe\StripeModule;
use Modules\Gateways\Tpay\TpayModule;
use Modules\Registrars\DomainNameApi\DomainNameApiRegistrar;
use Modules\Registrars\Enom\EnomRegistrar;
use Modules\Registrars\HRD\HrdRegistrar;
use Modules\Registrars\Manual\ManualRegistrar;
use Modules\Registrars\Namecheap\NamecheapRegistrar;
use Modules\Registrars\OpenProvider\OpenProviderRegistrar;
use Modules\Registrars\ResellerClub\ResellerClubRegistrar;
use Modules\Servers\AaPanel\AaPanelModule;
use Modules\Servers\CPanel\CPanelModule;
use Modules\Servers\Custom\CustomModule;
use Modules\Servers\DirectAdmin\DirectAdminModule;
use Modules\Servers\HestiaCP\HestiaCPModule;
use Modules\Servers\Panelica\PanelicaModule;
use Modules\Servers\PanelicaLicense\PanelicaLicenseModule;
use Modules\Servers\Plesk\PleskModule;
use Modules\Servers\Proxmox\ProxmoxModule;
use Modules\Servers\Vultr\VultrModule;
use Modules\Ssl\GoGetSSL\GoGetSslModule;

class ModuleServiceProvider extends ServiceProvider
{
    /**
     * A module found by its pnlcs.json may name a Laravel service provider
     * ("provider"): a gateway's own checkout pages and webhook URLs, a
     * module's tables, views and scheduled tasks. They live in the module's
     * folder, so an update leaves them in place. A provider that fails is
     * logged with the module's name and skipped (GuardedProviders).
     */
    public function boot(): void
    {
        try {
            $candidates = $this->app->make(ModuleRegistry::class)->discoveredProviders();
        } catch (\Throwable $e) {
            Log::error('Module service providers not loaded, module discovery failed: '.$e->getMessage());

            return;
        }

        $labelled = [];
        foreach ($candidates as $module => $class) {
            $labelled["Module {$module}"] = $class;
        }
        GuardedProviders::boot($this->app, $labelled);
    }

    public function register(): void
    {
        $this->app->singleton(ModuleRegistry::class, function () {
            $registry = new ModuleRegistry;

            // Built-in server modules
            $registry->registerServer('custom', CustomModule::class);

            // Built-in gateway modules
            $registry->registerGateway('banktransfer', BankTransferModule::class);
            $registry->registerGateway('stripe', StripeModule::class);
            $registry->registerGateway('paypal', PayPalModule::class);
            $registry->registerGateway('authorize', AuthorizeNetModule::class);

            // Panelica hosting panel module
            if (class_exists(PanelicaModule::class)) {
                $registry->registerServer('panelica', PanelicaModule::class);
            }

            // cPanel/WHM module
            if (class_exists(CPanelModule::class)) {
                $registry->registerServer('cpanel', CPanelModule::class);
            }

            // Plesk Obsidian module
            $registry->registerServer('plesk', PleskModule::class);

            // DirectAdmin module
            $registry->registerServer('directadmin', DirectAdminModule::class);

            // Proxmox VE module (KVM + LXC)
            if (class_exists(ProxmoxModule::class)) {
                $registry->registerServer('proxmox', ProxmoxModule::class);
            }

            // HestiaCP module
            $registry->registerServer('hestiacp', HestiaCPModule::class);
            // aaPanel module (Sub aaPanel accounts)
            $registry->registerServer('aapanel', AaPanelModule::class);
            // Vultr VPS module
            $registry->registerServer('vultr', VultrModule::class);
            // Panelica licenses, sold with a Panelica partner account
            $registry->registerServer('panelicalicense', PanelicaLicenseModule::class);

            // Namecheap Registrar
            $registry->registerRegistrar('namecheap', NamecheapRegistrar::class);
            // ResellerClub / LogicBoxes Registrar
            $registry->registerRegistrar('resellerclub', ResellerClubRegistrar::class);
            $registry->registerRegistrar('openprovider', OpenProviderRegistrar::class);
            // eNom Registrar
            $registry->registerRegistrar('enom', EnomRegistrar::class);
            // Manual Registrar
            $registry->registerRegistrar('manual', ManualRegistrar::class);
            // HRD Registrar (hrd.pl)
            $registry->registerRegistrar('hrd', HrdRegistrar::class);
            // DomainNameAPI (Turkey). The module shipped without this line and
            // without a pnlcs.json, so it could be configured nowhere and every
            // domain naming it was treated as having no registrar.
            $registry->registerRegistrar('domainnameapi', DomainNameApiRegistrar::class);

            // Mollie (EU)
            $registry->registerGateway('mollie', MollieModule::class);
            // Razorpay (India/Asia)
            $registry->registerGateway('razorpay', RazorpayModule::class);
            // Tpay (Poland)
            $registry->registerGateway('tpay', TpayModule::class);

            // iyzico (Turkey) - cards, 3D Secure, instalments
            $registry->registerGateway('iyzico', IyzicoModule::class);

            // SSL Modules
            $registry->registerSsl('gogetssl', GoGetSslModule::class);

            $this->discoverModules($registry);

            return $registry;
        });
    }

    /**
     * Register third-party modules that describe themselves with a pnlcs.json
     * manifest, so installing one does not mean editing this file.
     *
     * The rules about what a manifest may register live in
     * ModuleRegistry::registerDiscovered(): the core's own registrations
     * above always win, and the class must implement its type's contract.
     */
    private function discoverModules(ModuleRegistry $registry): void
    {
        foreach (glob(base_path('modules/*/*/pnlcs.json')) ?: [] as $manifestPath) {
            $manifest = json_decode(
                (string) file_get_contents($manifestPath),
                true
            );

            if (! is_array($manifest)) {
                continue;
            }

            $name = strtolower(trim((string) ($manifest['name'] ?? '')));
            $type = strtolower(trim((string) ($manifest['type'] ?? '')));
            $class = trim((string) ($manifest['class'] ?? ''));

            if ($name === '' || $type === '' || $class === '' || ! class_exists($class)) {
                continue;
            }

            $provider = trim((string) ($manifest['provider'] ?? ''));

            $registry->registerDiscovered($type, $name, $class, $manifestPath, $provider);
        }
    }
}
