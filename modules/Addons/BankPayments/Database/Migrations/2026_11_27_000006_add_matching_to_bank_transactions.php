<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_transactions', function (Blueprint $table) {
            $table->string('matching_status', 30)->default('pending')->after('fingerprint');
            $table->unsignedSmallInteger('matching_score')->nullable()->after('matching_status');
            $table->text('matching_reason')->nullable()->after('matching_score');
            $table->timestamp('matched_at')->nullable()->after('matching_reason');

            $table->index('matching_status');
        });
    }

    public function down(): void
    {
        Schema::table('bank_transactions', function (Blueprint $table) {
            $table->dropIndex(['matching_status']);
            $table->dropColumn(['matching_status', 'matching_score', 'matching_reason', 'matched_at']);
        });
    }
};
