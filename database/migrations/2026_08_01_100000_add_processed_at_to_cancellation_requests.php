<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('cancellation_requests', 'processed_at')) {
            Schema::table('cancellation_requests', function (Blueprint $table) {
                // Nothing ever closed a request, so a reinstated service was
                // cancelled again on the next run and the customer could never
                // ask a second time.
                $table->timestamp('processed_at')->nullable()->after('reason');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('cancellation_requests', 'processed_at')) {
            Schema::table('cancellation_requests', function (Blueprint $table) {
                $table->dropColumn('processed_at');
            });
        }
    }
};
