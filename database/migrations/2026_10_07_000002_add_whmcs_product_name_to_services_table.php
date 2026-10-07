<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Can run twice without harm (RELEASING.md).
        if (Schema::hasColumn('services', 'whmcs_product_name')) {
            return;
        }

        Schema::table('services', function (Blueprint $table) {
            $table->string('whmcs_product_name')->nullable()->after('product_id');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('whmcs_product_name');
        });
    }
};
