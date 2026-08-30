<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('tax_rules', function (Blueprint $table) {
            $table->boolean('is_default')->default(false)->after('tax_rate');
            $table->dropColumn('level');
        });
    }

    public function down(): void {
        Schema::table('tax_rules', function (Blueprint $table) {
            $table->integer('level')->default(1)->after('id');
            $table->dropColumn('is_default');
        });
    }
};
