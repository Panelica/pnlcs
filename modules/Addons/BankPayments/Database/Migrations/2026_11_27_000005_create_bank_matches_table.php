<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_transaction_id')->constrained('bank_transactions')->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->unsignedTinyInteger('rank')->default(1);
            $table->unsignedSmallInteger('score')->default(0);
            $table->decimal('amount', 14, 2);
            $table->json('criteria')->nullable();
            $table->string('status', 20)->default('proposed');
            $table->text('reason')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['bank_transaction_id', 'invoice_id']);
            $table->index(['bank_transaction_id', 'rank']);
            $table->index(['status', 'created_at']);
            $table->index('invoice_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_matches');
    }
};
