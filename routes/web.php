<?php

use App\Http\Controllers\GatewayWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// ===== Gateway Webhooks (no CSRF — verified by signature) =====
Route::withoutMiddleware([\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class])->group(function () {
    Route::post('gateway/paypal/webhook',    [GatewayWebhookController::class, 'paypal'])->name('gateway.paypal.webhook');
    Route::post('gateway/stripe/webhook',    [GatewayWebhookController::class, 'stripe'])->name('gateway.stripe.webhook');
    Route::post('gateway/authorize/webhook', [GatewayWebhookController::class, 'authorize'])->name('gateway.authorize.webhook');
});

// ===== Gateway JS-SDK Capture Endpoints (authenticated, CSRF-protected) =====
Route::middleware(['web'])->group(function () {
    Route::post('gateway/paypal/capture/{invoice}',    [GatewayWebhookController::class, 'paypalCapture'])->name('gateway.paypal.capture');
    Route::post('gateway/stripe/intent/{invoice}',     [GatewayWebhookController::class, 'stripeIntent'])->name('gateway.stripe.intent');
    Route::post('gateway/stripe/confirm/{invoice}',    [GatewayWebhookController::class, 'stripeConfirm'])->name('gateway.stripe.confirm');
    Route::post('gateway/authorize/capture/{invoice}', [GatewayWebhookController::class, 'authorizeCapture'])->name('gateway.authorize.capture');
});
