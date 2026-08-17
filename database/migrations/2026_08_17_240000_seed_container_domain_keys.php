<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/*
 * Three gaps a customer hit in the same sitting: pressing Install did nothing
 * visible for minutes, an app stuck in a restart loop just said "restarting",
 * and there was no way at all to serve an installed app on their own domain.
 */
return new class extends Migration
{
    private function rows(): array
    {
        return [
            'hosting.containers.installing' => 'Installing...',
            'hosting.containers.installing_note' => 'This downloads the app and can take a few minutes for larger ones. You can leave this page - the install carries on.',
            'hosting.containers.crashing' => 'Not starting',
            'hosting.containers.crashing_hint' => 'The app keeps restarting, usually because it needs configuration or more memory than your plan allows. Remove it and try a smaller one, or open a ticket.',
            'hosting.containers.domain_link' => 'Point here',
            'hosting.containers.domain_unlink' => 'Stop serving this app on this domain',
            'hosting.containers.domain_needs_running' => 'Start the app to serve it on one of your domains.',
            'hosting.containers.domain_none' => 'Add a domain to this account to serve this app on it.',
        ];
    }

    public function up(): void
    {
        $now = now();
        foreach ($this->rows() as $key => $value) {
            if (! DB::table('dynamic_translations')->where('language', 'en')->where('group', 'client')->where('key', $key)->exists()) {
                DB::table('dynamic_translations')->insert(['language' => 'en', 'group' => 'client', 'key' => $key, 'value' => $value, 'is_auto_translated' => false, 'is_reviewed' => true, 'created_at' => $now, 'updated_at' => $now]);
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
        DB::table('dynamic_translations')->where('language', 'en')->where('group', 'client')->whereIn('key', array_keys($this->rows()))->delete();
    }
};
