<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// The code that confirms a sensitive action binds to this template through
// EmailTemplateService::MAP, so an operator can reword it like every other
// customer email. EmailTemplateSeeder covers fresh installs; this puts the
// same row on installs that already exist.
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('email_templates')->where('name', 'Confirmation Code')->exists()) {
            return;
        }

        DB::table('email_templates')->insert([
            'type' => 'general',
            'name' => 'Confirmation Code',
            'subject' => 'Your confirmation code - {CompanyName}',
            'message' => "Dear {client_name},\n\nUse this code to confirm the action you started in your account:\n\n{confirmation_code}\n\nThe code is valid for {code_minutes} minutes. If you did not ask for it, someone may be signed in to your account: change your password.\n\n{CompanyName}",
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('email_templates')->where('name', 'Confirmation Code')->delete();
    }
};
