<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// The new-device warning binds to this template through
// EmailTemplateService::MAP, so an operator can reword it or switch it off
// like every other customer email. EmailTemplateSeeder covers fresh installs;
// this puts the same row on installs that already exist.
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('email_templates')->where('name', 'New Device Sign-in')->exists()) {
            return;
        }

        DB::table('email_templates')->insert([
            'type' => 'general',
            'name' => 'New Device Sign-in',
            'subject' => 'New sign-in to your {CompanyName} account',
            'message' => "Dear {client_name},\n\nYour account was signed in to from a device we have not seen before.\n\nWhen: {login_time}\nDevice: {login_device}\nIP address: {login_ip}\n\nIf this was you, there is nothing to do. If it was not, change your password at {whmcs_url} and sign out the sessions you do not recognise on the Security page.\n\n{CompanyName}",
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('email_templates')->where('name', 'New Device Sign-in')->delete();
    }
};
