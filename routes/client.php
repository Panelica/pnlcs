<?php
use App\Http\Controllers\Client\AuthController;
use App\Http\Controllers\Client\HomeController;
use Illuminate\Support\Facades\Route;

Route::prefix("client")->name("client.")->group(function () {
    Route::get("login", [AuthController::class, "showLogin"])->name("login");
    Route::post("login", [AuthController::class, "login"])->name("login.submit");
    Route::get("register", [AuthController::class, "showRegister"])->name("register");
    Route::post("register", [AuthController::class, "register"])->name("register.submit");

    Route::middleware("auth")->group(function () {
        Route::get("/", [HomeController::class, "index"])->name("home");
        Route::post("logout", [AuthController::class, "logout"])->name("logout");
    });
});
