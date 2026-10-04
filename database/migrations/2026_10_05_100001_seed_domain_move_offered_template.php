<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// The offer of a domain to another client account binds to this template
// through EmailTemplateService::MAP, so an operator can reword it like every
// other customer email. EmailTemplateSeeder covers fresh installs; this puts
// the same row on installs that already exist.
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('email_templates')->where('name', 'Domain Move Offered')->exists()) {
            return;
        }

        DB::table('email_templates')->insert([
            'type' => 'domain',
            'name' => 'Domain Move Offered',
            'subject' => 'Domain {domain} offered to you - {CompanyName}',
            'message' => "Dear {client_name},\n\n{from_name} would like to give you the domain {domain}.\n\nSign in and accept or decline it on your Domains page by {expires_on}:\n{client_area_url}/domains\n\nIf you did not expect this, you can ignore it or decline it.\n\n{CompanyName}",
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('email_templates')->where('name', 'Domain Move Offered')->delete();
    }
};
