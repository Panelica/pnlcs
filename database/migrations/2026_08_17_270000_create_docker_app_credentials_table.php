<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Where a customer finds out how to reach the app they just installed.
 *
 * The panel produces this at deploy time - the address, and for apps that
 * generate one, the first login - and then it is gone: the response is read
 * once and the panel does not hand it out again. So a customer who installed
 * n8n had it running and no idea what URL to open or what password it made.
 *
 * Kept per service so it disappears with the account, and encrypted because it
 * holds first-login passwords.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('docker_app_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->string('container_id', 100)->index();
            $table->string('container_name', 120);
            $table->string('slug', 100);
            $table->text('payload');   // encrypted: access_url, credentials, notes
            $table->timestamps();

            $table->unique(['service_id', 'container_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('docker_app_credentials');
    }
};
