<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The domain pricing category for "our own country's extensions" is called
 * "local", whatever the country. An installation that grouped them under the
 * country's own code before this release keeps its rows and its filter.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('domain_pricing')->whereIn('category', ['tr', 'pl', 'de', 'gb', 'us'])->update(['category' => 'local']);
    }

    public function down(): void
    {
        // The original code is not recorded; "local" stays "local".
    }
};
