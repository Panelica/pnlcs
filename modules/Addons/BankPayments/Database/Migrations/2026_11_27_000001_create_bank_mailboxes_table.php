<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_mailboxes', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('host');
            $table->unsignedSmallInteger('port')->default(993);
            $table->string('encryption', 10)->default('ssl');
            $table->string('username');
            $table->text('password');
            $table->string('folder')->default('INBOX');
            $table->string('bank', 50)->default('nest_bank');
            $table->string('parser', 50)->default('nest_bank');
            $table->boolean('active')->default(true);
            $table->boolean('validate_cert')->default(true);
            $table->unsignedBigInteger('last_uid')->nullable();
            $table->unsignedBigInteger('last_uidvalidity')->nullable();
            $table->timestamp('last_sync_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index('active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_mailboxes');
    }
};
