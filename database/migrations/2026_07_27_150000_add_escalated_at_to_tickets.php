<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Escalation used tickets.flag as an "already escalated" marker by writing the
 * string "escalated" into it. flag is an integer column holding the assigned
 * admin id (the support widget counts flag > 0 as assigned), so the write was
 * rejected outright under strict mode and, where it did land, made the ticket
 * look assigned. Escalation gets its own timestamp.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tickets', 'escalated_at')) {
            Schema::table('tickets', function (Blueprint $table) {
                $table->timestamp('escalated_at')->nullable()->after('flag');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('tickets', 'escalated_at')) {
            Schema::table('tickets', function (Blueprint $table) {
                $table->dropColumn('escalated_at');
            });
        }
    }
};
