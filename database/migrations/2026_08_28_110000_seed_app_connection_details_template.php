<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// The "email me these details" button binds to this template so the operator
// can edit or switch it off like every other customer email. The seeder covers
// fresh installs; this puts the same row on installs that already exist.
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('email_templates')->where('name', 'App Connection Details')->exists()) {
            return;
        }
        DB::table('email_templates')->insert([
            'type' => 'product',
            'name' => 'App Connection Details',
            'subject' => 'Your {app_name} connection details',
            'message' => "Dear {client_name},\n\nHere are the connection details of the app you installed. Keep this message safe - it contains generated passwords.\n\n{app_details}\n\n{CompanyName}",
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('email_templates')->where('name', 'App Connection Details')->delete();
    }
};
