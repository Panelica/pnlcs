<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * An affiliate's own word for their link (?ref=ayse) instead of the row id
 * (?ref=12). Optional and unique; the numeric link keeps working.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('affiliates', 'code')) {
            Schema::table('affiliates', function (Blueprint $table) {
                $table->string('code', 32)->nullable()->unique()->after('client_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('affiliates', 'code')) {
            Schema::table('affiliates', function (Blueprint $table) {
                $table->dropUnique(['code']);
                $table->dropColumn('code');
            });
        }
    }
};
