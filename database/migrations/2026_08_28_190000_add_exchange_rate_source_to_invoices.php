<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Where the rate came from, so the customer can check it.
     *
     * A lira figure converted from a dollar price is only trustworthy if the
     * document names the authority, the day and the bulletin behind it.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('exchange_rate_source', 32)->nullable()->after('exchange_rate');
            $table->string('exchange_rate_kind', 24)->nullable()->after('exchange_rate_source');
            $table->string('exchange_rate_date', 12)->nullable()->after('exchange_rate_kind');
            $table->string('exchange_rate_ref', 32)->nullable()->after('exchange_rate_date');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['exchange_rate_source', 'exchange_rate_kind', 'exchange_rate_date', 'exchange_rate_ref']);
        });
    }
};
