<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// The two emails the charger sends bind to these templates through
// EmailTemplateService::MAP, so that an operator can reword them or switch them
// off like every other customer email. EmailTemplateSeeder covers fresh
// installs and is never re-run on an existing one, which would have left every
// shop already in service with two customer emails it could not edit. Same
// shape as the App Connection Details and Email Verification migrations beside
// this one.
return new class extends Migration
{
    private const TEMPLATES = [
        [
            'type' => 'invoice',
            'name' => 'Automatic Payment Failed',
            'subject' => 'We could not take payment for invoice #{invoice_num} - {CompanyName}',
            'message' => "Dear {client_name},\n\nWe tried to take {invoice_total} for invoice #{invoice_num} from the card you have with us, and it was not accepted.\n\nYou can pay the invoice, or store a different card, at {whmcs_url}\n\n{CompanyName}",
        ],
        [
            'type' => 'invoice',
            'name' => 'Automatic Payment Authentication Required',
            'subject' => 'Your bank wants you to confirm a payment - invoice #{invoice_num}',
            'message' => "Dear {client_name},\n\nYour bank asked for your confirmation before releasing {invoice_total} for invoice #{invoice_num}. Nothing has been taken from your card.\n\nOpen the invoice at {whmcs_url} and use the \"Confirm with your bank\" button on it. That finishes the payment we already started, so you are not charged twice.\n\n{CompanyName}",
        ],
    ];

    public function up(): void
    {
        foreach (self::TEMPLATES as $template) {
            // Never overwrite wording an operator has already chosen.
            if (DB::table('email_templates')->where('name', $template['name'])->exists()) {
                continue;
            }

            DB::table('email_templates')->insert($template + [
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('email_templates')
            ->whereIn('name', array_column(self::TEMPLATES, 'name'))
            ->delete();
    }
};
