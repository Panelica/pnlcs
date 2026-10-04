<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * A customer offers a domain to another client account; the other account
 * accepts or declines. Nothing moves until it accepts.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('domain_move_requests')) {
            return;
        }
        Schema::create('domain_move_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('domain_id')->constrained('domains')->cascadeOnDelete();
            $table->unsignedBigInteger('from_client_id')->index();
            $table->unsignedBigInteger('to_client_id')->index();
            $table->string('status', 20)->default('pending')->index(); // pending|accepted|declined|cancelled
            $table->timestamp('expires_at');
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domain_move_requests');
    }
};
