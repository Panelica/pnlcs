<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * English base strings for the client hosting live dashboard (phase 2:
 * per-account CPU/RAM gauges + domain list). Idempotent, DB-backed like the
 * rest of the client translations.
 */
return new class extends Migration
{
    private function rows(): array
    {
        return [
            'hosting.dashboard.cpu' => 'CPU',
            'hosting.dashboard.ram' => 'Memory',
            'hosting.dashboard.domains' => 'Domains',
            'hosting.dashboard.no_domains' => 'No domains yet.',
            'hosting.dashboard.as_of' => 'as of',
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
                    'language' => 'en', 'group' => 'client', 'key' => $key, 'value' => $value,
                    'is_auto_translated' => false, 'is_reviewed' => true,
                    'created_at' => $now, 'updated_at' => $now,
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
