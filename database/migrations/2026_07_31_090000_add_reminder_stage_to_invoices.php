<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('invoices', 'reminder_stage')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->string('reminder_stage', 20)->nullable()->after('status');
                $table->timestamp('reminder_sent_at')->nullable()->after('reminder_stage');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('invoices', 'reminder_stage')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->dropColumn(['reminder_stage', 'reminder_sent_at']);
            });
        }
    }
};
