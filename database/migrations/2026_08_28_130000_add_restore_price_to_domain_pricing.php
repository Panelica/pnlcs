<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table("domain_pricing", function (Blueprint $table) {
            $table->decimal("restore_price", 10, 2)->default(0)->after("renew_price");
        });
    }
    public function down(): void {
        Schema::table("domain_pricing", function (Blueprint $table) {
            $table->dropColumn("restore_price");
        });
    }
};
