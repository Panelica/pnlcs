<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create("client_groups", function (Blueprint $table) {
            $table->id();
            $table->string("name");
            $table->string("color", 7)->default("#3b82f6");
            $table->decimal("discount_percent", 5, 2)->default(0);
            $table->boolean("suspend_exempt")->default(false);
            $table->boolean("terminate_exempt")->default(false);
            $table->timestamps();
        });

        // Add foreign key to clients
        Schema::table("clients", function (Blueprint $table) {
            $table->foreign("group_id")->references("id")->on("client_groups")->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table("clients", function (Blueprint $table) {
            $table->dropForeign(["group_id"]);
        });
        Schema::dropIfExists("client_groups");
    }
};
