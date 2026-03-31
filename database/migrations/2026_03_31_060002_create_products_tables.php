<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create("product_groups", function (Blueprint $table) {
            $table->id();
            $table->string("name");
            $table->string("slug")->unique();
            $table->text("headline")->nullable();
            $table->text("tagline")->nullable();
            $table->string("order_form_template")->default("standard_cart");
            $table->boolean("hidden")->default(false);
            $table->integer("sort_order")->default(0);
            $table->timestamps();
        });

        Schema::create("products", function (Blueprint $table) {
            $table->id();
            $table->string("type")->default("hosting");
            $table->foreignId("group_id")->constrained("product_groups")->cascadeOnDelete();
            $table->string("name");
            $table->string("slug")->nullable();
            $table->text("description")->nullable();
            $table->boolean("hidden")->default(false);
            $table->boolean("show_domain_options")->default(true);
            $table->boolean("is_featured")->default(false);
            $table->boolean("retired")->default(false);
            $table->string("pay_type")->default("recurring");
            $table->string("auto_setup")->default("order");
            $table->string("server_type")->nullable();
            $table->unsignedBigInteger("server_group_id")->nullable();
            $table->boolean("stock_control")->default(false);
            $table->integer("stock_qty")->default(0);
            $table->string("welcome_email_template")->nullable();
            $table->integer("sort_order")->default(0);
            $table->json("config_options")->nullable();
            $table->boolean("tax")->default(true);
            $table->timestamps();
        });

        Schema::create("pricing", function (Blueprint $table) {
            $table->id();
            $table->string("type");
            $table->foreignId("currency_id")->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger("rel_id");
            $table->decimal("monthly_setup", 15, 2)->default(0);
            $table->decimal("quarterly_setup", 15, 2)->default(0);
            $table->decimal("semiannually_setup", 15, 2)->default(0);
            $table->decimal("annually_setup", 15, 2)->default(0);
            $table->decimal("biennially_setup", 15, 2)->default(0);
            $table->decimal("triennially_setup", 15, 2)->default(0);
            $table->decimal("monthly", 15, 2)->default(-1);
            $table->decimal("quarterly", 15, 2)->default(-1);
            $table->decimal("semiannually", 15, 2)->default(-1);
            $table->decimal("annually", 15, 2)->default(-1);
            $table->decimal("biennially", 15, 2)->default(-1);
            $table->decimal("triennially", 15, 2)->default(-1);
            $table->timestamps();
            $table->unique(["type", "currency_id", "rel_id"]);
        });

        Schema::create("product_addons", function (Blueprint $table) {
            $table->id();
            $table->string("name");
            $table->text("description")->nullable();
            $table->text("packages")->nullable();
            $table->boolean("hidden")->default(false);
            $table->boolean("retired")->default(false);
            $table->integer("sort_order")->default(0);
            $table->boolean("tax")->default(true);
            $table->timestamps();
        });

        Schema::create("config_option_groups", function (Blueprint $table) {
            $table->id();
            $table->string("name");
            $table->text("description")->nullable();
            $table->timestamps();
        });
        Schema::create("config_options", function (Blueprint $table) {
            $table->id();
            $table->foreignId("group_id")->constrained("config_option_groups")->cascadeOnDelete();
            $table->string("option_name");
            $table->string("option_type")->default("dropdown");
            $table->integer("qty_minimum")->default(0);
            $table->integer("qty_maximum")->default(0);
            $table->integer("sort_order")->default(0);
            $table->boolean("hidden")->default(false);
            $table->timestamps();
        });
        Schema::create("config_option_subs", function (Blueprint $table) {
            $table->id();
            $table->foreignId("config_id")->constrained("config_options")->cascadeOnDelete();
            $table->string("option_name");
            $table->integer("sort_order")->default(0);
            $table->boolean("hidden")->default(false);
            $table->timestamps();
        });
        Schema::create("config_option_links", function (Blueprint $table) {
            $table->id();
            $table->foreignId("group_id")->constrained("config_option_groups")->cascadeOnDelete();
            $table->foreignId("product_id")->constrained("products")->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create("promotions", function (Blueprint $table) {
            $table->id();
            $table->string("code")->unique();
            $table->string("type")->default("percentage");
            $table->boolean("recurring")->default(false);
            $table->decimal("value", 15, 2)->default(0);
            $table->string("cycles")->nullable();
            $table->text("applies_to")->nullable();
            $table->text("requires")->nullable();
            $table->date("start_date")->nullable();
            $table->date("expiration_date")->nullable();
            $table->integer("max_uses")->default(0);
            $table->integer("uses")->default(0);
            $table->boolean("lifetime_promo")->default(false);
            $table->boolean("apply_once")->default(false);
            $table->boolean("new_signups_only")->default(false);
            $table->boolean("existing_client")->default(false);
            $table->boolean("upgrades")->default(false);
            $table->string("notes")->nullable();
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists("config_option_links");
        Schema::dropIfExists("config_option_subs");
        Schema::dropIfExists("config_options");
        Schema::dropIfExists("config_option_groups");
        Schema::dropIfExists("promotions");
        Schema::dropIfExists("product_addons");
        Schema::dropIfExists("pricing");
        Schema::dropIfExists("products");
        Schema::dropIfExists("product_groups");
    }
};
