<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\ClientController;
use App\Http\Controllers\Admin\ConfigController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DomainController;
use App\Http\Controllers\Admin\InvoiceController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ServiceController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\TicketController;
use App\Http\Controllers\Admin\QuoteController;
use App\Http\Controllers\Admin\ProjectController;
use App\Http\Controllers\Admin\LogController;
use Illuminate\Support\Facades\Route;

Route::get("/admin/login", [AuthController::class, "showLogin"])->name("admin.login");
Route::post("/admin/login", [AuthController::class, "login"])->name("admin.login.submit");

Route::middleware(["admin.auth"])->prefix("admin")->name("admin.")->group(function () {
    Route::get("/", [DashboardController::class, "index"])->name("dashboard");
    Route::post("/logout", [AuthController::class, "logout"])->name("logout");

    // Clients CRUD
    Route::resource("clients", ClientController::class);
    Route::post("clients/{client}/notes", [ClientController::class, "storeNote"])->name("clients.notes.store");

    // Products
    Route::get("products", [ProductController::class, "index"])->name("products.index");
    Route::get("products/create", [ProductController::class, "create"])->name("products.create");
    Route::post("products", [ProductController::class, "store"])->name("products.store");
    Route::get("products/{product}/edit", [ProductController::class, "edit"])->name("products.edit");
    Route::put("products/{product}", [ProductController::class, "update"])->name("products.update");
    Route::delete("products/{product}", [ProductController::class, "destroy"])->name("products.destroy");
    Route::get("products/groups/create", [ProductController::class, "createGroup"])->name("products.groups.create");
    Route::post("products/groups", [ProductController::class, "storeGroup"])->name("products.groups.store");

    // Orders
    Route::get("orders", [OrderController::class, "index"])->name("orders.index");
    Route::get("orders/{order}", [OrderController::class, "show"])->name("orders.show");
    Route::post("orders/{order}/accept", [OrderController::class, "accept"])->name("orders.accept");
    Route::post("orders/{order}/cancel", [OrderController::class, "cancel"])->name("orders.cancel");
    Route::post("orders/{order}/fraud", [OrderController::class, "markFraud"])->name("orders.fraud");
    Route::delete("orders/{order}", [OrderController::class, "delete"])->name("orders.delete");

    // Invoices
    Route::get("invoices", [InvoiceController::class, "index"])->name("invoices.index");
    Route::get("invoices/create", [InvoiceController::class, "create"])->name("invoices.create");
    Route::post("invoices", [InvoiceController::class, "store"])->name("invoices.store");
    Route::get("invoices/{invoice}", [InvoiceController::class, "show"])->name("invoices.show");
    Route::post("invoices/{invoice}/mark-paid", [InvoiceController::class, "markPaid"])->name("invoices.mark-paid");
    Route::post("invoices/{invoice}/cancel", [InvoiceController::class, "cancel"])->name("invoices.cancel");
    Route::get("clients/export/csv", [ClientController::class, "exportCsv"])->name("clients.export");
    Route::get("invoices/export/csv", [InvoiceController::class, "exportCsv"])->name("invoices.export");

    // Services
    Route::get("services", [ServiceController::class, "index"])->name("services.index");
    Route::get("services/{service}", [ServiceController::class, "show"])->name("services.show");
    Route::post("services/{service}/module/{action}", [ServiceController::class, "moduleAction"])->name("services.module-action");

    // Domains
    Route::get("domains", [DomainController::class, "index"])->name("domains.index");
    Route::get("domains/{domain}", [DomainController::class, "show"])->name("domains.show");

    // Tickets
    Route::get("tickets", [TicketController::class, "index"])->name("tickets.index");
    Route::get("tickets/{ticket}", [TicketController::class, "show"])->name("tickets.show");
    Route::post("tickets/{ticket}/reply", [TicketController::class, "reply"])->name("tickets.reply");

    // Settings
    Route::get("settings", [SettingController::class, "general"])->name("settings.general");
    Route::post("settings", [SettingController::class, "updateGeneral"])->name("settings.general.update");
    Route::post("settings/test-email", [SettingController::class, "testEmail"])->name("settings.test-email");
    Route::get("my-account", [SettingController::class, "myAccount"])->name("my-account");
    Route::post("my-account", [SettingController::class, "updateMyAccount"])->name("my-account.update");

    // Reports
    Route::get("reports", [\App\Http\Controllers\Admin\ReportController::class, "index"])->name("reports.index");
    Route::get("reports/{slug}", [\App\Http\Controllers\Admin\ReportController::class, "show"])->name("reports.show");

    // =============================================
    // Configuration Routes
    // =============================================
    Route::prefix("config")->name("config.")->group(function () {
        // Staff & Security
        Route::get("admins", [ConfigController::class, "admins"])->name("admins");
        Route::post("admins", [ConfigController::class, "storeAdmin"])->name("admins.store");
        Route::put("admins/{admin}", [ConfigController::class, "updateAdmin"])->name("admins.update");
        Route::delete("admins/{admin}", [ConfigController::class, "destroyAdmin"])->name("admins.destroy");

        Route::get("admin-roles", [ConfigController::class, "adminRoles"])->name("admin-roles");
        Route::post("admin-roles", [ConfigController::class, "storeRole"])->name("admin-roles.store");
        Route::put("admin-roles/{role}", [ConfigController::class, "updateRole"])->name("admin-roles.update");
        Route::delete("admin-roles/{role}", [ConfigController::class, "destroyRole"])->name("admin-roles.destroy");

        Route::get("api-credentials", [ConfigController::class, "apiCredentials"])->name("api-credentials");
        Route::post("api-credentials", [ConfigController::class, "storeApiCredential"])->name("api-credentials.store");
        Route::delete("api-credentials/{credential}", [ConfigController::class, "destroyApiCredential"])->name("api-credentials.destroy");

        // Billing
        Route::get("currencies", [ConfigController::class, "currencies"])->name("currencies");
        Route::post("currencies", [ConfigController::class, "storeCurrency"])->name("currencies.store");
        Route::put("currencies/{currency}", [ConfigController::class, "updateCurrency"])->name("currencies.update");
        Route::delete("currencies/{currency}", [ConfigController::class, "destroyCurrency"])->name("currencies.destroy");
        Route::post("currencies/{currency}/default", [ConfigController::class, "setDefaultCurrency"])->name("currencies.default");

        Route::get("tax", [ConfigController::class, "tax"])->name("tax");
        Route::post("tax", [ConfigController::class, "storeTax"])->name("tax.store");
        Route::put("tax/{taxRule}", [ConfigController::class, "updateTax"])->name("tax.update");
        Route::delete("tax/{taxRule}", [ConfigController::class, "destroyTax"])->name("tax.destroy");

        Route::get("promotions", [ConfigController::class, "promotions"])->name("promotions");
        Route::post("promotions", [ConfigController::class, "storePromotion"])->name("promotions.store");
        Route::put("promotions/{promotion}", [ConfigController::class, "updatePromotion"])->name("promotions.update");
        Route::delete("promotions/{promotion}", [ConfigController::class, "destroyPromotion"])->name("promotions.destroy");

        // Servers & Domains
        Route::get("servers", [ConfigController::class, "servers"])->name("servers");
        Route::post("servers", [ConfigController::class, "storeServer"])->name("servers.store");
        Route::put("servers/{server}", [ConfigController::class, "updateServer"])->name("servers.update");
        Route::delete("servers/{server}", [ConfigController::class, "destroyServer"])->name("servers.destroy");
        Route::post("servers/{server}/test", [ConfigController::class, "testServerConnection"])->name("servers.test");

        Route::get("server-groups", [ConfigController::class, "serverGroups"])->name("server-groups");
        Route::post("server-groups", [ConfigController::class, "storeServerGroup"])->name("server-groups.store");
        Route::put("server-groups/{serverGroup}", [ConfigController::class, "updateServerGroup"])->name("server-groups.update");
        Route::delete("server-groups/{serverGroup}", [ConfigController::class, "destroyServerGroup"])->name("server-groups.destroy");

        Route::get("domain-pricing", [ConfigController::class, "domainPricing"])->name("domain-pricing");
        Route::post("domain-pricing", [ConfigController::class, "storeTld"])->name("domain-pricing.store");
        Route::put("domain-pricing/{domainPricing}", [ConfigController::class, "updateTld"])->name("domain-pricing.update");
        Route::delete("domain-pricing/{domainPricing}", [ConfigController::class, "destroyTld"])->name("domain-pricing.destroy");

        // Modules
        Route::get("gateways", [ConfigController::class, "gateways"])->name("gateways");
        Route::post("gateways/{gateway}/settings", [ConfigController::class, "updateGatewaySettings"])->name("gateways.settings.update");
        Route::get("registrars", [ConfigController::class, "registrars"])->name("registrars");
        Route::post("registrars/{registrar}/settings", [ConfigController::class, "updateRegistrarSettings"])->name("registrars.settings.update");

        // Support
        Route::get("ticket-departments", [ConfigController::class, "ticketDepartments"])->name("ticket-departments");
        Route::post("ticket-departments", [ConfigController::class, "storeTicketDepartment"])->name("ticket-departments.store");
        Route::put("ticket-departments/{department}", [ConfigController::class, "updateTicketDepartment"])->name("ticket-departments.update");
        Route::delete("ticket-departments/{department}", [ConfigController::class, "destroyTicketDepartment"])->name("ticket-departments.destroy");

        Route::get("ticket-statuses", [ConfigController::class, "ticketStatuses"])->name("ticket-statuses");
        Route::post("ticket-statuses", [ConfigController::class, "storeTicketStatus"])->name("ticket-statuses.store");
        Route::put("ticket-statuses/{status}", [ConfigController::class, "updateTicketStatus"])->name("ticket-statuses.update");
        Route::delete("ticket-statuses/{status}", [ConfigController::class, "destroyTicketStatus"])->name("ticket-statuses.destroy");

        Route::get("email-templates", [ConfigController::class, "emailTemplates"])->name("email-templates");
        Route::put("email-templates/{template}", [ConfigController::class, "updateEmailTemplate"])->name("email-templates.update");

        // Content
        Route::get("announcements", [ConfigController::class, "announcements"])->name("announcements");
        Route::post("announcements", [ConfigController::class, "storeAnnouncement"])->name("announcements.store");
        Route::put("announcements/{announcement}", [ConfigController::class, "updateAnnouncement"])->name("announcements.update");
        Route::delete("announcements/{announcement}", [ConfigController::class, "destroyAnnouncement"])->name("announcements.destroy");

        Route::get("knowledge-base", [ConfigController::class, "knowledgeBase"])->name("knowledge-base");
        Route::post("knowledge-base/categories", [ConfigController::class, "storeKbCategory"])->name("knowledge-base.categories.store");
        Route::post("knowledge-base/articles", [ConfigController::class, "storeKbArticle"])->name("knowledge-base.articles.store");
        Route::put("knowledge-base/articles/{article}", [ConfigController::class, "updateKbArticle"])->name("knowledge-base.articles.update");
        Route::delete("knowledge-base/articles/{article}", [ConfigController::class, "destroyKbArticle"])->name("knowledge-base.articles.destroy");

        Route::get("downloads", [ConfigController::class, "downloads"])->name("downloads");
        Route::post("downloads/categories", [ConfigController::class, "storeDownloadCategory"])->name("downloads.categories.store");
        Route::post("downloads", [ConfigController::class, "storeDownload"])->name("downloads.store");
        Route::delete("downloads/{download}", [ConfigController::class, "destroyDownload"])->name("downloads.destroy");

        Route::get("network-issues", [ConfigController::class, "networkIssues"])->name("network-issues");
        Route::post("network-issues", [ConfigController::class, "storeNetworkIssue"])->name("network-issues.store");
        Route::put("network-issues/{issue}", [ConfigController::class, "updateNetworkIssue"])->name("network-issues.update");
        Route::delete("network-issues/{issue}", [ConfigController::class, "destroyNetworkIssue"])->name("network-issues.destroy");

        // Misc
        Route::get("banned-ips", [ConfigController::class, "bannedIps"])->name("banned-ips");
        Route::post("banned-ips", [ConfigController::class, "storeBannedIp"])->name("banned-ips.store");
        Route::delete("banned-ips/{bannedIp}", [ConfigController::class, "destroyBannedIp"])->name("banned-ips.destroy");

        Route::get("banned-emails", [ConfigController::class, "bannedEmails"])->name("banned-emails");
        Route::post("banned-emails", [ConfigController::class, "storeBannedEmail"])->name("banned-emails.store");
        Route::delete("banned-emails/{bannedEmail}", [ConfigController::class, "destroyBannedEmail"])->name("banned-emails.destroy");

        Route::get("todo", [ConfigController::class, "todoList"])->name("todo");
        Route::post("todo", [ConfigController::class, "storeTodo"])->name("todo.store");
        Route::put("todo/{todo}", [ConfigController::class, "updateTodo"])->name("todo.update");
        Route::delete("todo/{todo}", [ConfigController::class, "destroyTodo"])->name("todo.destroy");

        Route::get("activity-log", [ConfigController::class, "activityLog"])->name("activity-log");

        Route::get("affiliates", [ConfigController::class, "affiliates"])->name("affiliates");

        Route::get("quotes", [ConfigController::class, "quotes"])->name("quotes");



        Route::get("billable-items", [ConfigController::class, "billableItems"])->name("billable-items");
        Route::post("billable-items", [ConfigController::class, "storeBillableItem"])->name("billable-items.store");
        Route::delete("billable-items/{item}", [ConfigController::class, "destroyBillableItem"])->name("billable-items.destroy");

        Route::get("transactions", [ConfigController::class, "transactions"])->name("transactions");


        // Automation & Client Groups
        Route::get("automation", [ConfigController::class, "automation"])->name("automation");
        Route::get("client-groups", [ConfigController::class, "clientGroups"])->name("client-groups");
        Route::post("client-groups", [ConfigController::class, "storeClientGroup"])->name("client-groups.store");
        Route::put("client-groups/{group}", [ConfigController::class, "updateClientGroup"])->name("client-groups.update");
        Route::delete("client-groups/{group}", [ConfigController::class, "destroyClientGroup"])->name("client-groups.destroy");

        // System
        Route::get("system-database", [ConfigController::class, "systemDatabase"])->name("system-database");
        Route::get("system-phpinfo", [ConfigController::class, "systemPhpInfo"])->name("system-phpinfo");
    });


    // Quotes
    Route::resource("quotes", QuoteController::class);
    Route::post("quotes/{quote}/send", [QuoteController::class, "send"])->name("quotes.send");
    Route::post("quotes/{quote}/convert", [QuoteController::class, "convertToInvoice"])->name("quotes.convert");
    Route::post("quotes/{quote}/accept", [QuoteController::class, "accept"])->name("quotes.accept");
    Route::post("quotes/{quote}/decline", [QuoteController::class, "decline"])->name("quotes.decline");

    // Projects
    Route::resource("projects", ProjectController::class);
    Route::post("projects/{project}/tasks", [ProjectController::class, "addTask"])->name("projects.tasks.store");
    Route::put("projects/{project}/tasks/{task}", [ProjectController::class, "updateTask"])->name("projects.tasks.update");
    Route::delete("projects/{project}/tasks/{task}", [ProjectController::class, "deleteTask"])->name("projects.tasks.destroy");
    Route::post("projects/{project}/messages", [ProjectController::class, "addMessage"])->name("projects.messages.store");

    // Logs
    Route::get("logs", [LogController::class, "index"])->name("logs.index");
    Route::get("logs/gateway", [LogController::class, "gateway"])->name("logs.gateway");
    Route::get("logs/module", [LogController::class, "module"])->name("logs.module");
    Route::get("logs/email", [LogController::class, "email"])->name("logs.email");

});