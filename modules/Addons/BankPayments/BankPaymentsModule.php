<?php

namespace Modules\Addons\BankPayments;

use App\Contracts\AddonModuleInterface;
use App\Contracts\AdminMenuProvider;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Lang;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Modules\Addons\BankPayments\Services\BankPaymentsSettings;

/**
 * BankPayments addon.
 *
 * The first version only reads a mailbox and stores recognised bank messages
 * and transactions in the addon's own tables. It deliberately has no path to
 * Invoice or PaymentService; matching and booking arrive in later stages.
 */
class BankPaymentsModule implements AddonModuleInterface, AdminMenuProvider
{
    public function getName(): string
    {
        return 'bank_payments';
    }

    public function getDisplayName(): string
    {
        // Discovery runs before an inactive addon's provider (and therefore
        // its translation namespace) is loaded, so the module card must not
        // depend on the namespace.
        return $this->text('addon.title');
    }

    public function getDescription(): string
    {
        return $this->text('addon.description');
    }

    public function getVersion(): string
    {
        return '0.1.0';
    }

    public function getAuthor(): string
    {
        return 'PNLCS';
    }

    public function activate(): array
    {
        // An inactive addon's provider is not loaded, so `php artisan migrate`
        // cannot discover these migrations until the addon is switched on.
        // Install the addon's own tables here, once, before activation.
        $tables = ['bank_mailboxes', 'bank_messages', 'bank_transactions', 'bank_matches', 'bank_imports', 'bank_sync_logs', 'bank_payer_profiles'];

        if (collect($tables)->contains(fn (string $table): bool => ! Schema::hasTable($table))
            || ! Schema::hasColumn('bank_transactions', 'matching_status')) {
            try {
                Artisan::call('migrate', [
                    '--path' => base_path('modules/Addons/BankPayments/Database/Migrations'),
                    '--realpath' => true,
                    '--force' => true,
                ]);
            } catch (\Throwable $e) {
                return [
                    'success' => false,
                    'message' => $this->text('addon.tables_failed', ['error' => $e->getMessage()]),
                ];
            }
        }

        foreach ($tables as $table) {
            if (! Schema::hasTable($table)) {
                return [
                    'success' => false,
                    'message' => 'Brak tabeli '.$table.'. Uruchom php artisan migrate.',
                ];
            }
        }

        return ['success' => true, 'message' => $this->text('addon.activated')];
    }

    public function deactivate(): array
    {
        return ['success' => true, 'message' => $this->text('addon.deactivated')];
    }

    public function config(): array
    {
        return BankPaymentsSettings::fields();
    }

    /**
     * The addon publishes its link through adminMenu(); sidebar() is the
     * legacy Extensions slot and must stay empty so the link is not duplicated.
     */
    public function sidebar(): array
    {
        return [];
    }

    public function upgrade(string $fromVersion): array
    {
        return ['success' => true, 'message' => 'Zaktualizowano z wersji '.$fromVersion.'.'];
    }

    public function output(Request $request): string
    {
        return view('bank-payments::admin.embed', [
            'mode' => BankPaymentsSettings::mode(),
        ])->render();
    }

    /**
     * The Billing entry, through the contract rather than a condition in the
     * layout: manage_invoices is what every route in this addon requires, so
     * the link is offered to exactly the staff who can open it.
     */
    public function adminMenu(): array
    {
        if (! Route::has('admin.bank-payments.index')) {
            return [];
        }

        return [[
            'location' => 'billing',
            'label' => $this->text('addon.menu'),
            'route' => 'admin.bank-payments.index',
            'permission' => 'manage_invoices',
            'order' => 45,
        ]];
    }

    /**
     * One of this addon's own texts.
     *
     * The namespace is registered by the service provider, which Laravel only
     * loads while the addon is active - and the module page lists an inactive
     * addon by name too. Registering it here as well costs nothing and keeps
     * the name and the description out of the code.
     */
    private function text(string $key, array $replace = []): string
    {
        Lang::addNamespace('bank-payments', __DIR__.'/lang');

        return (string) __('bank-payments::'.$key, $replace);
    }
}
