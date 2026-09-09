<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a legally valid invoice needs from the customer.
 *
 * A Turkish invoice has to carry the buyer's identity: for a company the trade
 * title, tax office and tax number; for an individual their national ID. The
 * clients table already had company_name and tax_id, but nothing recorded
 * WHICH of the two a customer is, and nothing recorded the tax office — so an
 * invoice could be issued with a tax number and no way to tell whether it
 * belonged to a company or a person.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            // 'individual' or 'company'. Nullable rather than defaulted: the
            // accounts that already exist have not been asked yet, and
            // guessing on their behalf would put the wrong thing on a real
            // invoice.
            $table->string('client_type', 20)->nullable()->after('company_name');
            $table->string('tax_office', 100)->nullable()->after('tax_id');
            $table->string('national_id', 20)->nullable()->after('tax_office');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['client_type', 'tax_office', 'national_id']);
        });
    }
};
