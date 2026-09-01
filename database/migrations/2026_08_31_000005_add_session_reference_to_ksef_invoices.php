<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ksef_invoices', function (Blueprint $table) {
            $table->string('session_reference', 64)->nullable()->after('invoice_id');
        });
    }

    public function down(): void
    {
        Schema::table('ksef_invoices', function (Blueprint $table) {
            $table->dropColumn('session_reference');
        });
    }
};
