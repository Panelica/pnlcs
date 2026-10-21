<?php

use Illuminate\Support\Facades\Route;
use Modules\Servers\PanelicaLicense\Http\AttachLicenseController;
use Modules\Servers\PanelicaLicense\Http\SetupController;

// The customer's install command (curl -sSL <url> | bash). No session, no
// cookies: curl is the only caller.
Route::get('setup/{reference}', SetupController::class)
    ->where('reference', '[A-Za-z0-9]{32}')
    ->middleware('throttle:30,1')
    ->name('panelica-license.setup');

// Staff, with the same rights as the other module actions on a service.
Route::middleware(['web', 'admin.auth', 'admin.2fa', 'admin.permission:manage_services'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::post('services/{service}/panelica-license/attach', AttachLicenseController::class)
            ->name('services.panelica-license.attach');
    });
