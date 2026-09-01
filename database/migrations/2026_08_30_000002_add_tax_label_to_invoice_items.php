<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->string('tax_label')->nullable()->after('tax_rate');
        });
    }

    public function down(): void {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropColumn('tax_label');
        });
    }
};
