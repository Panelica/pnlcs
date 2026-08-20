<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table("clients", function (Blueprint $table) {
            $table->string("phone_prefix", 4)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table("clients", function (Blueprint $table) {
            $table->string("phone_prefix", 10)->nullable()->change();
        });
    }
};
