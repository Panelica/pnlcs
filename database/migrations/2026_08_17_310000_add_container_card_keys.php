<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * Wording for the app cards: the domain column and the copy button.
 */
return new class extends Migration
{
    private array $rows = [
        'hosting.containers.domain' => 'Domain',
        'hosting.containers.copy' => 'Copy',
    ];

    public function up(): void
    {
        foreach ($this->rows as $key => $value) {
            $exists = DB::table('dynamic_translations')->where('language', 'en')
                ->where('group', 'client')->where('key', $key)->exists();
            if ($exists) {
                continue;
            }
            DB::table('dynamic_translations')->insert([
                'language' => 'en', 'group' => 'client', 'key' => $key, 'value' => $value,
                'is_auto_translated' => false, 'is_reviewed' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('dynamic_translations')->where('language', 'en')->where('group', 'client')
            ->whereIn('key', array_keys($this->rows))->delete();
    }
};
