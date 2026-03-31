<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\AdminRole;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Default Full Administrator role
        $role = AdminRole::firstOrCreate(
            ["name" => "Full Administrator"],
            [
                "description" => "Full access to all areas",
                "is_full_admin" => true,
            ]
        );

        // Default admin user
        Admin::firstOrCreate(
            ["username" => "admin"],
            [
                "role_id" => $role->id,
                "email" => "admin@pnlcs.com",
                "password" => Hash::make("admin123"),
                "first_name" => "System",
                "last_name" => "Administrator",
            ]
        );
    }
}
