<?php

namespace Modules\Addons\BankPayments;

use App\Contracts\AddonModuleInterface;
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
class BankPaymentsModule implements AddonModuleInterface
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
        return 'Płatności bankowe';
    }

    public function getDescription(): string
    {
        return 'Automatyczne rozliczanie wpłat bankowych na podstawie wiadomości e-mail.';
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
        $tables = ['bank_mailboxes', 'bank_messages', 'bank_transactions', 'bank_matches', 'bank_imports', 'bank_sync_logs'];

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
                    'message' => 'Nie udało się utworzyć tabel dodatku: '.$e->getMessage(),
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

        return ['success' => true, 'message' => 'Dodatek Płatności bankowe został aktywowany.'];
    }

    public function deactivate(): array
    {
        return ['success' => true, 'message' => 'Dodatek Płatności bankowe został dezaktywowany.'];
    }

    public function config(): array
    {
        return BankPaymentsSettings::fields();
    }

    public function sidebar(): array
    {
        $url = route('admin.bank-payments.index');
        $children = [
            ['label' => __('bank-payments::addon.menu_dashboard'), 'url' => $url],
            ['label' => __('bank-payments::addon.menu_transactions'), 'url' => route('admin.bank-payments.transactions.index')],
            ['label' => __('bank-payments::addon.menu_matches'), 'url' => route('admin.bank-payments.matches.index')],
            ['label' => __('bank-payments::addon.menu_logs'), 'url' => route('admin.bank-payments.logs.index')],
        ];

        if (auth('admin')->user()?->role?->hasPermission('manage_settings')) {
            $children[] = ['label' => __('bank-payments::addon.menu_mailboxes'), 'url' => route('admin.bank-payments.mailboxes.index')];
        }

        return [[
            'label' => __('bank-payments::addon.menu'),
            'icon' => 'university',
            'url' => $url,
            'children' => $children,
        ]];
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
}
