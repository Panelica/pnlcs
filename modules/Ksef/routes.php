<?php

use Illuminate\Support\Facades\Route;
use Modules\Ksef\Http\Admin\KsefController;

Route::middleware(['web', 'admin.auth', 'admin.2fa', 'admin.permission:manage_products'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::post('ksef/test', [KsefController::class, 'test'])->name('ksef.test');
        Route::post('ksef/invoices/{record}/resend', [KsefController::class, 'resend'])->name('ksef.resend');
        Route::post('ksef/invoices/{record}/mark-corrected', [KsefController::class, 'markCorrected'])->name('ksef.mark-corrected');
    });
