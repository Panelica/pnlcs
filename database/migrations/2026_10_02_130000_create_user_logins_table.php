<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Every sign-in to the client area, and every wrong password for an
 * existing login: when, from where, with what, and whether it worked.
 *
 * users.last_login / last_login_ip keep only the latest one, so a customer
 * could not see that someone else had been in, and nothing could tell a new
 * device from one seen before. `device` is a hash of a random token kept in
 * a long-lived cookie on that browser - never the token itself.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('user_logins')) {
            return;
        }

        Schema::create('user_logins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('successful');
            $table->string('method', 20)->default('password');
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->string('device', 64)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['user_id', 'created_at']);
            $table->index(['user_id', 'device']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_logins');
    }
};
