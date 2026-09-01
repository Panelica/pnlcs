<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Currency;
use App\Models\Setting;
use App\Models\TaxRule;
use App\Models\TicketDepartment;
use App\Models\TicketStatus;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedCoreData();

        $this->call([
            PermissionSeeder::class,
            SettingsSeeder::class,
            EmailTemplateSeeder::class,
            DomainPricingSeeder::class,
            HomepageSectionSeeder::class,
            LanguageSeeder::class,
            TranslationSeeder::class,
        ]);
    }

    private function seedCoreData(): void
    {
        // Admin role + user
        $role = AdminRole::firstOrCreate(
            ['name' => 'Full Administrator'],
            ['description' => 'Full access to all areas', 'is_full_admin' => true]
        );
        Admin::firstOrCreate(
            ['username' => 'admin'],
            ['role_id' => $role->id, 'email' => 'admin@pnlcs.com', 'password' => Hash::make('admin123'), 'first_name' => 'System', 'last_name' => 'Administrator']
        );

        // Default currencies
        Currency::firstOrCreate(['code' => 'USD'], ['prefix' => '$', 'suffix' => ' USD', 'rate' => 1.00000, 'is_default' => true]);
        Currency::firstOrCreate(['code' => 'EUR'], ['prefix' => '€', 'suffix' => ' EUR', 'rate' => 0.92000]);
        Currency::firstOrCreate(['code' => 'GBP'], ['prefix' => '£', 'suffix' => ' GBP', 'rate' => 0.79000]);
        Currency::firstOrCreate(['code' => 'TRY'], ['prefix' => '₺', 'suffix' => ' TRY', 'rate' => 32.50000]);

        // Default Polish VAT rates, grouped under Poland; 23% is the default.
        TaxRule::firstOrCreate(['name' => 'VAT 23%', 'country' => 'PL'], ['tax_rate' => 23.00, 'state' => '', 'is_default' => true]);
        TaxRule::firstOrCreate(['name' => 'VAT 8%', 'country' => 'PL'], ['tax_rate' => 8.00, 'state' => '', 'is_default' => false]);
        TaxRule::firstOrCreate(['name' => 'VAT 5%', 'country' => 'PL'], ['tax_rate' => 5.00, 'state' => '', 'is_default' => false]);
        TaxRule::firstOrCreate(['name' => 'VAT 0%', 'country' => 'PL'], ['tax_rate' => 0.00, 'state' => '', 'is_default' => false]);
        TaxRule::firstOrCreate(['name' => 'VAT ZW', 'country' => 'PL'], ['tax_rate' => 0.00, 'state' => '', 'is_default' => false]);
        TaxRule::firstOrCreate(['name' => 'VAT NP', 'country' => 'PL'], ['tax_rate' => 0.00, 'state' => '', 'is_default' => false]);

        // Standard VAT rate for every other European country (and Turkey).
        $europeanVat = [
            'AT' => 20, 'BE' => 21, 'BG' => 20, 'HR' => 25, 'CY' => 19, 'CZ' => 21,
            'DK' => 25, 'EE' => 22, 'FI' => 25.5, 'FR' => 20, 'DE' => 19, 'GR' => 24,
            'HU' => 27, 'IE' => 23, 'IT' => 22, 'LV' => 21, 'LT' => 21, 'LU' => 17,
            'MT' => 18, 'NL' => 21, 'PT' => 23, 'RO' => 19, 'SK' => 23,
            'SI' => 22, 'ES' => 21, 'SE' => 25,
            'GB' => 20, 'CH' => 8.1, 'NO' => 25, 'IS' => 24, 'LI' => 8.1, 'TR' => 20,
            'AL' => 20, 'AD' => 4.5, 'BY' => 20, 'BA' => 17, 'GE' => 18, 'MD' => 20,
            'MK' => 18, 'ME' => 21, 'RS' => 20, 'UA' => 20, 'MC' => 20, 'SM' => 22,
            'AM' => 20, 'AZ' => 18, 'XK' => 18,
        ];

        foreach ($europeanVat as $code => $rate) {
            TaxRule::firstOrCreate(
                ['country' => $code, 'state' => ''],
                ['name' => "VAT {$rate}%", 'tax_rate' => $rate, 'is_default' => true]
            );
        }

        // Default settings
        $settings = [
            ['setting' => 'CompanyName', 'value' => 'PNLCS', 'group' => 'general'],
            ['setting' => 'Domain', 'value' => 'hosting.panelica.com', 'group' => 'general'],
            ['setting' => 'Logo', 'value' => '', 'group' => 'general'],
            ['setting' => 'DefaultLanguage', 'value' => 'en', 'group' => 'general'],
            ['setting' => 'DefaultPaymentMethod', 'value' => 'banktransfer', 'group' => 'general'],
            ['setting' => 'InvoiceNumberFormat', 'value' => 'INV-{year}{month}-{num}', 'group' => 'general'],
            ['setting' => 'InvoiceNumberYearlyReset', 'value' => '0', 'group' => 'general'],
            // InvoicePayTerms, EnableTax and TaxType used to be seeded here and
            // were never read anywhere: due dates come from InvoiceDueDays and
            // tax is decided per line. Seeding them invited editing a knob
            // that is connected to nothing.
            ['setting' => 'DateFormat', 'value' => 'd/m/Y', 'group' => 'general'],
        ];
        foreach ($settings as $s) {
            Setting::firstOrCreate(['setting' => $s['setting']], $s);
        }

        // Default ticket departments
        TicketDepartment::firstOrCreate(['name' => 'General'], ['description' => 'General inquiries', 'email' => 'support@pnlcs.com']);
        TicketDepartment::firstOrCreate(['name' => 'Technical Support'], ['description' => 'Technical issues and server problems', 'email' => 'tech@pnlcs.com']);
        TicketDepartment::firstOrCreate(['name' => 'Billing'], ['description' => 'Billing and payment inquiries', 'email' => 'billing@pnlcs.com']);
        TicketDepartment::firstOrCreate(['name' => 'Sales'], ['description' => 'Pre-sales questions', 'email' => 'sales@pnlcs.com']);

        // Default ticket statuses (matching WHMCS)
        TicketStatus::firstOrCreate(['title' => 'Open'], ['color' => '#22c55e', 'sort_order' => 1, 'show_active' => true]);
        TicketStatus::firstOrCreate(['title' => 'Answered'], ['color' => '#3b82f6', 'sort_order' => 2, 'show_active' => true]);
        TicketStatus::firstOrCreate(['title' => 'Customer-Reply'], ['color' => '#f59e0b', 'sort_order' => 3, 'show_active' => true, 'show_awaiting' => true]);
        TicketStatus::firstOrCreate(['title' => 'On Hold'], ['color' => '#8b5cf6', 'sort_order' => 4, 'show_active' => true]);
        TicketStatus::firstOrCreate(['title' => 'In Progress'], ['color' => '#06b6d4', 'sort_order' => 5, 'show_active' => true]);
        TicketStatus::firstOrCreate(['title' => 'Closed'], ['color' => '#6b7280', 'sort_order' => 6, 'auto_close' => 0]);

        // RBAC roles
        AdminRole::firstOrCreate(
            ['name' => 'Support Agent'],
            ['description' => 'Support tickets only', 'permissions' => ['list_clients', 'view_clients', 'list_tickets', 'view_tickets', 'reply_tickets', 'list_services', 'view_services', 'list_orders', 'view_orders']]
        );
        AdminRole::firstOrCreate(
            ['name' => 'Billing Manager'],
            ['description' => 'Billing and orders', 'permissions' => ['list_clients', 'view_clients', 'list_invoices', 'view_invoices', 'create_invoices', 'manage_invoices', 'list_orders', 'view_orders', 'manage_orders', 'list_quotes', 'manage_quotes']]
        );
    }
}
