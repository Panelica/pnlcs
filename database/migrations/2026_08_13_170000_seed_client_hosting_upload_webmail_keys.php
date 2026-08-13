<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Strings for the file manager upload feature + the email webmail button.
 * Seeds English keys and flushes the cached translation groups so they show
 * immediately after an update (see 2026_08_13_150000_flush_stale_translation_cache).
 */
return new class extends Migration
{
    private function rows(): array
    {
        return [
            'hosting.files.items' => 'items',
            'hosting.files.upload' => 'Upload',
            'hosting.files.uploading' => 'Uploading…',
            'hosting.files.upload_done' => 'Done',
            'hosting.files.drop_here' => 'Drop files here to upload',
            'hosting.email.webmail' => 'Open Webmail',
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
