<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table("domain_pricing", function (Blueprint $table) {
            $table->decimal("register_price", 10, 2)->default(0)->after("extension");
            $table->decimal("transfer_price", 10, 2)->default(0)->after("register_price");
            $table->decimal("renew_price", 10, 2)->default(0)->after("transfer_price");
        });
    }
    public function down(): void {
        Schema::table("domain_pricing", function (Blueprint $table) {
            $table->dropColumn(["register_price", "transfer_price", "renew_price"]);
        });
    }
};
