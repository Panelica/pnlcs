<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('gateway')->default('banktransfer');
            $table->string('sender_name');
            $table->string('bank_name')->nullable();
            $table->decimal('amount', 10, 2);
            $table->date('transfer_date');
            $table->string('reference')->nullable();
            $table->string('receipt_path')->nullable();
            $table->text('client_note')->nullable();
            $table->string('status')->default('pending'); // pending, approved, rejected
            $table->foreignId('admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->text('admin_note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_notifications');
    }
};
