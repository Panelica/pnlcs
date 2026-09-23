<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * One-time sign-in links for the client area (the API's createssotoken).
 * Only a hash of the token is kept; the link works once, for a minute.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_sso_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('token_hash', 64)->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->string('redirect_path', 255)->default('/client');
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_sso_tokens');
    }
};
