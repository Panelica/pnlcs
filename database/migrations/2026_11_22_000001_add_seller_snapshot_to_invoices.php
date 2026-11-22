<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Freeze the seller on the invoice, and keep invoices when a client is deleted.
 *
 * The buyer and the money were frozen in issue #7, but the seller block (name,
 * address, tax ids, logo, bank accounts) was still read live from the settings
 * at print time. Changing the company details rewrote every invoice ever
 * issued, including ones already filed. A VAT document has to keep the seller
 * that issued it, the same way it keeps the buyer.
 *
 * All seller columns are nullable and nothing is backfilled: invoices issued
 * before this migration keep rendering from the live settings exactly as they
 * do today, the same fallback the buyer snapshot uses.
 *
 * invoices.client_id and invoice_items.client_id become nullable with
 * nullOnDelete. The normal admin delete is a soft delete, but a hard delete
 * (GDPR, cleanup) used to cascade the invoices away with the customer. The
 * invoice is the document; removing the customer must not remove it. A NULL
 * client_id means "the account is gone, the document remains".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('seller_name')->nullable()->after('language');
            $table->string('seller_legal_name')->nullable()->after('seller_name');
            $table->string('seller_website')->nullable()->after('seller_legal_name');
            $table->string('seller_address')->nullable()->after('seller_website');
            $table->string('seller_city')->nullable()->after('seller_address');
            $table->string('seller_postcode', 32)->nullable()->after('seller_city');
            $table->string('seller_country', 64)->nullable()->after('seller_postcode');
            $table->string('seller_phone')->nullable()->after('seller_country');
            $table->string('seller_email')->nullable()->after('seller_phone');
            $table->string('seller_tax_office')->nullable()->after('seller_email');
            $table->string('seller_tax_id', 64)->nullable()->after('seller_tax_office');
            $table->string('seller_mersis', 64)->nullable()->after('seller_tax_id');
            $table->string('seller_logo')->nullable()->after('seller_mersis');
            $table->json('seller_bank_accounts')->nullable()->after('seller_logo');
            $table->timestamp('seller_snapshot_at')->nullable()->after('seller_bank_accounts');
        });

        // The client foreign keys cascade today: a hard delete removes the
        // document. Keep the document instead; the buyer snapshot is the copy.
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
        });
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->unsignedBigInteger('client_id')->nullable()->change();
        });
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->unsignedBigInteger('client_id')->nullable()->change();
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->foreign('client_id')->references('id')->on('clients')->nullOnDelete();
        });
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->foreign('client_id')->references('id')->on('clients')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
        });
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
        });

        // The column stays nullable: rows whose customer was hard-deleted have
        // no id left to write back, and deleting the documents to satisfy a
        // NOT NULL would undo the point of the migration.
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreign('client_id')->references('id')->on('clients')->cascadeOnDelete();
        });
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->foreign('client_id')->references('id')->on('clients')->cascadeOnDelete();
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn([
                'seller_name', 'seller_legal_name', 'seller_website', 'seller_address',
                'seller_city', 'seller_postcode', 'seller_country', 'seller_phone',
                'seller_email', 'seller_tax_office', 'seller_tax_id', 'seller_mersis',
                'seller_logo', 'seller_bank_accounts', 'seller_snapshot_at',
            ]);
        });
    }
};
