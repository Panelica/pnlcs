<?php

use App\Models\EmailTemplate;
use Illuminate\Database\Migrations\Migration;

// Staff asking a customer to add a card binds to this template through
// EmailTemplateService::MAP, so an operator can reword it like every other
// customer email. EmailTemplateSeeder covers fresh installs; this puts the
// same row on installs that already exist. Created through the model, not
// DB::table, so EmailTemplateObserver gives every other language its copy
// (the shipped translation where there is one) and a customer in another
// language is not sent the English subject line.
return new class extends Migration
{
    public function up(): void
    {
        if (EmailTemplate::where('name', 'Card Setup Request')->where('language', 'en')->exists()) {
            return;
        }

        EmailTemplate::create([
            'type' => 'invoice',
            'name' => 'Card Setup Request',
            'language' => 'en',
            'subject' => 'Please add a card to your account - {CompanyName}',
            'message' => "Dear {client_name},\n\nPlease add a card to your account so your invoices can be paid with it. Only you can add the card: you enter it yourself on our payment provider's secure form, and our staff never see it.\n\n{add_card_url}\n\nIf you did not expect this, you can ignore this email; nothing changes on your account.\n\n{CompanyName}",
        ]);
    }

    public function down(): void
    {
        EmailTemplate::where('name', 'Card Setup Request')->delete();
    }
};
