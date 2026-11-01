<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A product may keep its own suspension and termination days; empty, the
 * general settings (AutoSuspensionDays, AutoTermination*) apply as before.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedSmallInteger('suspend_after_days')->nullable()->after('auto_setup');
            $table->unsignedSmallInteger('terminate_after_days')->nullable()->after('suspend_after_days');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['suspend_after_days', 'terminate_after_days']);
        });
    }
};
