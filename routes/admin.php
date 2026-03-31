<?php

use App\Livewire\Admin\Auth\Login;
use App\Livewire\Admin\Dashboard;
use Illuminate\Support\Facades\Route;

Route::get('/admin/login', Login::class)->name('admin.login');

Route::middleware(['admin.auth'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', Dashboard::class)->name('dashboard');

    Route::post('/logout', function () {
        auth('admin')->logout();
        session()->invalidate();
        session()->regenerateToken();

        return redirect()->route('admin.login');
    })->name('logout');
});
