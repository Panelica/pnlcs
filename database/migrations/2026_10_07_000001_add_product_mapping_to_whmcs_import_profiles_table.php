<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Can run twice without harm (RELEASING.md).
        if (Schema::hasColumn('whmcs_import_profiles', 'product_mapping')) {
            return;
        }

        Schema::table('whmcs_import_profiles', function (Blueprint $table) {
            $table->json('product_mapping')->nullable()->after('transforms');
        });
    }

    public function down(): void
    {
        Schema::table('whmcs_import_profiles', function (Blueprint $table) {
            $table->dropColumn('product_mapping');
        });
    }
};
