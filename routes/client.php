<?php
use App\Http\Controllers\Client\AuthController;
use App\Http\Controllers\Client\DomainController;
use App\Http\Controllers\Client\HomeController;
use App\Http\Controllers\Client\InvoiceController;
use App\Http\Controllers\Client\KbController;
use App\Http\Controllers\Client\ServiceController;
use App\Http\Controllers\Client\TicketController;
use Illuminate\Support\Facades\Route;

Route::prefix("client")->name("client.")->group(function () {
    Route::get("login", [AuthController::class, "showLogin"])->name("login");
    Route::post("login", [AuthController::class, "login"])->name("login.submit");
    Route::get("register", [AuthController::class, "showRegister"])->name("register");
    Route::post("register", [AuthController::class, "register"])->name("register.submit");

    // Knowledge Base (public)
    Route::get("knowledgebase", [KbController::class, "index"])->name("kb.index");
    Route::get("knowledgebase/{article}", [KbController::class, "show"])->name("kb.show");

    Route::middleware("auth")->group(function () {
        Route::get("/", [HomeController::class, "index"])->name("home");
        Route::post("logout", [AuthController::class, "logout"])->name("logout");

        // Services
        Route::get("services", [ServiceController::class, "index"])->name("services.index");
        Route::get("services/{service}", [ServiceController::class, "show"])->name("services.show");

        // Domains
        Route::get("domains", [DomainController::class, "index"])->name("domains.index");

        // Invoices
        Route::get("invoices", [InvoiceController::class, "index"])->name("invoices.index");
        Route::get("invoices/{invoice}", [InvoiceController::class, "show"])->name("invoices.show");

        // Tickets
        Route::get("tickets", [TicketController::class, "index"])->name("tickets.index");
        Route::get("tickets/create", [TicketController::class, "create"])->name("tickets.create");
        Route::post("tickets", [TicketController::class, "store"])->name("tickets.store");
        Route::get("tickets/{ticket}", [TicketController::class, "show"])->name("tickets.show");
        Route::post("tickets/{ticket}/reply", [TicketController::class, "reply"])->name("tickets.reply");
    });
});
