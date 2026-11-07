<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Credit is now a payment, not a discount.
 *
 * invoices.total used to be lowered by any account credit spent on the
 * invoice, so a top-up- or balance-paid invoice ended up with total 0 and every
 * document printed a gross of zero. total is now the gross value
 * (subtotal + tax) and credit is recorded in invoices.credit and taken off in
 * PaymentService::balance(). Adding the credit back restores exactly what the
 * old code had subtracted; verified on the data as total + credit ==
 * subtotal + tax + tax2.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('invoices')->update(['total' => DB::raw('ROUND(total + credit, 2)')]);
    }

    public function down(): void
    {
        DB::table('invoices')->update(['total' => DB::raw('ROUND(total - credit, 2)')]);
    }
};
