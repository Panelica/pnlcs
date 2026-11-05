<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pnlcs_monobank_sessions')) return;
        Schema::create('pnlcs_monobank_sessions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('mono_invoice_id', 128)->unique();
            $table->unsignedBigInteger('invoice_id')->index();
            $table->char('invoice_currency', 3);
            $table->unsignedBigInteger('invoice_amount_minor');
            $table->unsignedInteger('payment_currency');
            $table->unsignedBigInteger('payment_amount_minor');
            $table->decimal('exchange_rate', 18, 8);
            $table->string('merchant_token_hash', 64);
            $table->string('refund_ext_ref', 64)->nullable();
            $table->timestamp('refund_requested_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        // Intentionally keep financial records even if the module is disabled.
        // Never automatically drop a payment ledger in a rollback.
    }
};
