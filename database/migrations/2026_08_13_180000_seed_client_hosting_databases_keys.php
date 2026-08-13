<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** English strings for the client Databases tab. Seeds + flushes the cache. */
return new class extends Migration
{
    private function rows(): array
    {
        return [
            'hosting.databases.title' => 'Databases',
            'hosting.databases.subtitle' => 'MySQL databases and users for your domains.',
            'hosting.databases.create_title' => 'Create Database',
            'hosting.databases.domain' => 'Domain',
            'hosting.databases.db_name' => 'Database name',
            'hosting.databases.db_user' => 'Username',
            'hosting.databases.password' => 'Password',
            'hosting.databases.create' => 'Create',
            'hosting.databases.no_domains' => 'No domains on this service yet.',
            'hosting.databases.empty' => 'No databases yet.',
            'hosting.databases.add_user' => 'Add User',
            'hosting.databases.new_user' => 'Username',
            'hosting.databases.role' => 'Role',
            'hosting.databases.primary' => 'primary',
            'hosting.databases.change_password' => 'Password',
            'hosting.databases.new_password' => 'New password',
            'hosting.databases.save' => 'Save',
            'hosting.databases.delete' => 'Delete',
            'hosting.databases.delete_db_confirm' => 'Delete this database and all its data? This cannot be undone.',
            'hosting.databases.delete_user_confirm' => 'Delete this database user?',
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
