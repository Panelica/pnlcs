<?php

use Illuminate\Support\Facades\Route;
use Modules\Servers\PanelicaLicense\Http\Admin\LicensesPageController;
use Modules\Servers\PanelicaLicense\Http\AttachLicenseController;
use Modules\Servers\PanelicaLicense\Http\SetupController;

// The customer's install command (curl -sSL <url> | bash). No session, no
// cookies: curl is the only caller.
Route::get('setup/{reference}', SetupController::class)
    ->where('reference', '[A-Za-z0-9]{32}')
    ->middleware('throttle:30,1')
    ->name('panelica-license.setup');

// The partner's general install link, at this site's address.
Route::get('setup', [SetupController::class, 'general'])
    ->middleware('throttle:30,1')
    ->name('panelica-license.setup.general');

Route::middleware(['web', 'admin.auth', 'admin.2fa'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        // The Panelica Licenses page (top menu): who sells and what is sold.
        Route::middleware('admin.permission:manage_products')
            ->prefix('panelica-licenses')
            ->name('panelica-licenses.')
            ->controller(LicensesPageController::class)
            ->group(function () {
                Route::get('/', 'index')->name('index');
                Route::post('connect', 'connect')->middleware('throttle:10,1')->name('connect');
                Route::post('disconnect', 'disconnect')->name('disconnect');
                Route::post('products', 'createProduct')->name('products.store');
                Route::post('sync', 'sync')->middleware('throttle:6,1')->name('sync');
                Route::post('install-code', 'updateInstallCode')->middleware('throttle:10,1')->name('install-code');
            });

        // Staff, with the same rights as the other module actions on a service.
        Route::post('services/{service}/panelica-license/attach', AttachLicenseController::class)
            ->middleware('admin.permission:manage_services')
            ->name('services.panelica-license.attach');
    });
