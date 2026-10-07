<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Can run twice without harm (RELEASING.md).
        if (Schema::hasColumn('whmcs_import_profiles', 'transforms')) {
            return;
        }

        Schema::table('whmcs_import_profiles', function (Blueprint $table) {
            $table->json('transforms')->nullable()->after('constants');
        });
    }

    public function down(): void
    {
        Schema::table('whmcs_import_profiles', function (Blueprint $table) {
            $table->dropColumn('transforms');
        });
    }
};
