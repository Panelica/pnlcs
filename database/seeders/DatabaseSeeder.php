<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Currency;
use App\Models\Setting;
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

        // Default settings
        $settings = [
            ['setting' => 'CompanyName', 'value' => 'PNLCS', 'group' => 'general'],
            ['setting' => 'Domain', 'value' => 'hosting.panelica.com', 'group' => 'general'],
            ['setting' => 'Logo', 'value' => '', 'group' => 'general'],
            ['setting' => 'DefaultLanguage', 'value' => 'en', 'group' => 'general'],
            ['setting' => 'DefaultPaymentMethod', 'value' => 'banktransfer', 'group' => 'general'],
            ['setting' => 'InvoiceNumberFormat', 'value' => 'INV-{year}{month}-{num}', 'group' => 'general'],
            ['setting' => 'DateFormat', 'value' => 'd/m/Y', 'group' => 'general'],
            ['setting' => 'InvoicePayTerms', 'value' => '7', 'group' => 'billing'],
            ['setting' => 'EnableTax', 'value' => 'true', 'group' => 'billing'],
            ['setting' => 'TaxType', 'value' => 'inclusive', 'group' => 'billing'],
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
