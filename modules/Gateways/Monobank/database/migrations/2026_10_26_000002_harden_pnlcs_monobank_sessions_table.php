<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('pnlcs_monobank_sessions')) {
            throw new \RuntimeException('Run Monobank session table migration 000001 first.');
        }
        $fields = [
            'status' => fn (Blueprint $t) => $t->string('status', 24)->default('created')->index(),
            'checkout_url' => fn (Blueprint $t) => $t->text('checkout_url')->nullable(),
            'expires_at' => fn (Blueprint $t) => $t->timestamp('expires_at')->nullable()->index(),
            'paid_at' => fn (Blueprint $t) => $t->timestamp('paid_at')->nullable()->index(),
            'refund_status' => fn (Blueprint $t) => $t->string('refund_status', 24)->nullable(),
            'refunded_at' => fn (Blueprint $t) => $t->timestamp('refunded_at')->nullable(),
            'last_provider_modified_at' => fn (Blueprint $t) => $t->timestamp('last_provider_modified_at')->nullable(),
        ];
        foreach ($fields as $column => $add) {
            if (!Schema::hasColumn('pnlcs_monobank_sessions', $column)) {
                Schema::table('pnlcs_monobank_sessions', $add);
            }
        }
    }

    public function down(): void
    {
        // Financial and payment-session data must never be removed by rollback.
    }
};
