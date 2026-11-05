<?php

use Illuminate\Support\Facades\Route;
use Modules\Gateways\Monobank\MonobankController;

// Public webhook: no session, no CSRF (it is not part of the web group). Authenticity = Monobank's ECDSA signature,
// checked in MonobankModule::processWebhook(). Rate-limit this URL at nginx, see docs/OPERATIONS.md.
// Naming it gateway.monobank.* also tells PNLCS's generic /gateway/{gateway}/callback to leave Monobank alone.
Route::post('gateway/monobank/webhook', [MonobankController::class, 'webhook'])
    ->name('gateway.monobank.webhook');

// Customer checkout. COMPARE the middleware list with the core's own checkout routes (docs/OPERATIONS.md, step 8):
//   php artisan route:list --path=gateway/authorize/capture -v
Route::middleware(['web', 'auth', 'throttle:8,1'])
    ->post('gateway/monobank/capture/{invoice}', [MonobankController::class, 'capture'])
    ->whereNumber('invoice')
    ->name('gateway.monobank.capture');
