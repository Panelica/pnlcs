<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ksef_imported_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('ksef_number', 36)->unique();
            $table->string('direction', 10);
            $table->string('invoice_number')->nullable();
            $table->date('issue_date')->nullable();
            $table->date('invoicing_date')->nullable();
            $table->date('permanent_storage_date')->nullable();
            $table->string('seller_nip', 20)->nullable();
            $table->string('seller_name')->nullable();
            $table->string('buyer_nip', 20)->nullable();
            $table->string('buyer_name')->nullable();
            $table->decimal('net', 14, 2)->nullable();
            $table->decimal('vat', 14, 2)->nullable();
            $table->decimal('gross', 14, 2)->nullable();
            $table->string('currency', 3)->nullable();
            $table->json('metadata')->nullable();
            $table->longText('xml')->nullable();
            $table->foreignId('local_invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->timestamp('imported_at');
            $table->timestamps();

            $table->index(['direction', 'issue_date']);
            $table->index(['direction', 'permanent_storage_date']);
            $table->index('local_invoice_id');
        });

        Schema::create('ksef_import_runs', function (Blueprint $table) {
            $table->id();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->string('status', 20)->default('running');
            $table->string('date_type', 20)->nullable();
            $table->timestamp('from_date')->nullable();
            $table->timestamp('to_date')->nullable();
            $table->unsignedInteger('fetched')->default(0);
            $table->unsignedInteger('created')->default(0);
            $table->unsignedInteger('updated')->default(0);
            $table->unsignedInteger('errors')->default(0);
            $table->text('message')->nullable();
            $table->timestamps();

            $table->index('started_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ksef_import_runs');
        Schema::dropIfExists('ksef_imported_invoices');
    }
};
