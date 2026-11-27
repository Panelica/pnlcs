<?php

use Illuminate\Support\Facades\Route;
use Modules\Ksef\Http\Admin\KsefController;

Route::middleware(['web', 'admin.auth', 'admin.2fa', 'admin.permission:manage_products'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('ksef', [KsefController::class, 'index'])->name('ksef.index');
        Route::post('ksef/test', [KsefController::class, 'test'])->name('ksef.test');
        Route::post('ksef/import', [KsefController::class, 'import'])->name('ksef.import');
        Route::get('ksef/imported/{imported}/xml', [KsefController::class, 'downloadImportedXml'])->name('ksef.imported.xml');
        Route::get('ksef/imported/{imported}', [KsefController::class, 'showImported'])->name('ksef.imported.show');
        Route::post('ksef/invoices/{record}/resend', [KsefController::class, 'resend'])->name('ksef.resend');
        Route::post('ksef/invoices/{record}/mark-corrected', [KsefController::class, 'markCorrected'])->name('ksef.mark-corrected');
    });
