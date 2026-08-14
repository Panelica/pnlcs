<?php

use App\Http\Controllers\Client\AccountController;
use App\Http\Controllers\Client\AffiliateController;
use App\Http\Controllers\Client\AnnouncementController;
use App\Http\Controllers\Client\AuthController;
use App\Http\Controllers\Client\CartController;
use App\Http\Controllers\Client\ContactController;
use App\Http\Controllers\Client\DomainController;
use App\Http\Controllers\Client\DownloadController;
use App\Http\Controllers\Client\EmailController;
use App\Http\Controllers\Client\FundsController;
use App\Http\Controllers\Client\HomeController;
use App\Http\Controllers\Client\InvoiceController;
use App\Http\Controllers\Client\KbController;
use App\Http\Controllers\Client\NetworkStatusController;
use App\Http\Controllers\Client\PaymentMethodController;
use App\Http\Controllers\Client\QuoteController;
use App\Http\Controllers\Client\ServiceController;
use App\Http\Controllers\Client\SslController;
use App\Http\Controllers\Client\TicketController;
use App\Http\Controllers\DomainSearchController;
use App\Http\Middleware\TwoFactorVerify;
use Illuminate\Support\Facades\Route;

Route::prefix('client')->name('client.')->middleware('banned.ip')->group(function () {
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1')->name('login.submit');
    Route::get('register', [AuthController::class, 'showRegister'])->name('register');
    // Signing up makes an account and sends mail; the contact form next to
    // it has been counted all along.
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:5,1')->name('register.submit');

    // Password Reset
    Route::get('forgot-password', [AuthController::class, 'showForgotPassword'])->name('password.request');
    // Unthrottled, this is a mail bomb aimed at any address the attacker likes.
    Route::post('forgot-password', [AuthController::class, 'sendResetLink'])->middleware('throttle:5,1')->name('password.email');
    Route::get('reset-password/{token}', [AuthController::class, 'showResetForm'])->name('password.reset');
    Route::post('reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:10,1')->name('password.update.reset');

    // Knowledge Base (public)
    Route::get('knowledgebase', [KbController::class, 'index'])->name('kb.index');
    Route::get('knowledgebase/{article}', [KbController::class, 'show'])->name('kb.show');

    // Announcements (public)
    Route::get('announcements', [AnnouncementController::class, 'index'])->name('announcements.index');
    // Network / server status (public)
    Route::get('network-status', [NetworkStatusController::class, 'index'])->name('network-status');
    Route::get('announcements/{announcement}', [AnnouncementController::class, 'show'])->name('announcements.show');

    // Contact (public)
    Route::get('contact', [ContactController::class, 'show'])->name('contact');
    Route::post('contact', [ContactController::class, 'submit'])->middleware('throttle:5,1')->name('contact.submit');

    // Domain Search (public — no auth required)
    Route::get('domain-search', [DomainSearchController::class, 'index'])->name('domain.search');
    // One search fans out into a WHOIS query for the name and one for every
    // suggested ending - outbound connections the registries throttle
    // themselves, made in the operator's name by anybody at all.
    Route::post('domain-search', [DomainSearchController::class, 'check'])->middleware('throttle:20,1')->name('domain.check');
    Route::get('domain-pricing', [DomainSearchController::class, 'pricing'])->name('domain.pricing');

    // Store — public browsing
    Route::get('store', [CartController::class, 'store'])->name('store');
    Route::get('store/configure/{product:slug}', [CartController::class, 'configure'])->name('store.configure');

    // 2FA verification (requires login but not 2FA yet)
    Route::middleware('auth')->withoutMiddleware([TwoFactorVerify::class])->group(function () {
        Route::get('2fa', [AuthController::class, 'show2faVerify'])->name('2fa.verify');
        // Six digits, and the form that comes before it allows ten tries a
        // minute. Unthrottled, the second factor is only as good as the
        // patience of whoever already has the password.
        Route::post('2fa', [AuthController::class, 'verify2fa'])->middleware('throttle:10,1')->name('2fa.verify.submit');
    });

    Route::middleware(['auth', '2fa'])->group(function () {
        Route::get('/', [HomeController::class, 'index'])->name('home');
        // Signing out must stay reachable while the code is outstanding.
        Route::post('logout', [AuthController::class, 'logout'])
            ->withoutMiddleware([TwoFactorVerify::class])->name('logout');

        // Services
        Route::get('services', [ServiceController::class, 'index'])->name('services.index');
        Route::get('services/{service}', [ServiceController::class, 'show'])->name('services.show');
        Route::get('services/{service}/login', [ServiceController::class, 'loginToPanel'])->name('services.login');
        Route::get('services/{service}/usage', [ServiceController::class, 'usage'])->name('services.usage');
        Route::get('services/{service}/cancel', [ServiceController::class, 'requestCancellation'])->name('services.cancel');
        Route::post('services/{service}/cancel', [ServiceController::class, 'submitCancellation'])->name('services.cancel.submit');
        Route::get('services/{service}/upgrade', [ServiceController::class, 'upgrade'])->name('services.upgrade');
        Route::post('services/{service}/upgrade', [ServiceController::class, 'processUpgrade'])->name('services.upgrade.process');
        Route::post('services/{service}/autorenew', [ServiceController::class, 'toggleAutoRenew'])->name('services.autorenew');
        Route::post('services/{service}/addons', [ServiceController::class, 'storeAddon'])->name('services.addons.store');
        Route::post('services/{service}/addons/{addon}/cancel', [ServiceController::class, 'cancelAddon'])->name('services.addons.cancel');

        // Hosting management (Panelica-only, feature-gated in the controller)
        Route::get('services/{service}/emails', [ServiceController::class, 'emails'])->name('services.emails');
        Route::post('services/{service}/emails', [ServiceController::class, 'storeEmail'])->name('services.emails.store');
        Route::post('services/{service}/emails/delete', [ServiceController::class, 'destroyEmail'])->name('services.emails.destroy');
        Route::post('services/{service}/emails/password', [ServiceController::class, 'updateEmailPassword'])->name('services.emails.password');
        Route::get('services/{service}/ftp', [ServiceController::class, 'ftp'])->name('services.ftp');
        Route::post('services/{service}/ftp', [ServiceController::class, 'storeFtp'])->name('services.ftp.store');
        Route::post('services/{service}/ftp/delete', [ServiceController::class, 'destroyFtp'])->name('services.ftp.destroy');
        Route::post('services/{service}/ftp/password', [ServiceController::class, 'updateFtpPassword'])->name('services.ftp.password');
        Route::get('services/{service}/subdomains', [ServiceController::class, 'subdomains'])->name('services.subdomains');
        Route::post('services/{service}/subdomains', [ServiceController::class, 'storeSubdomain'])->name('services.subdomains.store');
        Route::post('services/{service}/subdomains/delete', [ServiceController::class, 'destroySubdomain'])->name('services.subdomains.destroy');
        Route::get('services/{service}/cron', [ServiceController::class, 'cron'])->name('services.cron');
        Route::post('services/{service}/cron', [ServiceController::class, 'storeCron'])->name('services.cron.store');
        Route::post('services/{service}/cron/toggle', [ServiceController::class, 'toggleCron'])->name('services.cron.toggle');
        Route::post('services/{service}/cron/run', [ServiceController::class, 'runCron'])->name('services.cron.run');
        Route::post('services/{service}/cron/delete', [ServiceController::class, 'destroyCron'])->name('services.cron.destroy');
        Route::get('services/{service}/dns', [ServiceController::class, 'dns'])->name('services.dns');
        Route::post('services/{service}/dns', [ServiceController::class, 'storeDns'])->name('services.dns.store');
        Route::post('services/{service}/dns/delete', [ServiceController::class, 'destroyDns'])->name('services.dns.destroy');
        Route::get('services/{service}/databases', [ServiceController::class, 'databases'])->name('services.databases');
        Route::post('services/{service}/databases', [ServiceController::class, 'storeDatabase'])->name('services.databases.store');
        Route::post('services/{service}/databases/delete', [ServiceController::class, 'destroyDatabase'])->name('services.databases.destroy');
        Route::post('services/{service}/databases/users', [ServiceController::class, 'storeDatabaseUser'])->name('services.databases.users.store');
        Route::post('services/{service}/databases/users/delete', [ServiceController::class, 'destroyDatabaseUser'])->name('services.databases.users.destroy');
        Route::post('services/{service}/databases/users/password', [ServiceController::class, 'updateDatabaseUserPassword'])->name('services.databases.users.password');
        Route::get('services/{service}/files', [ServiceController::class, 'files'])->name('services.files');
        Route::get('services/{service}/files/download', [ServiceController::class, 'filesDownload'])->name('services.files.download');
        Route::get('services/{service}/files/edit', [ServiceController::class, 'filesEdit'])->name('services.files.edit');
        Route::post('services/{service}/files/save', [ServiceController::class, 'filesWrite'])->name('services.files.save');
        Route::post('services/{service}/files/create', [ServiceController::class, 'filesCreate'])->name('services.files.create');
        Route::post('services/{service}/files/upload', [ServiceController::class, 'filesUpload'])->name('services.files.upload');
        Route::post('services/{service}/files/rename', [ServiceController::class, 'filesRename'])->name('services.files.rename');
        Route::post('services/{service}/files/delete', [ServiceController::class, 'filesDelete'])->name('services.files.delete');

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
        Route::post('invoices/{invoice}/payment-notification', [InvoiceController::class, 'submitPaymentNotification'])->name('invoices.payment-notification');

        // Quotes
        Route::get('quotes', [QuoteController::class, 'index'])->name('quotes.index');
        Route::get('quotes/{quote}', [QuoteController::class, 'show'])->name('quotes.show');
        Route::post('quotes/{quote}/accept', [QuoteController::class, 'accept'])->name('quotes.accept');
        Route::post('quotes/{quote}/decline', [QuoteController::class, 'decline'])->name('quotes.decline');

        // Payment methods
        Route::get('payment-methods', [PaymentMethodController::class, 'index'])->name('payment-methods.index');
        Route::post('payment-methods', [PaymentMethodController::class, 'store'])->name('payment-methods.store');
        Route::post('payment-methods/{paymentMethod}/default', [PaymentMethodController::class, 'setDefault'])->name('payment-methods.default');
        Route::delete('payment-methods/{paymentMethod}', [PaymentMethodController::class, 'destroy'])->name('payment-methods.destroy');

        // Email history
        Route::get('emails', [EmailController::class, 'index'])->name('emails.index');
        Route::get('emails/{email}', [EmailController::class, 'show'])->name('emails.show');

        // Tickets
        Route::get('tickets', [TicketController::class, 'index'])->name('tickets.index');
        Route::get('tickets/create', [TicketController::class, 'create'])->name('tickets.create');
        Route::post('tickets', [TicketController::class, 'store'])->name('tickets.store');
        Route::get('tickets/{ticket}', [TicketController::class, 'show'])->name('tickets.show');
        Route::post('tickets/{ticket}/reply', [TicketController::class, 'reply'])->name('tickets.reply');
        Route::get('tickets/{ticket}/attachment', [TicketController::class, 'downloadAttachment'])->name('tickets.attachment');
        Route::get('tickets/{ticket}/reply/{replyId}/attachment', [TicketController::class, 'downloadAttachment'])->name('tickets.reply.attachment');

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
        Route::post('account/switch/{client}', [AccountController::class, 'switchAccount'])->name('account.switch');
        Route::put('account/contacts/{contact}', [AccountController::class, 'updateContact'])->name('account.contacts.update');
        Route::delete('account/contacts/{contact}', [AccountController::class, 'destroyContact'])->name('account.contacts.destroy');
        Route::get('account/payment-methods', [AccountController::class, 'paymentMethods'])->name('account.payment_methods');
        Route::get('account/security', [AccountController::class, 'security'])->name('account.security');
        Route::post('account/security/sessions/{sessionId}/logout', [AccountController::class, 'logoutSession'])->name('account.security.logout_session');
        Route::match(['get', 'post'], '2fa/enable', [AuthController::class, 'enable2fa'])->name('2fa.enable');
        Route::post('2fa/disable', [AuthController::class, 'disable2fa'])->name('2fa.disable');

        // ── SSL Certificates ──────────────────────────────────────────
        Route::prefix('ssl')->name('ssl.')->group(function () {
            Route::get('/', [SslController::class, 'index'])->name('index');
            Route::get('/{sslOrder}', [SslController::class, 'show'])->name('show');
            Route::get('/{sslOrder}/configure', [SslController::class, 'configure'])->name('configure');
            Route::post('/{sslOrder}/configure', [SslController::class, 'submitConfiguration'])->name('submitConfiguration');
            Route::get('/{sslOrder}/approver-emails', [SslController::class, 'getApproverEmails'])->name('approverEmails');
            Route::get('/{sslOrder}/download', [SslController::class, 'downloadCert'])->name('download');
            Route::post('/{sslOrder}/resend-validation', [SslController::class, 'resendValidation'])->name('resendValidation');
        });
    });
});
