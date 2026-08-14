<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Freeze the buyer on the invoice (issue #7).
 *
 * An invoice already snapshots its money — subtotal, tax, tax2, tax_rate, total
 * and every line item. The buyer was not snapshotted: the PDF, the admin view
 * and the client view all read name/company/address/tax id live from the client
 * record. So a customer who moves house rewrites the address on every invoice
 * they were ever issued, including ones already filed with an accountant. For
 * VAT the buyer's address and tax ID must be the ones that applied on the
 * invoice date.
 *
 * All columns are nullable and nothing is backfilled: invoices issued before
 * this migration keep rendering from the live client record exactly as they do
 * today (the views fall back), because guessing at historical buyer data would
 * be worse than the current behaviour.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('buyer_first_name')->nullable()->after('client_id');
            $table->string('buyer_last_name')->nullable()->after('buyer_first_name');
            $table->string('buyer_company_name')->nullable()->after('buyer_last_name');
            $table->string('buyer_email')->nullable()->after('buyer_company_name');
            $table->string('buyer_address1')->nullable()->after('buyer_email');
            $table->string('buyer_address2')->nullable()->after('buyer_address1');
            $table->string('buyer_city')->nullable()->after('buyer_address2');
            $table->string('buyer_state')->nullable()->after('buyer_city');
            $table->string('buyer_postcode', 32)->nullable()->after('buyer_state');
            $table->string('buyer_country', 64)->nullable()->after('buyer_postcode');
            $table->string('buyer_tax_id', 64)->nullable()->after('buyer_country');
            // Whether the buyer was tax exempt AT ISSUE TIME — the flag drifts
            // and silently changes how a historical invoice reads.
            $table->boolean('buyer_tax_exempt')->nullable()->after('buyer_tax_id');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn([
                'buyer_first_name', 'buyer_last_name', 'buyer_company_name', 'buyer_email',
                'buyer_address1', 'buyer_address2', 'buyer_city', 'buyer_state',
                'buyer_postcode', 'buyer_country', 'buyer_tax_id', 'buyer_tax_exempt',
            ]);
        });
    }
};
