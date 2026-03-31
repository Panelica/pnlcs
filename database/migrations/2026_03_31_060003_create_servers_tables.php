<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create("server_groups", function (Blueprint $table) {
            $table->id();
            $table->string("name");
            $table->string("fill_type")->default("fill"); // fill, overflow
            $table->timestamps();
        });

        Schema::create("servers", function (Blueprint $table) {
            $table->id();
            $table->string("name");
            $table->string("hostname");
            $table->string("ip_address")->nullable();
            $table->integer("max_accounts")->default(0);
            $table->string("type")->nullable(); // module name
            $table->string("username")->nullable();
            $table->text("password")->nullable();
            $table->text("access_hash")->nullable();
            $table->integer("port")->nullable();
            $table->boolean("active")->default(true);
            $table->boolean("disabled")->default(false);
            $table->string("nameserver1")->nullable();
            $table->string("nameserver2")->nullable();
            $table->string("nameserver3")->nullable();
            $table->string("nameserver4")->nullable();
            $table->string("nameserver5")->nullable();
            $table->timestamps();
        });

        Schema::create("server_group_server", function (Blueprint $table) {
            $table->id();
            $table->foreignId("server_group_id")->constrained()->cascadeOnDelete();
            $table->foreignId("server_id")->constrained()->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create("module_configurations", function (Blueprint $table) {
            $table->id();
            $table->string("module");
            $table->string("setting");
            $table->text("value")->nullable();
            $table->timestamps();
            $table->unique(["module", "setting"]);
        });

        Schema::table("products", function (Blueprint $table) {
            $table->foreign("server_group_id")->references("id")->on("server_groups")->nullOnDelete();
        });
    }
    public function down(): void {
        Schema::table("products", function (Blueprint $table) { $table->dropForeign(["server_group_id"]); });
        Schema::dropIfExists("module_configurations");
        Schema::dropIfExists("server_group_server");
        Schema::dropIfExists("servers");
        Schema::dropIfExists("server_groups");
    }
};
