<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create("settings", function (Blueprint $table) {
            $table->id();
            $table->string("setting")->unique();
            $table->text("value")->nullable();
            $table->string("group")->default("general");
            $table->timestamps();
        });

        Schema::create("currencies", function (Blueprint $table) {
            $table->id();
            $table->string("code", 3)->unique();
            $table->string("prefix", 10)->default("");
            $table->string("suffix", 10)->default("");
            $table->integer("format")->default(1);
            $table->decimal("rate", 10, 5)->default(1.00000);
            $table->boolean("is_default")->default(false);
            $table->timestamps();
        });
        Schema::table("clients", function (Blueprint $table) {
            $table->foreign("currency_id")->references("id")->on("currencies")->nullOnDelete();
        });

        Schema::create("tax_rules", function (Blueprint $table) {
            $table->id();
            $table->integer("level")->default(1);
            $table->string("name");
            $table->string("state")->default("");
            $table->string("country", 2)->default("");
            $table->decimal("tax_rate", 10, 5)->default(0);
            $table->timestamps();
        });

        Schema::create("custom_fields", function (Blueprint $table) {
            $table->id();
            $table->string("type");
            $table->unsignedBigInteger("rel_id")->default(0);
            $table->string("field_name");
            $table->string("field_type")->default("text");
            $table->text("description")->nullable();
            $table->text("field_options")->nullable();
            $table->string("regex")->nullable();
            $table->boolean("admin_only")->default(false);
            $table->boolean("required")->default(false);
            $table->integer("sort_order")->default(0);
            $table->boolean("show_on_invoice")->default(false);
            $table->boolean("show_on_order")->default(false);
            $table->timestamps();
        });
        Schema::create("custom_field_values", function (Blueprint $table) {
            $table->id();
            $table->foreignId("field_id")->constrained("custom_fields")->cascadeOnDelete();
            $table->unsignedBigInteger("rel_id");
            $table->text("value")->nullable();
            $table->timestamps();
            $table->index(["field_id", "rel_id"]);
        });
    }
    public function down(): void {
        Schema::dropIfExists("custom_field_values");
        Schema::dropIfExists("custom_fields");
        Schema::dropIfExists("tax_rules");
        Schema::table("clients", function (Blueprint $table) { $table->dropForeign(["currency_id"]); });
        Schema::dropIfExists("currencies");
        Schema::dropIfExists("settings");
    }
};
