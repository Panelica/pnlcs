<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** English strings for the client FTP tab. Seeds + flushes cache. */
return new class extends Migration
{
    private function rows(): array
    {
        return [
            'hosting.ftp.title' => 'FTP Accounts',
            'hosting.ftp.subtitle' => 'Manage FTP access to your hosting files.',
            'hosting.ftp.accounts' => 'FTP Accounts',
            'hosting.ftp.create_title' => 'Create FTP Account',
            'hosting.ftp.username' => 'Username',
            'hosting.ftp.directory' => 'Access directory',
            'hosting.ftp.home_default' => 'Account home (all files)',
            'hosting.ftp.password' => 'Password',
            'hosting.ftp.quota_mb' => 'Quota (MB)',
            'hosting.ftp.create' => 'Create',
            'hosting.ftp.empty' => 'No FTP accounts yet.',
            'hosting.ftp.home' => 'Directory',
            'hosting.ftp.usage' => 'Usage',
            'hosting.ftp.change_password' => 'Password',
            'hosting.ftp.new_password' => 'New password',
            'hosting.ftp.save' => 'Save',
            'hosting.ftp.delete' => 'Delete',
            'hosting.ftp.delete_confirm' => 'Delete this FTP account?',
            'hosting.ftp.plan_disabled' => 'Your plan does not include FTP account creation.',
            'hosting.ftp.limit_reached' => 'You have reached your plan\'s FTP account limit.',
        ];
    }

    public function up(): void
    {
        $now = now();
        foreach ($this->rows() as $key => $value) {
            $exists = DB::table('dynamic_translations')
                ->where('language', 'en')->where('group', 'client')->where('key', $key)->exists();
            if (! $exists) {
                DB::table('dynamic_translations')->insert([
                    'language' => 'en', 'group' => 'client', 'key' => $key, 'value' => $value,
                    'is_auto_translated' => false, 'is_reviewed' => true,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }
        try {
            foreach (DB::table('dynamic_translations')->distinct()->pluck('language') as $lang) {
                Cache::forget("translations:{$lang}:client");
            }
        } catch (\Throwable $e) {
        }
    }

    public function down(): void
    {
        DB::table('dynamic_translations')
            ->where('language', 'en')->where('group', 'client')
            ->whereIn('key', array_keys($this->rows()))->delete();
    }
};
