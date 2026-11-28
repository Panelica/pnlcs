<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_match_id')->unique()->constrained('bank_matches')->cascadeOnDelete();
            $table->foreignId('bank_transaction_id')->constrained('bank_transactions')->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->string('source', 20)->default('manual');
            $table->string('gateway', 50)->default('banktransfer');
            $table->string('payment_reference')->unique();
            $table->decimal('amount', 14, 2);
            $table->string('status', 20)->default('pending');
            $table->json('response')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('bank_transaction_id');
            $table->index('invoice_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_imports');
    }
};
