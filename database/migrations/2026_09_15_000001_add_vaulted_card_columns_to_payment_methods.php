<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a stored card needs before it can be charged again on its own.
 *
 * payment_methods already holds the gateway's handle for a card in
 * remote_token and the digits the customer recognises it by in last_four, and
 * neither is touched here. What it never held is the customer the card belongs
 * to at the gateway — Stripe hangs stored cards off a customer record, and
 * without it the same client collects a new customer per card — nor anything
 * to show them on screen beyond four digits, nor any way to say that a card is
 * still there but no longer usable.
 *
 * Every column is nullable or defaulted: the rows already in the table were
 * written by the bank-transfer form and the iyzico callback, and they must stay
 * exactly as valid as they were.
 *
 * expiry_date is deliberately left alone. It stores a 'Y-m' string and the
 * monthly expiry-alert command compares it as text; exp_month and exp_year are
 * what a gateway hands back, kept beside it for display rather than instead of
 * it, so that command keeps working untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            // The gateway's own customer record (Stripe: cus_...). A webhook
            // arrives naming it and nothing else, so it has to be findable.
            $table->string('gateway_customer_id', 191)->nullable()->after('remote_token');
            $table->string('card_brand', 32)->nullable()->after('gateway_customer_id');
            $table->unsignedTinyInteger('exp_month')->nullable()->after('card_brand');
            $table->unsignedSmallInteger('exp_year')->nullable()->after('exp_month');
            // 'active' or 'requires_update' — a card that is still stored but
            // that the bank has stopped accepting. Defaulted so that every row
            // written before today reads as usable, which is what it is.
            $table->string('status', 20)->default('active')->after('is_default');

            $table->index('gateway_customer_id');
        });
    }

    public function down(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->dropIndex(['gateway_customer_id']);
            $table->dropColumn(['gateway_customer_id', 'card_brand', 'exp_month', 'exp_year', 'status']);
        });
    }
};
