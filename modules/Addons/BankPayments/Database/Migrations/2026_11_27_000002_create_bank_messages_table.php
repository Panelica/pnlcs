<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_mailbox_id')->constrained('bank_mailboxes')->cascadeOnDelete();
            $table->unsignedBigInteger('uid');
            $table->unsignedBigInteger('uidvalidity');
            $table->string('message_id')->nullable();
            $table->string('from_email')->nullable();
            $table->string('from_name')->nullable();
            $table->text('subject')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->longText('body_text')->nullable();
            $table->string('content_hash', 64);
            $table->string('status', 20)->default('received');
            $table->text('error')->nullable();
            $table->timestamps();

            $table->unique(['bank_mailbox_id', 'uidvalidity', 'uid']);
            $table->index(['bank_mailbox_id', 'message_id']);
            $table->index(['bank_mailbox_id', 'content_hash']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_messages');
    }
};
