<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * The way into a container's terminal.
 *
 * The apps page said advanced settings live in the hosting panel and left it
 * there - no link, and nothing saying that a shell for each container is one of
 * the things waiting behind it.
 */
return new class extends Migration
{
    private array $rows = [
        'hosting.containers.open_panel' => 'Open the hosting panel',
        'hosting.containers.panel_hint' => 'A terminal, logs and advanced settings for each app live in your hosting panel.',
    ];

    public function up(): void
    {
        foreach ($this->rows as $key => $value) {
            $row = DB::table('dynamic_translations')->where('language', 'en')
                ->where('group', 'client')->where('key', $key)->first();
            if ($row === null) {
                DB::table('dynamic_translations')->insert([
                    'language' => 'en', 'group' => 'client', 'key' => $key, 'value' => $value,
                    'is_auto_translated' => false, 'is_reviewed' => true,
                    'created_at' => now(), 'updated_at' => now(),
                ]);

                continue;
            }
            // panel_hint already exists and now says something different: the
            // seeds only ever insert, so a changed wording needs this.
            if ($key === 'hosting.containers.panel_hint') {
                DB::table('dynamic_translations')->where('id', $row->id)
                    ->update(['value' => $value, 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        DB::table('dynamic_translations')->where('language', 'en')->where('group', 'client')
            ->where('key', 'hosting.containers.open_panel')->delete();
    }
};
