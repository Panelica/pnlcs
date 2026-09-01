<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ksef_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            // Status of the KSeF submission: pending, sent, accepted, rejected, error, corrected
            $table->string('status', 20)->default('pending');
            // The KSeF reference number returned after acceptance.
            $table->string('ksef_number', 64)->nullable();
            // The correction invoice that replaces this one (korekta).
            $table->unsignedBigInteger('corrected_by_invoice_id')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->text('error_message')->nullable();
            $table->text('request_xml')->nullable();
            $table->text('response_xml')->nullable();
            $table->timestamps();

            $table->unique('invoice_id');
            $table->index('status');
            $table->index('ksef_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ksef_invoices');
    }
};
