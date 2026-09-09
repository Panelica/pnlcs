<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// The verification email is edited from the templates screen like every other
// customer email. The seeder covers fresh installs; this puts the same row on
// installs that already exist.
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('email_templates')->where('name', 'Email Verification')->exists()) {
            return;
        }

        DB::table('email_templates')->insert([
            'type' => 'general',
            'name' => 'Email Verification',
            'subject' => 'Confirm your email address - {CompanyName}',
            'message' => "Dear {client_name},\n\nPlease confirm this email address so we can send you invoices and account notices.\n\nConfirm here: {verify_url}\n\nThe link is valid for 24 hours.\n\n{CompanyName}",
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('email_templates')->where('name', 'Email Verification')->delete();
    }
};
