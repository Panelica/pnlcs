<?php

use App\Http\Controllers\Client\AccountController;
use App\Http\Controllers\Client\AffiliateController;
use App\Http\Controllers\Client\AnnouncementController;
use App\Http\Controllers\Client\AuthController;
use App\Http\Controllers\Client\CartController;
use App\Http\Controllers\Client\ContactController;
use App\Http\Controllers\Client\DomainController;
use App\Http\Controllers\Client\DownloadController;
use App\Http\Controllers\Client\FundsController;
use App\Http\Controllers\Client\HomeController;
use App\Http\Controllers\Client\InvoiceController;
use App\Http\Controllers\Client\KbController;
use App\Http\Controllers\Client\ServiceController;
use App\Http\Controllers\Client\TicketController;
use App\Http\Controllers\DomainSearchController;
use Illuminate\Support\Facades\Route;

Route::prefix('client')->name('client.')->group(function () {
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post("login", [AuthController::class, "login"])->middleware("throttle:30,5")->name("login.submit");
    Route::get('register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('register', [AuthController::class, 'register'])->name('register.submit');

    // Password Reset
    Route::get("forgot-password", [AuthController::class, "showForgotPassword"])->name("password.request");
    Route::post("forgot-password", [AuthController::class, "sendResetLink"])->name("password.email");
    Route::get("reset-password/{token}", [AuthController::class, "showResetForm"])->name("password.reset");
    Route::post("reset-password", [AuthController::class, "resetPassword"])->name("password.update.reset");

    // Knowledge Base (public)
    Route::get('knowledgebase', [KbController::class, 'index'])->name('kb.index');
    Route::get('knowledgebase/{article}', [KbController::class, 'show'])->name('kb.show');

    // Announcements (public)
    Route::get('announcements', [AnnouncementController::class, 'index'])->name('announcements.index');
    Route::get('announcements/{announcement}', [AnnouncementController::class, 'show'])->name('announcements.show');

    // Contact (public)
    Route::get('contact', [ContactController::class, 'show'])->name('contact');
    Route::post('contact', [ContactController::class, 'submit'])->middleware('throttle:5,1')->name('contact.submit');

    // Domain Search (public — no auth required)
    Route::get('domain-search', [DomainSearchController::class, 'index'])->name('domain.search');
    Route::post('domain-search', [DomainSearchController::class, 'check'])->name('domain.check');
    Route::get('domain-pricing', [DomainSearchController::class, 'pricing'])->name('domain.pricing');

    // Store — public browsing
    Route::get('store', [CartController::class, 'store'])->name('store');
    Route::get('store/configure/{product:slug}', [CartController::class, 'configure'])->name('store.configure');

    // 2FA verification (requires login but not 2FA yet)
    Route::middleware('auth')->withoutMiddleware([\App\Http\Middleware\TwoFactorVerify::class])->group(function () {
        Route::get('2fa', [AuthController::class, 'show2faVerify'])->name('2fa.verify');
        Route::post('2fa', [AuthController::class, 'verify2fa'])->name('2fa.verify.submit');
    });

    Route::middleware('auth')->group(function () {
        Route::get('/', [HomeController::class, 'index'])->name('home');
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');

        // Services
        Route::get('services', [ServiceController::class, 'index'])->name('services.index');
        Route::get('services/{service}', [ServiceController::class, 'show'])->name('services.show');
        Route::get('services/{service}/cancel', [ServiceController::class, 'requestCancellation'])->name('services.cancel');
        Route::post('services/{service}/cancel', [ServiceController::class, 'submitCancellation'])->name('services.cancel.submit');
        Route::get('services/{service}/upgrade', [ServiceController::class, 'upgrade'])->name('services.upgrade');
        Route::post('services/{service}/upgrade', [ServiceController::class, 'processUpgrade'])->name('services.upgrade.process');

        // Domains
        Route::get('domains', [DomainController::class, 'index'])->name('domains.index');
        Route::get('domains/{domain}', [DomainController::class, 'show'])->name('domains.show');
        Route::put('domains/{domain}/nameservers', [DomainController::class, 'updateNameservers'])->name('domains.nameservers');
        Route::post('domains/{domain}/lock', [DomainController::class, 'toggleLock'])->name('domains.lock');
        Route::post('domains/{domain}/autorenew', [DomainController::class, 'toggleAutoRenew'])->name('domains.autorenew');
        Route::get('domains/{domain}/epp', [DomainController::class, 'getEppCode'])->name('domains.epp');

        // Invoices
        Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
        Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'downloadPdf'])->name('invoices.pdf');
        Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');

        // Tickets
        Route::get('tickets', [TicketController::class, 'index'])->name('tickets.index');
        Route::get('tickets/create', [TicketController::class, 'create'])->name('tickets.create');
        Route::post('tickets', [TicketController::class, 'store'])->name('tickets.store');
        Route::get('tickets/{ticket}', [TicketController::class, 'show'])->name('tickets.show');
        Route::post('tickets/{ticket}/reply', [TicketController::class, 'reply'])->name('tickets.reply');

        // Downloads (auth required)
        Route::get('downloads', [DownloadController::class, 'index'])->name('downloads.index');
        Route::get('downloads/{download}', [DownloadController::class, 'download'])->name('downloads.download');

        // Add Funds
        Route::get('funds', [FundsController::class, 'index'])->name('funds.index');
        Route::post('funds', [FundsController::class, 'store'])->name('funds.store');

        // Affiliates
        Route::get('affiliates', [AffiliateController::class, 'index'])->name('affiliates.index');
        Route::post('affiliates/activate', [AffiliateController::class, 'activate'])->name('affiliates.activate');
        Route::post('affiliates/withdraw', [AffiliateController::class, 'withdraw'])->name('affiliates.withdraw');

        // Cart & Checkout
        Route::get('cart', [CartController::class, 'index'])->name('cart.index');
        Route::post('cart/add', [CartController::class, 'addToCart'])->name('cart.add');
        Route::post('cart/add-domain', [CartController::class, 'addDomainToCart'])->name('cart.add-domain');
        Route::delete('cart/remove/{index}', [CartController::class, 'removeItem'])->name('cart.remove');
        Route::post('cart/promo', [CartController::class, 'applyPromo'])->name('cart.promo');
        Route::get('cart/checkout', [CartController::class, 'checkout'])->name('cart.checkout');
        Route::post('cart/checkout', [CartController::class, 'processCheckout'])->name('cart.process');

        // Account Management
        Route::get('account', [AccountController::class, 'profile'])->name('account.profile');
        Route::put('account', [AccountController::class, 'updateProfile'])->name('account.update');
        Route::get('account/password', [AccountController::class, 'changePassword'])->name('account.password');
        Route::put('account/password', [AccountController::class, 'updatePassword'])->name('account.password.update');
        Route::get('account/contacts', [AccountController::class, 'contacts'])->name('account.contacts');
        Route::post('account/contacts', [AccountController::class, 'storeContact'])->name('account.contacts.store');
        Route::delete('account/contacts/{contact}', [AccountController::class, 'destroyContact'])->name('account.contacts.destroy');
        Route::get("account/payment-methods", [AccountController::class, "paymentMethods"])->name("account.payment_methods");
        Route::get('account/security', [AccountController::class, 'security'])->name('account.security');
        Route::match(['get', 'post'], '2fa/enable', [AuthController::class, 'enable2fa'])->name('2fa.enable');
        Route::post('2fa/disable', [AuthController::class, 'disable2fa'])->name('2fa.disable');
    });
});

