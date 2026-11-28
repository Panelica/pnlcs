<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_payer_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('bank', 50);
            $table->string('sender_name');
            $table->string('sender_name_normalized', 191);
            $table->string('sender_account', 100)->nullable();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->unsignedInteger('uses')->default(0);
            $table->timestamp('last_matched_at')->nullable();
            $table->timestamps();

            $table->unique(['bank', 'sender_name_normalized']);
            $table->index(['bank', 'sender_account']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_payer_profiles');
    }
};
