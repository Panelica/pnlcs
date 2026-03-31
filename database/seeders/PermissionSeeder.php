<?php

namespace Database\Seeders;

use App\Constants\Permissions;
use App\Models\AdminRole;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        AdminRole::updateOrCreate(
            ['name' => 'Full Administrator'],
            ['description' => 'Full access to all areas', 'is_full_admin' => true, 'permissions' => Permissions::all()]
        );
        AdminRole::updateOrCreate(
            ['name' => 'Support Agent'],
            ['description' => 'Handle support tickets and view clients', 'is_full_admin' => false,
             'permissions' => [Permissions::LIST_CLIENTS, Permissions::VIEW_CLIENTS, Permissions::LIST_TICKETS, Permissions::VIEW_TICKETS, Permissions::REPLY_TICKETS, Permissions::LIST_SERVICES, Permissions::VIEW_SERVICES, Permissions::LIST_ORDERS, Permissions::VIEW_ORDERS]]
        );
        AdminRole::updateOrCreate(
            ['name' => 'Billing Agent'],
            ['description' => 'Manage invoices, transactions, and billing', 'is_full_admin' => false,
             'permissions' => [Permissions::LIST_CLIENTS, Permissions::VIEW_CLIENTS, Permissions::LIST_INVOICES, Permissions::VIEW_INVOICES, Permissions::CREATE_INVOICES, Permissions::MANAGE_INVOICES, Permissions::LIST_ORDERS, Permissions::VIEW_ORDERS, Permissions::MANAGE_ORDERS, Permissions::LIST_QUOTES, Permissions::MANAGE_QUOTES]]
        );
    }
}
