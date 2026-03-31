<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create("orders", function (Blueprint $table) {
            $table->id();
            $table->string("order_num")->unique();
            $table->foreignId("client_id")->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger("contact_id")->nullable();
            $table->date("date");
            $table->string("promo_code")->nullable();
            $table->decimal("amount", 15, 2)->default(0);
            $table->string("payment_method")->nullable();
            $table->unsignedBigInteger("invoice_id")->nullable();
            $table->string("status")->default("pending");
            $table->string("ip_address", 45)->nullable();
            $table->string("fraud_module")->nullable();
            $table->text("fraud_output")->nullable();
            $table->text("notes")->nullable();
            $table->timestamps();
            $table->index("status");
        });

        Schema::create("order_statuses", function (Blueprint $table) {
            $table->id();
            $table->string("title");
            $table->string("color", 7)->default("#3b82f6");
            $table->boolean("show_pending")->default(false);
            $table->boolean("show_active")->default(false);
            $table->boolean("show_cancelled")->default(false);
            $table->integer("sort_order")->default(0);
            $table->timestamps();
        });

        Schema::create("invoices", function (Blueprint $table) {
            $table->id();
            $table->foreignId("client_id")->constrained()->cascadeOnDelete();
            $table->string("invoice_num")->nullable();
            $table->date("date");
            $table->date("due_date");
            $table->timestamp("date_paid")->nullable();
            $table->decimal("subtotal", 15, 2)->default(0);
            $table->decimal("credit", 15, 2)->default(0);
            $table->decimal("tax", 15, 2)->default(0);
            $table->decimal("tax2", 15, 2)->default(0);
            $table->decimal("total", 15, 2)->default(0);
            $table->decimal("tax_rate", 10, 5)->default(0);
            $table->decimal("tax_rate2", 10, 5)->default(0);
            $table->string("status")->default("unpaid"); // draft, unpaid, paid, overdue, cancelled, refunded, collections, payment_pending
            $table->string("payment_method")->nullable();
            $table->unsignedBigInteger("pay_method_id")->nullable();
            $table->text("notes")->nullable();
            $table->timestamps();
            $table->index("status");
            $table->index("due_date");
        });

        Schema::create("invoice_items", function (Blueprint $table) {
            $table->id();
            $table->foreignId("invoice_id")->constrained()->cascadeOnDelete();
            $table->foreignId("client_id")->constrained()->cascadeOnDelete();
            $table->string("type")->default(""); // Hosting, Domain, Addon, Item, PromoHosting, PromoDomain
            $table->unsignedBigInteger("rel_id")->default(0);
            $table->text("description")->nullable();
            $table->decimal("amount", 15, 2)->default(0);
            $table->boolean("taxed")->default(false);
            $table->date("due_date")->nullable();
            $table->timestamps();
        });

        Schema::create("transactions", function (Blueprint $table) {
            $table->id();
            $table->foreignId("client_id")->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger("currency_id")->nullable();
            $table->string("gateway")->nullable();
            $table->date("date");
            $table->text("description")->nullable();
            $table->decimal("amount_in", 15, 2)->default(0);
            $table->decimal("fees", 15, 2)->default(0);
            $table->decimal("amount_out", 15, 2)->default(0);
            $table->decimal("rate", 10, 5)->default(1);
            $table->string("transaction_id")->nullable();
            $table->unsignedBigInteger("invoice_id")->nullable();
            $table->unsignedBigInteger("refund_id")->nullable();
            $table->timestamps();
            $table->index("transaction_id");
        });

        Schema::create("credits", function (Blueprint $table) {
            $table->id();
            $table->foreignId("client_id")->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger("admin_id")->nullable();
            $table->date("date");
            $table->text("description")->nullable();
            $table->decimal("amount", 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::create("payment_methods", function (Blueprint $table) {
            $table->id();
            $table->foreignId("client_id")->constrained()->cascadeOnDelete();
            $table->string("description")->nullable();
            $table->unsignedBigInteger("contact_id")->nullable();
            $table->string("gateway_name")->nullable();
            $table->string("payment_type")->nullable(); // CreditCard, BankAccount
            $table->string("last_four", 4)->nullable();
            $table->string("expiry_date", 7)->nullable();
            $table->string("remote_token")->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create("gateway_settings", function (Blueprint $table) {
            $table->id();
            $table->string("gateway");
            $table->string("setting");
            $table->text("value")->nullable();
            $table->integer("sort_order")->default(0);
            $table->timestamps();
            $table->unique(["gateway", "setting"]);
        });

        // Services (hosting)
        Schema::create("services", function (Blueprint $table) {
            $table->id();
            $table->foreignId("client_id")->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger("order_id")->nullable();
            $table->unsignedBigInteger("product_id")->nullable();
            $table->unsignedBigInteger("server_id")->nullable();
            $table->string("domain")->nullable();
            $table->string("payment_method")->nullable();
            $table->integer("qty")->default(1);
            $table->decimal("first_payment_amount", 15, 2)->default(0);
            $table->decimal("amount", 15, 2)->default(0);
            $table->string("billing_cycle")->default("monthly");
            $table->date("next_due_date")->nullable();
            $table->date("registration_date")->nullable();
            $table->string("status")->default("pending"); // pending, active, suspended, terminated, cancelled, fraud, completed
            $table->string("username")->nullable();
            $table->text("password")->nullable();
            $table->bigInteger("disk_usage")->default(0);
            $table->bigInteger("disk_limit")->default(0);
            $table->bigInteger("bw_usage")->default(0);
            $table->bigInteger("bw_limit")->default(0);
            $table->date("suspension_date")->nullable();
            $table->string("suspension_reason")->nullable();
            $table->date("termination_date")->nullable();
            $table->date("override_auto_suspend_date")->nullable();
            $table->text("notes")->nullable();
            $table->timestamps();
            $table->index("status");
            $table->index("next_due_date");
        });

        Schema::create("service_addons", function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger("order_id")->nullable();
            $table->foreignId("service_id")->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger("addon_id")->nullable();
            $table->foreignId("client_id")->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger("server_id")->nullable();
            $table->integer("qty")->default(1);
            $table->decimal("amount", 15, 2)->default(0);
            $table->string("billing_cycle")->default("monthly");
            $table->date("next_due_date")->nullable();
            $table->string("status")->default("pending");
            $table->text("notes")->nullable();
            $table->timestamps();
        });

        Schema::create("cancellation_requests", function (Blueprint $table) {
            $table->id();
            $table->foreignId("service_id")->constrained()->cascadeOnDelete();
            $table->string("type")->default("end_of_billing"); // immediate, end_of_billing
            $table->text("reason")->nullable();
            $table->timestamps();
        });

        // Domains
        Schema::create("domains", function (Blueprint $table) {
            $table->id();
            $table->foreignId("client_id")->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger("order_id")->nullable();
            $table->string("type")->default("register"); // register, transfer
            $table->string("domain");
            $table->string("registrar")->nullable();
            $table->integer("registration_period")->default(1);
            $table->date("registration_date")->nullable();
            $table->date("expiry_date")->nullable();
            $table->date("next_due_date")->nullable();
            $table->string("status")->default("pending"); // pending, active, grace, redemption, expired, cancelled, fraud, transferred_away
            $table->boolean("dns_management")->default(false);
            $table->boolean("email_forwarding")->default(false);
            $table->boolean("id_protection")->default(false);
            $table->boolean("is_premium")->default(false);
            $table->string("payment_method")->nullable();
            $table->decimal("first_payment_amount", 15, 2)->default(0);
            $table->decimal("recurring_amount", 15, 2)->default(0);
            $table->text("nameservers")->nullable();
            $table->text("notes")->nullable();
            $table->timestamps();
            $table->index("status");
            $table->index("domain");
        });

        Schema::create("domain_pricing", function (Blueprint $table) {
            $table->id();
            $table->string("extension"); // .com, .net etc
            $table->boolean("dns_management")->default(false);
            $table->boolean("email_forwarding")->default(false);
            $table->boolean("id_protection")->default(false);
            $table->boolean("epp_code")->default(true);
            $table->string("auto_registrar")->nullable();
            $table->integer("grace_period")->default(0);
            $table->integer("redemption_grace_period")->default(0);
            $table->integer("min_years")->default(1);
            $table->integer("max_years")->default(10);
            $table->integer("sort_order")->default(0);
            $table->boolean("enabled")->default(true);
            $table->timestamps();
            $table->unique("extension");
        });

        Schema::create("registrar_settings", function (Blueprint $table) {
            $table->id();
            $table->string("registrar");
            $table->string("setting");
            $table->text("value")->nullable();
            $table->timestamps();
            $table->unique(["registrar", "setting"]);
        });
    }
    public function down(): void {
        Schema::dropIfExists("registrar_settings");
        Schema::dropIfExists("domain_pricing");
        Schema::dropIfExists("domains");
        Schema::dropIfExists("cancellation_requests");
        Schema::dropIfExists("service_addons");
        Schema::dropIfExists("services");
        Schema::dropIfExists("gateway_settings");
        Schema::dropIfExists("payment_methods");
        Schema::dropIfExists("credits");
        Schema::dropIfExists("transactions");
        Schema::dropIfExists("invoice_items");
        Schema::dropIfExists("invoices");
        Schema::dropIfExists("order_statuses");
        Schema::dropIfExists("orders");
    }
};
