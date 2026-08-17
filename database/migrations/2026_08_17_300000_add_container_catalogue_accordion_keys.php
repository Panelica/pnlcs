<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * Wording for the catalogue accordion.
 *
 * The catalogue sits below the customer's own apps, folded to a peek, so it
 * needs a way to say "there is more here" and a way back.
 */
return new class extends Migration
{
    private array $rows = [
        'hosting.containers.browse_all' => 'Browse all apps',
        'hosting.containers.collapse' => 'Show less',
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
