<?php

use App\Http\Controllers\GatewayWebhookController;
use App\Http\Controllers\InstallController;
use App\Http\Middleware\EnsureNotInstalled;
use Illuminate\Support\Facades\Route;

Route::get('/', [App\Http\Controllers\WelcomeController::class, 'index'])->name('home');

// ===== Install Wizard (gated by EnsureNotInstalled — 404 once admins exist) =====
Route::middleware(['web', EnsureNotInstalled::class])->prefix('install')->group(function () {
    Route::get('/',                  [InstallController::class, 'index']);
    Route::get('/requirements',      [InstallController::class, 'requirements']);
    Route::get('/database',          [InstallController::class, 'database']);
    Route::post('/database/test',    [InstallController::class, 'testDatabase']);
    Route::post('/database',         [InstallController::class, 'saveDatabase']);
    Route::get('/admin',             [InstallController::class, 'admin']);
    Route::post('/admin',            [InstallController::class, 'saveAdmin']);
    Route::get('/app',               [InstallController::class, 'app']);
    Route::post('/app',              [InstallController::class, 'saveApp']);
    Route::get('/finish',            [InstallController::class, 'finish']);
});

// ===== Gateway Webhooks (no CSRF — verified by signature) =====
Route::withoutMiddleware([\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class])->group(function () {
    Route::post('gateway/paypal/webhook',    [GatewayWebhookController::class, 'paypal'])->name('gateway.paypal.webhook');
    Route::post('gateway/stripe/webhook',    [GatewayWebhookController::class, 'stripe'])->name('gateway.stripe.webhook');
    Route::post('gateway/authorize/webhook', [GatewayWebhookController::class, 'authorize'])->name('gateway.authorize.webhook');
    Route::post('gateway/mollie/webhook', [GatewayWebhookController::class, 'mollie'])->name('gateway.mollie.webhook');
    Route::post('gateway/razorpay/webhook', [GatewayWebhookController::class, 'razorpay'])->name('gateway.razorpay.webhook');
});

// ===== Gateway JS-SDK Capture Endpoints (authenticated, CSRF-protected) =====
Route::middleware(['web'])->group(function () {
    Route::post('gateway/paypal/capture/{invoice}',    [GatewayWebhookController::class, 'paypalCapture'])->name('gateway.paypal.capture');
    Route::post('gateway/stripe/intent/{invoice}',     [GatewayWebhookController::class, 'stripeIntent'])->name('gateway.stripe.intent');
    Route::post('gateway/stripe/confirm/{invoice}',    [GatewayWebhookController::class, 'stripeConfirm'])->name('gateway.stripe.confirm');
    Route::post('gateway/authorize/capture/{invoice}', [GatewayWebhookController::class, 'authorizeCapture'])->name('gateway.authorize.capture');
    Route::post('gateway/mollie/capture/{invoice}', [GatewayWebhookController::class, 'mollieCapture'])->name('gateway.mollie.capture');
    Route::post('gateway/razorpay/capture/{invoice}', [GatewayWebhookController::class, 'razorpayCapture'])->name('gateway.razorpay.capture');
});
