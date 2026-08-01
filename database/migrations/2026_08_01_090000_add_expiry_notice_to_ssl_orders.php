<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('ssl_orders', 'expiry_notice_days')) {
            Schema::table('ssl_orders', function (Blueprint $table) {
                // The nearest threshold already sent for the current expiry
                // date. Without it the command could only match an exact day,
                // and a missed run lost that notice for good.
                $table->unsignedSmallInteger('expiry_notice_days')->nullable()->after('status');
                $table->timestamp('expiry_notice_sent_at')->nullable()->after('expiry_notice_days');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('ssl_orders', 'expiry_notice_days')) {
            Schema::table('ssl_orders', function (Blueprint $table) {
                $table->dropColumn(['expiry_notice_days', 'expiry_notice_sent_at']);
            });
        }
    }
};
