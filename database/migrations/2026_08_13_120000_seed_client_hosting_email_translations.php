<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * English base strings for the client hosting area (phase 1: email accounts).
 *
 * PNLCS resolves UI strings from the dynamic_translations table (DB overrides
 * the file loader), so a new client-facing key has to exist here as an English
 * row before other languages can be auto-translated from it. Idempotent: an
 * existing key is left untouched so a customised translation is never clobbered.
 */
return new class extends Migration
{
    private function rows(): array
    {
        return [
            'hosting.title' => 'Hosting Management',
            'hosting.unavailable' => 'This action is not available for this service.',
            'hosting.email.title' => 'Email Accounts',
            'hosting.email.subtitle' => 'Create and manage mailboxes for your domains.',
            'hosting.email.mailboxes' => 'mailboxes',
            'hosting.email.no_domains' => 'No domains are set up on this service yet.',
            'hosting.email.create_title' => 'Create Mailbox',
            'hosting.email.mailbox_name' => 'Mailbox name',
            'hosting.email.domain' => 'Domain',
            'hosting.email.password' => 'Password',
            'hosting.email.quota_mb' => 'Quota (MB)',
            'hosting.email.create_button' => 'Create',
            'hosting.email.accounts_title' => 'Mailboxes',
            'hosting.email.address' => 'Address',
            'hosting.email.usage' => 'Usage',
            'hosting.email.unlimited' => 'Unlimited',
            'hosting.email.empty' => 'No mailboxes yet.',
            'hosting.email.change_password' => 'Password',
            'hosting.email.new_password' => 'New password',
            'hosting.email.save' => 'Save',
            'hosting.email.delete_confirm' => 'Delete this mailbox? This cannot be undone.',
        ];
    }

    public function up(): void
    {
        $now = now();
        foreach ($this->rows() as $key => $value) {
            $exists = DB::table('dynamic_translations')
                ->where('language', 'en')->where('group', 'client')->where('key', $key)
                ->exists();
            if (! $exists) {
                DB::table('dynamic_translations')->insert([
                    'language' => 'en',
                    'group' => 'client',
                    'key' => $key,
                    'value' => $value,
                    'is_auto_translated' => false,
                    'is_reviewed' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('dynamic_translations')
            ->where('language', 'en')->where('group', 'client')
            ->whereIn('key', array_keys($this->rows()))
            ->delete();
    }
};
