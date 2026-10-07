<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Can run twice without harm (RELEASING.md).
        if (Schema::hasColumn('whmcs_import_logs', 'skipped_details')) {
            return;
        }

        Schema::table('whmcs_import_logs', function (Blueprint $table) {
            $table->json('skipped_details')->nullable()->after('error_details');
        });
    }

    public function down(): void
    {
        Schema::table('whmcs_import_logs', function (Blueprint $table) {
            $table->dropColumn('skipped_details');
        });
    }
};
