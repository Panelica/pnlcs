<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        if (!Schema::hasTable("oauth_clients")) {
            Schema::create("oauth_clients", function (Blueprint $table) {
                $table->id();
                $table->string("identifier")->unique();
                $table->string("secret");
                $table->string("name");
                $table->text("redirect_uri")->nullable();
                $table->string("grant_types")->default("authorization_code");
                $table->boolean("active")->default(true);
                $table->timestamps();
            });
        }
        if (!Schema::hasTable("oauth_scopes")) {
            Schema::create("oauth_scopes", function (Blueprint $table) {
                $table->id();
                $table->string("scope")->unique();
                $table->string("description")->nullable();
                $table->timestamps();
            });
        }
        if (!Schema::hasTable("oauth_access_tokens")) {
            Schema::create("oauth_access_tokens", function (Blueprint $table) {
                $table->id();
                $table->foreignId("client_id")->constrained("oauth_clients")->cascadeOnDelete();
                $table->unsignedBigInteger("user_id")->nullable();
                $table->string("token", 500)->unique();
                $table->json("scopes")->nullable();
                $table->timestamp("expires_at");
                $table->timestamps();
            });
        }
        if (!Schema::hasTable("oauth_auth_codes")) {
            Schema::create("oauth_auth_codes", function (Blueprint $table) {
                $table->id();
                $table->foreignId("client_id")->constrained("oauth_clients")->cascadeOnDelete();
                $table->unsignedBigInteger("user_id")->nullable();
                $table->string("code")->unique();
                $table->json("scopes")->nullable();
                $table->string("redirect_uri")->nullable();
                $table->timestamp("expires_at");
                $table->timestamps();
            });
        }
        if (!Schema::hasTable("projects")) {
            Schema::create("projects", function (Blueprint $table) {
                $table->id();
                $table->foreignId("client_id")->constrained()->cascadeOnDelete();
                $table->unsignedBigInteger("admin_id")->nullable();
                $table->string("title");
                $table->text("description")->nullable();
                $table->string("status")->default("pending");
                $table->date("due_date")->nullable();
                $table->date("start_date")->nullable();
                $table->timestamps();
            });
        }
        if (!Schema::hasTable("project_tasks")) {
            Schema::create("project_tasks", function (Blueprint $table) {
                $table->id();
                $table->foreignId("project_id")->constrained()->cascadeOnDelete();
                $table->string("task");
                $table->text("notes")->nullable();
                $table->string("admin")->nullable();
                $table->boolean("completed")->default(false);
                $table->date("due_date")->nullable();
                $table->integer("sort_order")->default(0);
                $table->timestamps();
            });
        }
        if (!Schema::hasTable("project_messages")) {
            Schema::create("project_messages", function (Blueprint $table) {
                $table->id();
                $table->foreignId("project_id")->constrained()->cascadeOnDelete();
                $table->text("message");
                $table->string("admin")->nullable();
                $table->unsignedBigInteger("client_id")->nullable();
                $table->timestamps();
            });
        }
        if (!Schema::hasTable("user_invites")) {
            Schema::create("user_invites", function (Blueprint $table) {
                $table->id();
                $table->string("token")->unique();
                $table->string("email");
                $table->foreignId("client_id")->constrained()->cascadeOnDelete();
                $table->unsignedBigInteger("invited_by");
                $table->json("permissions")->nullable();
                $table->timestamp("accepted_at")->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }
        if (!Schema::hasTable("service_config_options")) {
            Schema::create("service_config_options", function (Blueprint $table) {
                $table->id();
                $table->foreignId("service_id")->constrained()->cascadeOnDelete();
                $table->unsignedBigInteger("config_id");
                $table->unsignedBigInteger("option_id")->nullable();
                $table->integer("qty")->default(0);
                $table->timestamps();
            });
        }
        if (!Schema::hasTable("pricing_brackets")) {
            Schema::create("pricing_brackets", function (Blueprint $table) {
                $table->id();
                $table->decimal("floor", 15, 2)->default(0);
                $table->decimal("ceiling", 15, 2)->default(0);
                $table->string("rel_type");
                $table->unsignedBigInteger("rel_id");
                $table->string("schema_type")->default("flat");
                $table->timestamps();
            });
        }
        if (!Schema::hasTable("product_slugs")) {
            Schema::create("product_slugs", function (Blueprint $table) {
                $table->id();
                $table->foreignId("product_id")->constrained()->cascadeOnDelete();
                $table->unsignedBigInteger("group_id");
                $table->string("group_slug");
                $table->string("slug");
                $table->boolean("active")->default(true);
                $table->integer("clicks")->default(0);
                $table->timestamps();
            });
        }
        if (!Schema::hasTable("product_features")) {
            Schema::create("product_features", function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger("product_group_id");
                $table->string("feature");
                $table->integer("sort_order")->default(0);
                $table->timestamps();
            });
        }
        if (!Schema::hasTable("product_recommendations")) {
            Schema::create("product_recommendations", function (Blueprint $table) {
                $table->id();
                $table->foreignId("product_id")->constrained()->cascadeOnDelete();
                $table->integer("sort_order")->default(0);
                $table->timestamps();
            });
        }
        if (!Schema::hasTable("product_upgrade_paths")) {
            Schema::create("product_upgrade_paths", function (Blueprint $table) {
                $table->id();
                $table->foreignId("product_id")->constrained()->cascadeOnDelete();
                $table->unsignedBigInteger("upgrade_product_id");
                $table->timestamps();
            });
        }
        if (!Schema::hasTable("ticket_mail_logs")) {
            Schema::create("ticket_mail_logs", function (Blueprint $table) {
                $table->id();
                $table->timestamp("date");
                $table->string("to")->nullable();
                $table->string("name")->nullable();
                $table->string("email")->nullable();
                $table->string("subject")->nullable();
                $table->text("message")->nullable();
                $table->string("status")->default("received");
                $table->timestamps();
            });
        }
        if (!Schema::hasTable("ticket_logs")) {
            Schema::create("ticket_logs", function (Blueprint $table) {
                $table->id();
                $table->foreignId("ticket_id")->constrained()->cascadeOnDelete();
                $table->timestamp("date");
                $table->text("action");
                $table->timestamps();
            });
        }
        if (!Schema::hasTable("dynamic_translations")) {
            Schema::create("dynamic_translations", function (Blueprint $table) {
                $table->id();
                $table->string("language", 10);
                $table->string("group");
                $table->string("key");
                $table->text("value")->nullable();
                $table->timestamps();
                $table->unique(["language", "group", "key"]);
            });
        }
        if (!Schema::hasTable("transaction_history")) {
            Schema::create("transaction_history", function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger("invoice_id")->nullable();
                $table->string("gateway")->nullable();
                $table->string("transaction_id")->nullable();
                $table->string("remote_status")->nullable();
                $table->boolean("completed")->default(false);
                $table->decimal("amount", 15, 2)->default(0);
                $table->unsignedBigInteger("currency_id")->nullable();
                $table->timestamps();
            });
        }
        if (!Schema::hasTable("invoice_data")) {
            Schema::create("invoice_data", function (Blueprint $table) {
                $table->id();
                $table->foreignId("invoice_id")->constrained()->cascadeOnDelete();
                $table->string("country", 2)->nullable();
                $table->timestamps();
            });
        }
        if (!Schema::hasTable("server_remote_data")) {
            Schema::create("server_remote_data", function (Blueprint $table) {
                $table->id();
                $table->foreignId("server_id")->constrained()->cascadeOnDelete();
                $table->integer("num_accounts")->default(0);
                $table->json("meta_data")->nullable();
                $table->timestamps();
            });
        }
        if (!Schema::hasTable("domain_reminders")) {
            Schema::create("domain_reminders", function (Blueprint $table) {
                $table->id();
                $table->foreignId("domain_id")->constrained()->cascadeOnDelete();
                $table->date("date");
                $table->string("type")->default("expiry");
                $table->integer("days_before_expiry")->default(30);
                $table->timestamps();
            });
        }
        if (!Schema::hasTable("storage_configurations")) {
            Schema::create("storage_configurations", function (Blueprint $table) {
                $table->id();
                $table->string("name");
                $table->string("type")->default("local");
                $table->json("configuration")->nullable();
                $table->boolean("is_default")->default(false);
                $table->timestamps();
            });
        }
        if (!Schema::hasTable("metric_usage")) {
            Schema::create("metric_usage", function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger("service_id");
                $table->string("metric");
                $table->decimal("value", 20, 4)->default(0);
                $table->timestamp("measured_at");
                $table->timestamps();
            });
        }
        if (!Schema::hasTable("rsa_key_pairs")) {
            Schema::create("rsa_key_pairs", function (Blueprint $table) {
                $table->id();
                $table->text("private_key");
                $table->text("public_key");
                $table->timestamps();
            });
        }
        if (!Schema::hasTable("transient_data")) {
            Schema::create("transient_data", function (Blueprint $table) {
                $table->id();
                $table->string("name")->unique();
                $table->text("data")->nullable();
                $table->timestamp("expires_at")->nullable();
                $table->timestamps();
            });
        }
        if (!Schema::hasTable("auth_providers")) {
            Schema::create("auth_providers", function (Blueprint $table) {
                $table->id();
                $table->string("provider");
                $table->json("configuration")->nullable();
                $table->boolean("active")->default(false);
                $table->timestamps();
            });
        }
        if (!Schema::hasTable("auth_account_links")) {
            Schema::create("auth_account_links", function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger("user_id");
                $table->string("provider");
                $table->string("provider_user_id");
                $table->json("data")->nullable();
                $table->timestamps();
                $table->unique(["provider", "provider_user_id"]);
            });
        }
        if (!Schema::hasTable("bundle_items")) {
            Schema::create("bundle_items", function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger("bundle_id");
                $table->string("item_type");
                $table->unsignedBigInteger("item_id");
                $table->integer("qty")->default(1);
                $table->timestamps();
            });
        }
        if (!Schema::hasTable("affiliate_pending")) {
            Schema::create("affiliate_pending", function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger("affiliate_account_id");
                $table->unsignedBigInteger("invoice_id")->nullable();
                $table->decimal("amount", 15, 2)->default(0);
                $table->date("clearing_date")->nullable();
                $table->timestamps();
            });
        }
        if (!Schema::hasTable("affiliate_hits")) {
            Schema::create("affiliate_hits", function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger("affiliate_id");
                $table->string("referrer")->nullable();
                $table->timestamps();
            });
        }
        if (!Schema::hasTable("service_data")) {
            Schema::create("service_data", function (Blueprint $table) {
                $table->id();
                $table->foreignId("service_id")->constrained()->cascadeOnDelete();
                $table->unsignedBigInteger("addon_id")->nullable();
                $table->unsignedBigInteger("client_id")->nullable();
                $table->string("scope")->default("service");
                $table->string("name");
                $table->text("value")->nullable();
                $table->timestamp("expires_at")->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void {
        DB::statement("SET FOREIGN_KEY_CHECKS=0");
        $tables = ["service_data","affiliate_hits","affiliate_pending","bundle_items","auth_account_links","auth_providers","transient_data","rsa_key_pairs","metric_usage","storage_configurations","domain_reminders","server_remote_data","invoice_data","transaction_history","dynamic_translations","ticket_logs","ticket_mail_logs","product_upgrade_paths","product_recommendations","product_features","product_slugs","pricing_brackets","service_config_options","user_invites","project_messages","project_tasks","projects","oauth_auth_codes","oauth_access_tokens","oauth_scopes","oauth_clients"];
        foreach ($tables as $t) { Schema::dropIfExists($t); }
        DB::statement("SET FOREIGN_KEY_CHECKS=1");
    }
};
