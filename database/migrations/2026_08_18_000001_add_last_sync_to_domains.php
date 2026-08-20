<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('domains', function (Blueprint $table) {
            $table->timestamp('last_sync_at')->nullable()->after('notes');
            $table->string('last_sync_status')->nullable()->after('last_sync_at');
        });
    }

    public function down(): void {
        Schema::table('domains', function (Blueprint $table) {
            $table->dropColumn(['last_sync_at', 'last_sync_status']);
        });
    }
};
