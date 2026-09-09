<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The currency the stored amounts are actually in.
     *
     * Amounts were printed with whatever currency the shop happened to be set
     * to at the time of printing, so changing the shop currency silently
     * reinterpreted every past invoice - a 264.89 lira invoice reprinted as
     * 264.89 dollars. Recording the currency alongside the number is the fix:
     * an invoice is then readable forever, whatever the shop does later.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('source_currency', 3)->nullable()->after('total');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('source_currency');
        });
    }
};
