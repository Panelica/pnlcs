<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        // Affiliates
        Schema::create("affiliates", function (Blueprint $table) {
            $table->id();
            $table->foreignId("client_id")->constrained()->cascadeOnDelete();
            $table->integer("visitors")->default(0);
            $table->string("pay_type")->default("percentage");
            $table->decimal("pay_amount", 15, 2)->default(0);
            $table->boolean("onetime")->default(false);
            $table->decimal("balance", 15, 2)->default(0);
            $table->decimal("withdrawn", 15, 2)->default(0);
            $table->timestamps();
        });
        Schema::create("affiliate_accounts", function (Blueprint $table) {
            $table->id();
            $table->foreignId("affiliate_id")->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger("service_id");
            $table->date("last_paid")->nullable();
            $table->timestamps();
        });
        Schema::create("affiliate_history", function (Blueprint $table) {
            $table->id();
            $table->foreignId("affiliate_id")->constrained()->cascadeOnDelete();
            $table->date("date");
            $table->unsignedBigInteger("affiliate_account_id")->nullable();
            $table->unsignedBigInteger("invoice_id")->nullable();
            $table->text("description")->nullable();
            $table->decimal("amount", 15, 2)->default(0);
            $table->timestamps();
        });
        Schema::create("affiliate_withdrawals", function (Blueprint $table) {
            $table->id();
            $table->foreignId("affiliate_id")->constrained()->cascadeOnDelete();
            $table->date("date");
            $table->decimal("amount", 15, 2)->default(0);
            $table->timestamps();
        });

        // SSL
        Schema::create("ssl_orders", function (Blueprint $table) {
            $table->id();
            $table->foreignId("client_id")->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger("service_id")->nullable();
            $table->string("remote_id")->nullable();
            $table->string("module")->nullable();
            $table->string("cert_type")->nullable();
            $table->json("config_data")->nullable();
            $table->string("status")->default("pending");
            $table->date("certificate_expiry_date")->nullable();
            $table->timestamps();
        });

        // Quotes
        Schema::create("quotes", function (Blueprint $table) {
            $table->id();
            $table->foreignId("client_id")->constrained()->cascadeOnDelete();
            $table->string("subject");
            $table->date("date");
            $table->date("valid_until");
            $table->decimal("subtotal", 15, 2)->default(0);
            $table->decimal("tax", 15, 2)->default(0);
            $table->decimal("total", 15, 2)->default(0);
            $table->string("status")->default("draft");
            $table->text("notes")->nullable();
            $table->text("customer_notes")->nullable();
            $table->string("proposal")->nullable();
            $table->timestamps();
        });
        Schema::create("quote_items", function (Blueprint $table) {
            $table->id();
            $table->foreignId("quote_id")->constrained()->cascadeOnDelete();
            $table->text("description");
            $table->integer("quantity")->default(1);
            $table->decimal("unit_price", 15, 2)->default(0);
            $table->decimal("discount", 15, 2)->default(0);
            $table->boolean("taxable")->default(true);
            $table->integer("sort_order")->default(0);
            $table->timestamps();
        });

        // Downloads
        Schema::create("download_categories", function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger("parent_id")->nullable();
            $table->string("name");
            $table->text("description")->nullable();
            $table->boolean("hidden")->default(false);
            $table->timestamps();
        });
        Schema::create("downloads", function (Blueprint $table) {
            $table->id();
            $table->foreignId("category_id")->constrained("download_categories")->cascadeOnDelete();
            $table->string("type")->default("link");
            $table->string("title");
            $table->text("description")->nullable();
            $table->integer("download_count")->default(0);
            $table->string("location")->nullable();
            $table->boolean("clients_only")->default(false);
            $table->boolean("hidden")->default(false);
            $table->timestamps();
        });

        // Network Issues
        Schema::create("network_issues", function (Blueprint $table) {
            $table->id();
            $table->string("title");
            $table->text("description");
            $table->string("type")->default("server");
            $table->string("status")->default("open");
            $table->string("priority")->default("medium");
            $table->string("affected_server")->nullable();
            $table->timestamp("start_date")->nullable();
            $table->timestamp("end_date")->nullable();
            $table->timestamp("last_updated")->nullable();
            $table->timestamps();
        });

        // Billable Items
        Schema::create("billable_items", function (Blueprint $table) {
            $table->id();
            $table->foreignId("client_id")->constrained()->cascadeOnDelete();
            $table->text("description");
            $table->decimal("amount", 15, 2)->default(0);
            $table->boolean("recur")->default(false);
            $table->string("recur_cycle")->nullable();
            $table->integer("recur_for")->default(0);
            $table->date("due_date")->nullable();
            $table->unsignedBigInteger("invoice_id")->nullable();
            $table->timestamps();
        });

        // Upgrades
        Schema::create("upgrades", function (Blueprint $table) {
            $table->id();
            $table->foreignId("client_id")->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger("order_id")->nullable();
            $table->string("type")->default("package");
            $table->unsignedBigInteger("rel_id");
            $table->string("original_value")->nullable();
            $table->string("new_value")->nullable();
            $table->decimal("amount", 15, 2)->default(0);
            $table->string("status")->default("pending");
            $table->timestamps();
        });

        // ToDo List
        Schema::create("todo_items", function (Blueprint $table) {
            $table->id();
            $table->string("title");
            $table->text("description")->nullable();
            $table->string("status")->default("pending");
            $table->date("due_date")->nullable();
            $table->string("admin")->nullable();
            $table->timestamps();
        });

        // Calendar
        Schema::create("calendar_events", function (Blueprint $table) {
            $table->id();
            $table->string("title");
            $table->text("description")->nullable();
            $table->timestamp("start")->nullable();
            $table->timestamp("end")->nullable();
            $table->boolean("recurring")->default(false);
            $table->string("admin")->nullable();
            $table->timestamps();
        });

        // Client Notes
        Schema::create("client_notes", function (Blueprint $table) {
            $table->id();
            $table->foreignId("client_id")->constrained()->cascadeOnDelete();
            $table->string("admin");
            $table->text("note");
            $table->boolean("sticky")->default(false);
            $table->timestamps();
        });

        // Client Files
        Schema::create("client_files", function (Blueprint $table) {
            $table->id();
            $table->foreignId("client_id")->constrained()->cascadeOnDelete();
            $table->string("title");
            $table->string("filename");
            $table->boolean("admin_only")->default(false);
            $table->timestamps();
        });

        // Banned IPs + Emails
        Schema::create("banned_ips", function (Blueprint $table) {
            $table->id();
            $table->string("ip");
            $table->text("reason")->nullable();
            $table->timestamps();
        });
        Schema::create("banned_emails", function (Blueprint $table) {
            $table->id();
            $table->string("domain");
            $table->text("reason")->nullable();
            $table->timestamps();
        });

        // API Credentials
        Schema::create("api_roles", function (Blueprint $table) {
            $table->id();
            $table->string("name");
            $table->text("description")->nullable();
            $table->json("permissions")->nullable();
            $table->timestamps();
        });
        Schema::create("api_credentials", function (Blueprint $table) {
            $table->id();
            $table->foreignId("admin_id")->constrained()->cascadeOnDelete();
            $table->foreignId("api_role_id")->nullable()->constrained("api_roles")->nullOnDelete();
            $table->string("identifier")->unique();
            $table->string("secret");
            $table->text("description")->nullable();
            $table->json("allowed_ips")->nullable();
            $table->boolean("active")->default(true);
            $table->timestamps();
        });

        // Ticket Escalations + Feedback + Watchers
        Schema::create("ticket_escalations", function (Blueprint $table) {
            $table->id();
            $table->string("name");
            $table->json("departments")->nullable();
            $table->json("statuses")->nullable();
            $table->json("priorities")->nullable();
            $table->integer("time_elapsed")->default(0);
            $table->unsignedBigInteger("new_department_id")->nullable();
            $table->string("new_priority")->nullable();
            $table->unsignedBigInteger("flag_to")->nullable();
            $table->boolean("notify")->default(false);
            $table->text("add_reply")->nullable();
            $table->timestamps();
        });
        Schema::create("ticket_feedback", function (Blueprint $table) {
            $table->id();
            $table->foreignId("ticket_id")->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger("admin_id")->nullable();
            $table->integer("rating")->default(0);
            $table->text("comments")->nullable();
            $table->timestamps();
        });
        Schema::create("ticket_watchers", function (Blueprint $table) {
            $table->id();
            $table->foreignId("ticket_id")->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger("admin_id");
            $table->timestamps();
            $table->unique(["ticket_id", "admin_id"]);
        });
        Schema::create("ticket_spam_filters", function (Blueprint $table) {
            $table->id();
            $table->string("type");
            $table->text("content");
            $table->timestamps();
        });

        // Sent Emails log
        Schema::create("emails", function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger("client_id")->nullable();
            $table->string("subject");
            $table->text("message");
            $table->timestamp("date");
            $table->string("to");
            $table->string("cc")->nullable();
            $table->string("bcc")->nullable();
            $table->boolean("pending")->default(false);
            $table->boolean("failed")->default(false);
            $table->string("failure_reason")->nullable();
            $table->timestamps();
        });

        // Gateway Log
        Schema::create("gateway_logs", function (Blueprint $table) {
            $table->id();
            $table->string("gateway");
            $table->timestamp("date");
            $table->text("data")->nullable();
            $table->text("result")->nullable();
            $table->timestamps();
        });

        // Module Log
        Schema::create("module_logs", function (Blueprint $table) {
            $table->id();
            $table->string("module");
            $table->string("action");
            $table->text("request")->nullable();
            $table->text("response")->nullable();
            $table->unsignedBigInteger("service_id")->nullable();
            $table->timestamps();
        });

        // WHOIS Log
        Schema::create("whois_logs", function (Blueprint $table) {
            $table->id();
            $table->string("domain");
            $table->timestamp("date");
            $table->timestamps();
        });

        // Carts
        Schema::create("carts", function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger("user_id")->nullable();
            $table->string("session_id")->nullable();
            $table->json("data")->nullable();
            $table->timestamps();
            $table->index("session_id");
        });

        // Domain Additional Fields
        Schema::create("domain_additional_fields", function (Blueprint $table) {
            $table->id();
            $table->foreignId("domain_id")->constrained()->cascadeOnDelete();
            $table->string("name");
            $table->text("value")->nullable();
            $table->timestamps();
        });

        // Marketing Consent
        Schema::create("marketing_consents", function (Blueprint $table) {
            $table->id();
            $table->foreignId("client_id")->constrained()->cascadeOnDelete();
            $table->boolean("email_opt_in")->default(false);
            $table->timestamps();
        });

        // Notification Rules
        Schema::create("notification_providers", function (Blueprint $table) {
            $table->id();
            $table->string("name");
            $table->string("type");
            $table->json("settings")->nullable();
            $table->boolean("active")->default(true);
            $table->timestamps();
        });
        Schema::create("notification_rules", function (Blueprint $table) {
            $table->id();
            $table->string("event");
            $table->unsignedBigInteger("provider_id")->nullable();
            $table->json("conditions")->nullable();
            $table->boolean("active")->default(true);
            $table->timestamps();
        });
    }

    public function down(): void {
        $tables = ["notification_rules","notification_providers","marketing_consents","domain_additional_fields","carts","whois_logs","module_logs","gateway_logs","emails","ticket_spam_filters","ticket_watchers","ticket_feedback","ticket_escalations","api_credentials","api_roles","banned_emails","banned_ips","client_files","client_notes","calendar_events","todo_items","upgrades","billable_items","network_issues","downloads","download_categories","quote_items","quotes","ssl_orders","affiliate_withdrawals","affiliate_history","affiliate_accounts","affiliates"];
        foreach ($tables as $t) { Schema::dropIfExists($t); }
    }
};
