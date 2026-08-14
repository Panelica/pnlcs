<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshot the buyer's custom fields on the invoice (issue #7 extension).
 *
 * Custom fields flagged "show on invoice" (e.g. NIP) hold buyer data that must
 * be fixed on the invoice date. The other buyer_* columns froze the address and
 * tax id; this one freezes whatever the admin marked for the document, so a
 * customer who later edits their field does not rewrite already-issued invoices.
 *
 * Nullable, nothing backfilled: invoices issued before this migration keep
 * falling back to the live client's custom field values (the model does it),
 * exactly like the earlier buyer columns do for the address.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->json('buyer_custom_fields')->nullable()->after('buyer_tax_exempt');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('buyer_custom_fields');
        });
    }
};