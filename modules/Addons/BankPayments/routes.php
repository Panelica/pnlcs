<?php

use Illuminate\Support\Facades\Route;
use Modules\Addons\BankPayments\Http\Controllers\Admin\BankAssignmentController;
use Modules\Addons\BankPayments\Http\Controllers\Admin\BankMailboxController;
use Modules\Addons\BankPayments\Http\Controllers\Admin\BankMatchController;
use Modules\Addons\BankPayments\Http\Controllers\Admin\BankPaymentsDashboardController;
use Modules\Addons\BankPayments\Http\Controllers\Admin\BankSyncController;
use Modules\Addons\BankPayments\Http\Controllers\Admin\BankTransactionController;

Route::middleware(['web', 'admin.auth', 'admin.2fa', 'admin.permission:manage_invoices'])
    ->prefix('admin/bank-payments')
    ->name('admin.bank-payments.')
    ->group(function () {
        Route::get('/', [BankPaymentsDashboardController::class, 'index'])->name('index');
        Route::get('transactions', [BankTransactionController::class, 'index'])->name('transactions.index');
        Route::get('transactions/{transaction}', [BankTransactionController::class, 'show'])->name('transactions.show');
        Route::get('transactions/{transaction}/assign', [BankAssignmentController::class, 'show'])->name('transactions.assign');
        Route::post('transactions/{transaction}/assign', [BankAssignmentController::class, 'store'])->name('transactions.assign.store');
        Route::post('transactions/{transaction}/rematch', [BankMatchController::class, 'rematch'])->name('transactions.rematch');
        Route::get('payers', [BankAssignmentController::class, 'payers'])->name('payers.index');
        Route::delete('payers/{profile}', [BankAssignmentController::class, 'forgetPayer'])->name('payers.destroy');
        Route::get('matches', [BankMatchController::class, 'index'])->name('matches.index');
        Route::post('matches/{match}/approve', [BankMatchController::class, 'approve'])->name('matches.approve');
        Route::post('matches/{match}/reject', [BankMatchController::class, 'reject'])->name('matches.reject');
        Route::get('logs', [BankPaymentsDashboardController::class, 'logs'])->name('logs.index');
        Route::post('sync', [BankSyncController::class, 'store'])->name('sync');
    });

Route::middleware(['web', 'admin.auth', 'admin.2fa', 'admin.permission:manage_settings'])
    ->prefix('admin/bank-payments/mailboxes')
    ->name('admin.bank-payments.mailboxes.')
    ->group(function () {
        Route::get('/', [BankMailboxController::class, 'index'])->name('index');
        Route::get('create', [BankMailboxController::class, 'create'])->name('create');
        Route::post('/', [BankMailboxController::class, 'store'])->name('store');
        Route::get('{mailbox}/edit', [BankMailboxController::class, 'edit'])->name('edit');
        Route::put('{mailbox}', [BankMailboxController::class, 'update'])->name('update');
        Route::delete('{mailbox}', [BankMailboxController::class, 'destroy'])->name('destroy');
        Route::post('{mailbox}/test', [BankMailboxController::class, 'test'])->name('test');
    });
