<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table("domain_pricing", function (Blueprint $table) {
            $table->boolean("is_popular")->default(false)->after("category")->index();
        });
    }
    public function down(): void {
        Schema::table("domain_pricing", function (Blueprint $table) {
            $table->dropColumn("is_popular");
        });
    }
};
