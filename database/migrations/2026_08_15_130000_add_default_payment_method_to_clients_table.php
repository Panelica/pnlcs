<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table("clients", function (Blueprint $table) {
            $table->string("default_payment_method", 50)->nullable()->after("currency_id");
        });
    }

    public function down(): void
    {
        Schema::table("clients", function (Blueprint $table) {
            $table->dropColumn("default_payment_method");
        });
    }
};
