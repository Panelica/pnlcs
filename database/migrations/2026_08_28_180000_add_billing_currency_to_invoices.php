<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The shop prices in USD but bills Turkish customers in lira, so every
     * invoice has to remember the rate it was raised at. Without it a document
     * reprinted months later would show today's lira figure against an old
     * dollar total.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('billing_currency', 3)->nullable()->after('total');
            $table->decimal('exchange_rate', 15, 6)->nullable()->after('billing_currency');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['billing_currency', 'exchange_rate']);
        });
    }
};
