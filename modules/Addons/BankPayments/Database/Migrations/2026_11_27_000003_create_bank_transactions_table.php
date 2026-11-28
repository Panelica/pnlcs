<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_message_id')->constrained('bank_messages')->cascadeOnDelete();
            $table->string('bank', 50);
            $table->string('operation_type', 50)->nullable();
            $table->date('transaction_date')->nullable();
            $table->decimal('amount', 14, 2);
            $table->string('currency', 3)->default('PLN');
            $table->string('sender_name')->nullable();
            $table->string('sender_account', 64)->nullable();
            $table->string('recipient_account', 64)->nullable();
            $table->text('reference')->nullable();
            $table->json('raw_payload')->nullable();
            $table->string('fingerprint', 64);
            $table->timestamps();

            $table->unique(['bank_message_id', 'fingerprint']);
            $table->index('transaction_date');
            $table->index(['currency', 'amount']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_transactions');
    }
};
