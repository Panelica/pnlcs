<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\ClientController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DomainController;
use App\Http\Controllers\Admin\InvoiceController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ServiceController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\TicketController;
use Illuminate\Support\Facades\Route;

Route::get("/admin/login", [AuthController::class, "showLogin"])->name("admin.login");
Route::post("/admin/login", [AuthController::class, "login"])->name("admin.login.submit");

Route::middleware(["admin.auth"])->prefix("admin")->name("admin.")->group(function () {
    Route::get("/", [DashboardController::class, "index"])->name("dashboard");
    Route::post("/logout", [AuthController::class, "logout"])->name("logout");

    // Clients CRUD
    Route::resource("clients", ClientController::class);

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

    // Invoices
    Route::get("invoices", [InvoiceController::class, "index"])->name("invoices.index");
    Route::get("invoices/{invoice}", [InvoiceController::class, "show"])->name("invoices.show");

    // Services
    Route::get("services", [ServiceController::class, "index"])->name("services.index");
    Route::get("services/{service}", [ServiceController::class, "show"])->name("services.show");

    // Domains
    Route::get("domains", [DomainController::class, "index"])->name("domains.index");

    // Tickets
    Route::get("tickets", [TicketController::class, "index"])->name("tickets.index");
    Route::get("tickets/{ticket}", [TicketController::class, "show"])->name("tickets.show");
    Route::post("tickets/{ticket}/reply", [TicketController::class, "reply"])->name("tickets.reply");

    // Settings
    Route::get("settings", [SettingController::class, "general"])->name("settings.general");
    Route::post("settings", [SettingController::class, "updateGeneral"])->name("settings.general.update");

    // Reports
    Route::get("reports", [\App\Http\Controllers\Admin\ReportController::class, "index"])->name("reports.index");
    Route::get("reports/{slug}", [\App\Http\Controllers\Admin\ReportController::class, "show"])->name("reports.show");
});

