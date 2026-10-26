<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How an invoice was taxed, fixed when it is issued: null (tax added on top,
 * every existing invoice), 'inclusive' (prices included it) or 'none' (the
 * shop was not charging tax). The totals, the VAT rows on the PDF and the
 * pages, and KSeF read it, so an invoice reads the same after the shop's tax
 * settings change.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('invoices', 'tax_mode')) {
            return;
        }

        Schema::table('invoices', function (Blueprint $table) {
            $table->string('tax_mode', 16)->nullable()->after('tax_rate2');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('invoices', 'tax_mode')) {
            return;
        }

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('tax_mode');
        });
    }
};
