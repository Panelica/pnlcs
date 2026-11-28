<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_mailbox_id')->constrained('bank_mailboxes')->cascadeOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->string('status', 20)->default('running');
            $table->unsignedInteger('fetched')->default(0);
            $table->unsignedInteger('parsed')->default(0);
            $table->unsignedInteger('new_transactions')->default(0);
            $table->unsignedInteger('duplicates')->default(0);
            $table->unsignedInteger('errors')->default(0);
            $table->text('message')->nullable();
            $table->timestamps();

            $table->index(['bank_mailbox_id', 'started_at']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_sync_logs');
    }
};
