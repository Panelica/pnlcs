<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The verification mail carries a code beside the link, and the link no
 * longer signs anybody in. The template text an operator sees and starts from
 * says so. Only rows that are still exactly a shipped text are changed - in
 * every language, since a language without a translation holds the English
 * copy - so a message the operator wrote stays, and running it again changes
 * nothing.
 */
return new class extends Migration
{
    /** English, then German: [shipped before, shipped now]. */
    private const TEXTS = [
        [
            "Dear {client_name},\n\nPlease confirm this email address so we can send you invoices and account notices.\n\nConfirm here: {verify_url}\n\nThe link is valid for 24 hours.\n\n{CompanyName}",
            "Dear {client_name},\n\nPlease confirm this email address so we can send you invoices and account notices.\n\nYour confirmation code: {verification_code}\nEnter it on the page that asked for it. It is valid for {code_minutes} minutes.\n\nOr confirm here: {verify_url}\nThe link is valid for 24 hours and confirms the address only.\n\nDo not share this code or link with anyone.\n\n{CompanyName}",
        ],
        [
            "Guten Tag {client_name},\n\nBitte bestätigen Sie Ihre E-Mail-Adresse, damit wir Ihnen Rechnungen und wichtige Mitteilungen zu Ihrem Kundenkonto senden können.\n\nE-Mail-Adresse bestätigen: {verify_url}\n\nDieser Link ist 24 Stunden gültig.\n\nMit freundlichen Grüßen\nIhr {CompanyName}-Team",
            "Guten Tag {client_name},\n\nBitte bestätigen Sie Ihre E-Mail-Adresse, damit wir Ihnen Rechnungen und wichtige Mitteilungen zu Ihrem Kundenkonto senden können.\n\nIhr Bestätigungscode: {verification_code}\nGeben Sie ihn auf der Seite ein, die danach gefragt hat. Er ist {code_minutes} Minuten gültig.\n\nOder hier bestätigen: {verify_url}\nDieser Link ist 24 Stunden gültig und bestätigt nur die Adresse.\n\nGeben Sie diesen Code und Link an niemanden weiter.\n\nMit freundlichen Grüßen\nIhr {CompanyName}-Team",
        ],
    ];

    public function up(): void
    {
        foreach (self::TEXTS as [$old, $new]) {
            $this->replace($old, $new);
        }
    }

    public function down(): void
    {
        foreach (self::TEXTS as [$old, $new]) {
            $this->replace($new, $old);
        }
    }

    private function replace(string $from, string $to): void
    {
        DB::table('email_templates')
            ->where('name', 'Email Verification')
            ->where('message', $from)
            ->update(['message' => $to, 'updated_at' => now()]);
    }
};
