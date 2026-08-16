<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('invoice_id')->nullable()->index()->after('client_id');
        });
    }

    public function down(): void {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropColumn('invoice_id');
        });
    }
};
