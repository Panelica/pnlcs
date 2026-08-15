<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('domains', function (Blueprint $table) {
            $table->string('epp_code')->nullable()->after('domain');
        });
    }

    public function down(): void {
        Schema::table('domains', function (Blueprint $table) {
            $table->dropColumn('epp_code');
        });
    }
};
