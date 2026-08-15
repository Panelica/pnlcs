<?php

use App\Http\Controllers\Admin\AddonController;
use App\Http\Controllers\Admin\AffiliateController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\BulkActionController;
use App\Http\Controllers\Admin\CalendarController;
use App\Http\Controllers\Admin\ClientController;
use App\Http\Controllers\Admin\ConfigController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DomainController;
use App\Http\Controllers\Admin\InvoiceController;
use App\Http\Controllers\Admin\LogController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\PaymentNotificationController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ProjectController;
use App\Http\Controllers\Admin\QuoteController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\ServiceController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\SslOrderController;
use App\Http\Controllers\Admin\TicketController;
use App\Http\Controllers\Admin\TranslationController;
use App\Http\Controllers\Admin\WhoisController;
use App\Http\Middleware\AdminTwoFactorVerify;
use Illuminate\Support\Facades\Route;

Route::get('/admin/login', [AuthController::class, 'showLogin'])->name('admin.login');
Route::post('/admin/login', [AuthController::class, 'login'])->middleware('throttle:10,1')->name('admin.login.submit');
Route::get('/admin/2fa', [AuthController::class, 'show2faVerify'])->name('admin.2fa.verify');
Route::post('/admin/2fa', [AuthController::class, 'verify2fa'])->middleware('throttle:10,1')->name('admin.2fa.verify.submit');

Route::middleware(['admin.auth', 'admin.2fa'])->prefix('admin')->name('admin.')->group(function () {
    // Dashboard, logout, search — no permission required (all admins)
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    // Signing out must stay reachable while the code is outstanding.
    Route::post('/logout', [AuthController::class, 'logout'])
        ->withoutMiddleware([AdminTwoFactorVerify::class])->name('logout');

    // =============================================
    // Clients CRUD
    // =============================================
    Route::middleware('admin.permission:list_clients')->group(function () {
        Route::get('clients', [ClientController::class, 'index'])->name('clients.index');
        Route::get('clients/export/csv', [ClientController::class, 'exportCsv'])->name('clients.export');
    });
    Route::middleware('admin.permission:create_clients')->group(function () {
        Route::get('clients/create', [ClientController::class, 'create'])->name('clients.create');
        Route::post('clients', [ClientController::class, 'store'])->name('clients.store');
    });
    Route::middleware('admin.permission:view_clients')->group(function () {
        Route::get('clients/{client}', [ClientController::class, 'show'])->name('clients.show');
    });

    Route::middleware('admin.permission:edit_clients')->group(function () {
        Route::get('clients/{client}/edit', [ClientController::class, 'edit'])->name('clients.edit');
        Route::put('clients/{client}', [ClientController::class, 'update'])->name('clients.update');
        Route::patch('clients/{client}', [ClientController::class, 'update']);
        Route::post('clients/{client}/notes', [ClientController::class, 'storeNote'])->name('clients.notes.store');
        Route::post('clients/{client}/impersonate', [ClientController::class, 'impersonate'])->name('clients.impersonate');
        Route::get('impersonation/stop', [ClientController::class, 'stopImpersonation'])->name('clients.stop-impersonation');
    });
    Route::middleware('admin.permission:delete_clients')->group(function () {
        Route::delete('clients/{client}', [ClientController::class, 'destroy'])->name('clients.destroy');
    });

    // =============================================
    // Products
    // =============================================
    Route::middleware('admin.permission:list_products')->group(function () {
        // The product form asks the server which plans it offers.
        Route::get('products/packages', [ProductController::class, 'packages'])->name('products.packages');
        Route::get('products', [ProductController::class, 'index'])->name('products.index');
    });
    Route::middleware('admin.permission:manage_products')->group(function () {
        Route::get('products/create', [ProductController::class, 'create'])->name('products.create');
        Route::post('products', [ProductController::class, 'store'])->name('products.store');
        Route::get('products/{product}/edit', [ProductController::class, 'edit'])->name('products.edit');
        Route::put('products/{product}', [ProductController::class, 'update'])->name('products.update');
        Route::delete('products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');
        Route::get('products/groups/create', [ProductController::class, 'createGroup'])->name('products.groups.create');
        Route::post('products/groups', [ProductController::class, 'storeGroup'])->name('products.groups.store');
    });

    // =============================================
    // Orders
    // =============================================
    Route::middleware('admin.permission:list_orders')->group(function () {
        Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
    });
    Route::middleware('admin.permission:view_orders')->group(function () {
        Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    });
    Route::middleware('admin.permission:manage_orders')->group(function () {
        Route::post('orders/{order}/accept', [OrderController::class, 'accept'])->name('orders.accept');
        Route::post('orders/{order}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');
        Route::post('orders/{order}/fraud', [OrderController::class, 'markFraud'])->name('orders.fraud');
        Route::put('orders/{order}/service/{service}/domain', [OrderController::class, 'updateServiceDomain'])->name('orders.service-domain');
        Route::delete('orders/{order}', [OrderController::class, 'delete'])->name('orders.delete');
    });

    // =============================================
    // Invoices
    // =============================================
    Route::middleware('admin.permission:list_invoices')->group(function () {
        Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
        Route::get('invoices/export/csv', [InvoiceController::class, 'exportCsv'])->name('invoices.export');
    });
    Route::middleware('admin.permission:create_invoices')->group(function () {
        Route::get('invoices/create', [InvoiceController::class, 'create'])->name('invoices.create');
        Route::post('invoices', [InvoiceController::class, 'store'])->name('invoices.store');
    });
    Route::middleware('admin.permission:view_invoices')->group(function () {
        Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
        Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'downloadPdf'])->name('invoices.pdf');
    });

    Route::middleware('admin.permission:manage_invoices')->group(function () {
        Route::post('invoices/{invoice}/mark-paid', [InvoiceController::class, 'markPaid'])->name('invoices.mark-paid');
        Route::post('invoices/{invoice}/cancel', [InvoiceController::class, 'cancel'])->name('invoices.cancel');
        Route::post('invoices/{invoice}/refund', [InvoiceController::class, 'refund'])->name('invoices.refund');

        // Offline payment notifications (bank transfer review queue)
        Route::get('payment-notifications', [PaymentNotificationController::class, 'index'])->name('payment-notifications.index');
        Route::get('payment-notifications/{paymentNotification}/receipt', [PaymentNotificationController::class, 'receipt'])->name('payment-notifications.receipt');
        Route::post('payment-notifications/{paymentNotification}/approve', [PaymentNotificationController::class, 'approve'])->name('payment-notifications.approve');
        Route::post('payment-notifications/{paymentNotification}/reject', [PaymentNotificationController::class, 'reject'])->name('payment-notifications.reject');
    });

    // =============================================
    // Services
    // =============================================
    Route::middleware('admin.permission:list_services')->group(function () {
        Route::get('services', [ServiceController::class, 'index'])->name('services.index');
    });
    Route::middleware('admin.permission:view_services')->group(function () {
        Route::get('services/{service}', [ServiceController::class, 'show'])->name('services.show');
    });
    Route::middleware('admin.permission:manage_services')->group(function () {
        Route::post('services/{service}/module/{action}', [ServiceController::class, 'moduleAction'])->name('services.module-action');
        Route::post('services/{service}/addons', [ServiceController::class, 'storeAddon'])->name('services.addons.store');
        Route::post('services/{service}/addons/{addon}/cancel', [ServiceController::class, 'cancelAddon'])->name('services.addons.cancel');
        Route::put('services/{service}/next-due', [ServiceController::class, 'updateNextDue'])->name('services.next-due');
        Route::put('services/{service}/status', [ServiceController::class, 'updateStatus'])->name('services.status');
        Route::delete('services/{service}', [ServiceController::class, 'destroy'])->name('services.destroy');
    });

    // =============================================
    // Domains
    // =============================================
    Route::middleware('admin.permission:list_domains')->group(function () {
        Route::get('domains', [DomainController::class, 'index'])->name('domains.index');
    });
    Route::middleware('admin.permission:manage_domains')->group(function () {
        Route::get('domains/{domain}', [DomainController::class, 'show'])->name('domains.show');
        Route::post('domains/{domain}/sync', [DomainController::class, 'sync'])->name('domains.sync');
        Route::post('domains/{domain}/renew', [DomainController::class, 'renew'])->name('domains.renew');
        Route::post('domains/{domain}/nameservers', [DomainController::class, 'updateNameservers'])->name('domains.nameservers');
        Route::post('domains/{domain}/lock', [DomainController::class, 'toggleLock'])->name('domains.lock');
        Route::post('domains/{domain}/autorenew', [DomainController::class, 'toggleAutoRenew'])->name('domains.autorenew');
        Route::get('domains/{domain}/epp', [DomainController::class, 'getEppCode'])->name('domains.epp');
    });

    // =============================================
    // Tickets
    // =============================================
    Route::middleware('admin.permission:list_tickets')->group(function () {
        Route::get('tickets', [TicketController::class, 'index'])->name('tickets.index');
    });
    Route::middleware('admin.permission:view_tickets')->group(function () {
        Route::get('tickets/{ticket}', [TicketController::class, 'show'])->name('tickets.show');
    });
    Route::middleware('admin.permission:reply_tickets')->group(function () {
        Route::post('tickets/{ticket}/reply', [TicketController::class, 'reply'])->name('tickets.reply');
    });

    // =============================================
    // Settings — manage_settings
    // =============================================
    Route::middleware('admin.permission:manage_settings')->group(function () {
        Route::get('settings', [SettingController::class, 'general'])->name('settings.general');
        Route::post('settings', [SettingController::class, 'updateGeneral'])->name('settings.general.update');
        Route::post('settings/test-email', [SettingController::class, 'testEmail'])->name('settings.test-email');

        // Appearance / Theme
        Route::get('settings/appearance', [SettingController::class, 'appearance'])->name('settings.appearance');
        Route::post('settings/appearance', [SettingController::class, 'updateAppearance'])->name('settings.appearance.update');
        Route::post('settings/appearance/logo', [SettingController::class, 'uploadLogo'])->name('settings.appearance.logo');
        Route::post('settings/appearance/favicon', [SettingController::class, 'uploadFavicon'])->name('settings.appearance.favicon');
        Route::delete('settings/appearance/logo', [SettingController::class, 'removeLogo'])->name('settings.appearance.logo.remove');
        Route::delete('settings/appearance/favicon', [SettingController::class, 'removeFavicon'])->name('settings.appearance.favicon.remove');

        // Homepage Builder
        Route::get('settings/appearance/sections', [SettingController::class, 'sectionsList'])->name('settings.appearance.sections');
        Route::post('settings/appearance/sections/reorder', [SettingController::class, 'sectionsReorder'])->name('settings.appearance.sections.reorder');
        Route::put('settings/appearance/sections/{section}', [SettingController::class, 'sectionUpdate'])->name('settings.appearance.sections.update');
        Route::get('settings/appearance/sections/{slug}/content', [SettingController::class, 'sectionContent'])->name('settings.appearance.sections.content');
        Route::post('settings/appearance/sections/{slug}/content', [SettingController::class, 'sectionContentSave'])->name('settings.appearance.sections.content.save');

        // White-Label
        Route::post('settings/appearance/whitelabel', [SettingController::class, 'whitelabelSave'])->name('settings.appearance.whitelabel');

        // Dark Mode
        Route::post('settings/appearance/darkmode', [SettingController::class, 'darkModeSave'])->name('settings.appearance.darkmode');

        // Theme CRUD (WordPress-style)
        Route::post('settings/appearance/theme/activate', [SettingController::class, 'activateTheme'])->name('settings.appearance.theme.activate');
        Route::post('settings/appearance/theme/install', [SettingController::class, 'installTheme'])->name('settings.appearance.theme.install');
        Route::delete('settings/appearance/theme/{slug}', [SettingController::class, 'deleteTheme'])->name('settings.appearance.theme.delete');
        Route::get('settings/appearance/theme/{slug}/download', [SettingController::class, 'downloadTheme'])->name('settings.appearance.theme.download');
    });

    // My Account — no permission required (all admins)
    Route::get('my-account', [SettingController::class, 'myAccount'])->name('my-account');
    Route::post('my-account', [SettingController::class, 'updateMyAccount'])->name('my-account.update');
    Route::match(['get', 'post'], '2fa/enable', [AuthController::class, 'enable2fa'])->name('2fa.enable');
    Route::post('2fa/disable', [AuthController::class, 'disable2fa'])->name('2fa.disable');

    // =============================================
    // Reports — view_reports
    // =============================================
    Route::middleware('admin.permission:view_reports')->group(function () {
        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('reports/{slug}', [ReportController::class, 'show'])->name('reports.show');
        Route::get('reports/{slug}/export', [ReportController::class, 'export'])->name('reports.export');
    });

    // =============================================
    // Configuration Routes
    // =============================================
    Route::prefix('config')->name('config.')->group(function () {
        // Staff & Security — manage_staff, manage_roles
        Route::middleware('admin.permission:manage_staff')->group(function () {
            Route::get('admins', [ConfigController::class, 'admins'])->name('admins');
            Route::post('admins', [ConfigController::class, 'storeAdmin'])->name('admins.store');
            Route::put('admins/{admin}', [ConfigController::class, 'updateAdmin'])->name('admins.update');
            Route::delete('admins/{admin}', [ConfigController::class, 'destroyAdmin'])->name('admins.destroy');
        });

        Route::middleware('admin.permission:manage_roles')->group(function () {
            Route::get('admin-roles', [ConfigController::class, 'adminRoles'])->name('admin-roles');
            Route::post('admin-roles', [ConfigController::class, 'storeRole'])->name('admin-roles.store');
            Route::put('admin-roles/{role}', [ConfigController::class, 'updateRole'])->name('admin-roles.update');
            Route::delete('admin-roles/{role}', [ConfigController::class, 'destroyRole'])->name('admin-roles.destroy');
        });

        // API Credentials — manage_staff (admin-level feature)
        Route::middleware('admin.permission:manage_staff')->group(function () {
            Route::get('api-credentials', [ConfigController::class, 'apiCredentials'])->name('api-credentials');
            Route::post('api-credentials', [ConfigController::class, 'storeApiCredential'])->name('api-credentials.store');
            Route::delete('api-credentials/{credential}', [ConfigController::class, 'destroyApiCredential'])->name('api-credentials.destroy');
        });

        // Billing — manage_currencies, manage_tax, manage_promotions
        Route::middleware('admin.permission:manage_currencies')->group(function () {
            Route::get('currencies', [ConfigController::class, 'currencies'])->name('currencies');
            Route::post('currencies', [ConfigController::class, 'storeCurrency'])->name('currencies.store');
            Route::put('currencies/{currency}', [ConfigController::class, 'updateCurrency'])->name('currencies.update');
            Route::delete('currencies/{currency}', [ConfigController::class, 'destroyCurrency'])->name('currencies.destroy');
            Route::post('currencies/{currency}/default', [ConfigController::class, 'setDefaultCurrency'])->name('currencies.default');
        });

        Route::middleware('admin.permission:manage_settings')->group(function () {
            Route::get('custom-fields', [ConfigController::class, 'customFields'])->name('custom-fields');
            Route::post('custom-fields', [ConfigController::class, 'storeCustomField'])->name('custom-fields.store');
            Route::put('custom-fields/{customField}', [ConfigController::class, 'updateCustomField'])->name('custom-fields.update');
            Route::delete('custom-fields/{customField}', [ConfigController::class, 'destroyCustomField'])->name('custom-fields.destroy');
        });

        Route::middleware('admin.permission:manage_tax')->group(function () {
            Route::get('tax', [ConfigController::class, 'tax'])->name('tax');
            Route::post('tax', [ConfigController::class, 'storeTax'])->name('tax.store');
            Route::put('tax/{taxRule}', [ConfigController::class, 'updateTax'])->name('tax.update');
            Route::delete('tax/{taxRule}', [ConfigController::class, 'destroyTax'])->name('tax.destroy');
        });

        Route::middleware('admin.permission:manage_promotions')->group(function () {
            Route::get('promotions', [ConfigController::class, 'promotions'])->name('promotions');
            Route::post('promotions', [ConfigController::class, 'storePromotion'])->name('promotions.store');
            Route::put('promotions/{promotion}', [ConfigController::class, 'updatePromotion'])->name('promotions.update');
            Route::delete('promotions/{promotion}', [ConfigController::class, 'destroyPromotion'])->name('promotions.destroy');
        });

        // Servers & Domains — manage_servers
        Route::middleware('admin.permission:manage_servers')->group(function () {
            Route::get('servers', [ConfigController::class, 'servers'])->name('servers');
            Route::post('servers', [ConfigController::class, 'storeServer'])->name('servers.store');
            Route::put('servers/{server}', [ConfigController::class, 'updateServer'])->name('servers.update');
            Route::delete('servers/{server}', [ConfigController::class, 'destroyServer'])->name('servers.destroy');
            Route::post('servers/{server}/test', [ConfigController::class, 'testServerConnection'])->name('servers.test');

            Route::get('server-groups', [ConfigController::class, 'serverGroups'])->name('server-groups');
            Route::post('server-groups', [ConfigController::class, 'storeServerGroup'])->name('server-groups.store');
            Route::put('server-groups/{serverGroup}', [ConfigController::class, 'updateServerGroup'])->name('server-groups.update');
            Route::delete('server-groups/{serverGroup}', [ConfigController::class, 'destroyServerGroup'])->name('server-groups.destroy');

            Route::get('domain-pricing', [ConfigController::class, 'domainPricing'])->name('domain-pricing');
            Route::post('domain-pricing', [ConfigController::class, 'storeTld'])->name('domain-pricing.store');
            Route::put('domain-pricing/{domainPricing}', [ConfigController::class, 'updateTld'])->name('domain-pricing.update');
            Route::delete('domain-pricing/{domainPricing}', [ConfigController::class, 'destroyTld'])->name('domain-pricing.destroy');
        });

        // Gateways — manage_gateways
        Route::middleware('admin.permission:manage_gateways')->group(function () {
            Route::get('gateways', [ConfigController::class, 'gateways'])->name('gateways');
            Route::post('gateways/{gateway}/settings', [ConfigController::class, 'updateGatewaySettings'])->name('gateways.settings.update');
        });

        // Registrars — manage_registrars
        Route::middleware('admin.permission:manage_registrars')->group(function () {
            Route::get('registrars', [ConfigController::class, 'registrars'])->name('registrars');
            Route::post('registrars/{registrar}/settings', [ConfigController::class, 'updateRegistrarSettings'])->name('registrars.settings.update');
        });

        // Support — manage_ticket_config
        Route::middleware('admin.permission:manage_ticket_config')->group(function () {
            Route::get('ticket-departments', [ConfigController::class, 'ticketDepartments'])->name('ticket-departments');
            Route::post('ticket-departments', [ConfigController::class, 'storeTicketDepartment'])->name('ticket-departments.store');
            Route::put('ticket-departments/{department}', [ConfigController::class, 'updateTicketDepartment'])->name('ticket-departments.update');
            Route::delete('ticket-departments/{department}', [ConfigController::class, 'destroyTicketDepartment'])->name('ticket-departments.destroy');

            Route::get('ticket-statuses', [ConfigController::class, 'ticketStatuses'])->name('ticket-statuses');
            Route::post('ticket-statuses', [ConfigController::class, 'storeTicketStatus'])->name('ticket-statuses.store');
            Route::put('ticket-statuses/{status}', [ConfigController::class, 'updateTicketStatus'])->name('ticket-statuses.update');
            Route::delete('ticket-statuses/{status}', [ConfigController::class, 'destroyTicketStatus'])->name('ticket-statuses.destroy');

            Route::get('ticket-escalation', [ConfigController::class, 'ticketEscalation'])->name('ticket-escalation');
            Route::post('ticket-escalation', [ConfigController::class, 'storeTicketEscalation'])->name('ticket-escalation.store');
            Route::put('ticket-escalation/{id}', [ConfigController::class, 'updateTicketEscalation'])->name('ticket-escalation.update');
            Route::delete('ticket-escalation/{id}', [ConfigController::class, 'deleteTicketEscalation'])->name('ticket-escalation.destroy');

            Route::get('ticket-spam', [ConfigController::class, 'ticketSpam'])->name('ticket-spam');
            Route::put('ticket-spam', [ConfigController::class, 'updateTicketSpam'])->name('ticket-spam.update');
            Route::post('ticket-spam/filters', [ConfigController::class, 'storeTicketSpamFilter'])->name('ticket-spam.filter.store');
            Route::delete('ticket-spam/filters/{id}', [ConfigController::class, 'destroyTicketSpamFilter'])->name('ticket-spam.filter.destroy');
        });

        // Email Templates — manage_email_templates
        Route::middleware('admin.permission:manage_email_templates')->group(function () {
            Route::get('email-templates', [ConfigController::class, 'emailTemplates'])->name('email-templates');
            Route::put('email-templates/{template}', [ConfigController::class, 'updateEmailTemplate'])->name('email-templates.update');
        });

        // Announcements — manage_announcements
        Route::middleware('admin.permission:manage_announcements')->group(function () {
            Route::get('announcements', [ConfigController::class, 'announcements'])->name('announcements');
            Route::post('announcements', [ConfigController::class, 'storeAnnouncement'])->name('announcements.store');
            Route::put('announcements/{announcement}', [ConfigController::class, 'updateAnnouncement'])->name('announcements.update');
            Route::delete('announcements/{announcement}', [ConfigController::class, 'destroyAnnouncement'])->name('announcements.destroy');
        });

        // Knowledge Base — manage_kb
        Route::middleware('admin.permission:manage_kb')->group(function () {
            Route::get('knowledge-base', [ConfigController::class, 'knowledgeBase'])->name('knowledge-base');
            Route::post('knowledge-base/categories', [ConfigController::class, 'storeKbCategory'])->name('knowledge-base.categories.store');
            Route::post('knowledge-base/articles', [ConfigController::class, 'storeKbArticle'])->name('knowledge-base.articles.store');
            Route::put('knowledge-base/articles/{article}', [ConfigController::class, 'updateKbArticle'])->name('knowledge-base.articles.update');
            Route::delete('knowledge-base/articles/{article}', [ConfigController::class, 'destroyKbArticle'])->name('knowledge-base.articles.destroy');

            Route::get('downloads', [ConfigController::class, 'downloads'])->name('downloads');
            Route::post('downloads/categories', [ConfigController::class, 'storeDownloadCategory'])->name('downloads.categories.store');
            Route::delete('downloads/categories/{category}', [ConfigController::class, 'destroyDownloadCategory'])->name('downloads.categories.destroy');
            Route::post('downloads', [ConfigController::class, 'storeDownload'])->name('downloads.store');
            Route::delete('downloads/{download}', [ConfigController::class, 'destroyDownload'])->name('downloads.destroy');

            Route::get('network-issues', [ConfigController::class, 'networkIssues'])->name('network-issues');
            Route::post('network-issues', [ConfigController::class, 'storeNetworkIssue'])->name('network-issues.store');
            Route::put('network-issues/{issue}', [ConfigController::class, 'updateNetworkIssue'])->name('network-issues.update');
            Route::delete('network-issues/{issue}', [ConfigController::class, 'destroyNetworkIssue'])->name('network-issues.destroy');
        });

        // Security — manage_security
        Route::middleware('admin.permission:manage_security')->group(function () {
            Route::get('banned-ips', [ConfigController::class, 'bannedIps'])->name('banned-ips');
            Route::post('banned-ips', [ConfigController::class, 'storeBannedIp'])->name('banned-ips.store');
            Route::delete('banned-ips/{bannedIp}', [ConfigController::class, 'destroyBannedIp'])->name('banned-ips.destroy');

            Route::get('banned-emails', [ConfigController::class, 'bannedEmails'])->name('banned-emails');
            Route::post('banned-emails', [ConfigController::class, 'storeBannedEmail'])->name('banned-emails.store');
            Route::delete('banned-emails/{bannedEmail}', [ConfigController::class, 'destroyBannedEmail'])->name('banned-emails.destroy');
        });

        // Activity Log — view_activity_log
        Route::middleware('admin.permission:view_activity_log')->group(function () {
            Route::get('activity-log', [ConfigController::class, 'activityLog'])->name('activity-log');
        });

        // Notifications config — manage_settings
        Route::middleware('admin.permission:manage_settings')->group(function () {
            Route::get('notifications', [ConfigController::class, 'notifications'])->name('notifications');
            Route::post('notification-providers', [ConfigController::class, 'storeNotificationProvider'])->name('notification-providers.store');
            Route::put('notification-providers/{id}', [ConfigController::class, 'updateNotificationProvider'])->name('notification-providers.update');
            Route::delete('notification-providers/{id}', [ConfigController::class, 'destroyNotificationProvider'])->name('notification-providers.destroy');
            Route::post('notification-rules', [ConfigController::class, 'storeNotificationRule'])->name('notification-rules.store');
            Route::delete('notification-rules/{id}', [ConfigController::class, 'destroyNotificationRule'])->name('notification-rules.destroy');
        });

        // Product Addons — manage_products
        Route::middleware('admin.permission:manage_products')->group(function () {
            Route::get('addons', [ConfigController::class, 'addons'])->name('addons');
            Route::post('addons', [ConfigController::class, 'storeAddon'])->name('addons.store');
            Route::put('addons/{id}', [ConfigController::class, 'updateAddon'])->name('addons.update');
            Route::delete('addons/{id}', [ConfigController::class, 'destroyAddon'])->name('addons.destroy');
            Route::get('addons/modules', [AddonController::class, 'index'])->name('addons.modules');
            Route::get('addons/modules/{name}', [AddonController::class, 'show'])->name('addons.modules.show');
            Route::post('addons/modules/{name}/toggle', [AddonController::class, 'toggle'])->name('addons.modules.toggle');

            Route::get('bundles', [ConfigController::class, 'bundles'])->name('bundles');
            Route::post('bundles', [ConfigController::class, 'storeBundle'])->name('bundles.store');
            Route::delete('bundles/{id}', [ConfigController::class, 'destroyBundle'])->name('bundles.destroy');

            Route::get('config-options', [ConfigController::class, 'configOptions'])->name('config-options');
            Route::post('config-option-groups', [ConfigController::class, 'storeConfigOptionGroup'])->name('config-option-groups.store');
            Route::put('config-option-groups/{id}', [ConfigController::class, 'updateConfigOptionGroup'])->name('config-option-groups.update');
            Route::delete('config-option-groups/{id}', [ConfigController::class, 'deleteConfigOptionGroup'])->name('config-option-groups.destroy');
            Route::post('config-options-store', [ConfigController::class, 'storeConfigOption'])->name('config-options.store');
            Route::delete('config-options/{id}', [ConfigController::class, 'deleteConfigOption'])->name('config-options.destroy');
            Route::post('config-option-subs', [ConfigController::class, 'storeConfigOptionSub'])->name('config-option-subs.store');
            Route::delete('config-option-subs/{id}', [ConfigController::class, 'deleteConfigOptionSub'])->name('config-option-subs.destroy');
            Route::post('config-option-groups/{id}/products', [ConfigController::class, 'linkConfigOptionGroup'])->name('config-option-groups.link');
        });

        // No permission: todo (an admin's own scratchpad, not business data)
        Route::get('todo', [ConfigController::class, 'todoList'])->name('todo');
        Route::post('todo', [ConfigController::class, 'storeTodo'])->name('todo.store');
        Route::put('todo/{todo}', [ConfigController::class, 'updateTodo'])->name('todo.update');
        Route::delete('todo/{todo}', [ConfigController::class, 'destroyTodo'])->name('todo.destroy');

        // Same data as admin.affiliates.index, which requires this.
        Route::middleware('admin.permission:manage_affiliates')->group(function () {
            Route::get('affiliates', [ConfigController::class, 'affiliates'])->name('affiliates');
        });

        // Same data as admin.quotes.index, which requires this.
        Route::middleware('admin.permission:list_quotes|manage_quotes')->group(function () {
            Route::get('quotes', [ConfigController::class, 'quotes'])->name('quotes');
        });

        Route::middleware('admin.permission:manage_invoices')->group(function () {
            Route::get('billable-items', [ConfigController::class, 'billableItems'])->name('billable-items');
            Route::post('billable-items', [ConfigController::class, 'storeBillableItem'])->name('billable-items.store');
            Route::delete('billable-items/{item}', [ConfigController::class, 'destroyBillableItem'])->name('billable-items.destroy');
        });

        // Every payment the business has taken, with the client on each one.
        Route::middleware('admin.permission:view_reports')->group(function () {
            Route::get('transactions', [ConfigController::class, 'transactions'])->name('transactions');
        });

        Route::middleware('admin.permission:manage_settings')->group(function () {
            Route::get('automation', [ConfigController::class, 'automation'])->name('automation');
        });
        Route::middleware('admin.permission:edit_clients')->group(function () {
            Route::get('client-groups', [ConfigController::class, 'clientGroups'])->name('client-groups');
            Route::post('client-groups', [ConfigController::class, 'storeClientGroup'])->name('client-groups.store');
            Route::put('client-groups/{group}', [ConfigController::class, 'updateClientGroup'])->name('client-groups.update');
            Route::delete('client-groups/{group}', [ConfigController::class, 'destroyClientGroup'])->name('client-groups.destroy');
        });

        Route::middleware('admin.permission:view_system|manage_security')->group(function () {
            Route::get('system-database', [ConfigController::class, 'systemDatabase'])->name('system-database');
            Route::get('system-phpinfo', [ConfigController::class, 'systemPhpInfo'])->name('system-phpinfo');
        });

        // SSL Modules — manage_servers
        Route::middleware('admin.permission:manage_servers')->group(function () {
            Route::get('ssl-modules', [ConfigController::class, 'sslModules'])->name('sslModules');
            Route::post('ssl-modules/{module}', [ConfigController::class, 'updateSslModuleSettings'])->name('updateSslModuleSettings');
            Route::post('ssl-modules/{module}/test', [ConfigController::class, 'testSslConnection'])->name('testSslConnection');
        });
    });

    // =============================================
    // Quotes
    // =============================================
    Route::middleware('admin.permission:manage_quotes')->group(function () {
        Route::resource('quotes', QuoteController::class)->except(['index', 'show']);
    });

    // Seeing quotes is its own permission; sending, converting and the
    // rest stay behind managing them.
    Route::middleware('admin.permission:list_quotes|manage_quotes')->group(function () {
        Route::get('quotes', [QuoteController::class, 'index'])->name('quotes.index');
        Route::get('quotes/{quote}', [QuoteController::class, 'show'])->name('quotes.show');
    });

    Route::middleware('admin.permission:manage_quotes')->group(function () {
        Route::post('quotes/{quote}/send', [QuoteController::class, 'send'])->name('quotes.send');
        Route::post('quotes/{quote}/convert', [QuoteController::class, 'convertToInvoice'])->name('quotes.convert');
        Route::post('quotes/{quote}/accept', [QuoteController::class, 'accept'])->name('quotes.accept');
        Route::post('quotes/{quote}/decline', [QuoteController::class, 'decline'])->name('quotes.decline');
    });

    // Projects
    // Writing is registered first: projects/create would otherwise be read as
    // projects/{project} and looked up as a project called "create".
    Route::middleware('admin.permission:manage_projects')->group(function () {
        Route::resource('projects', ProjectController::class)->except(['index', 'show']);
        Route::post('projects/{project}/tasks', [ProjectController::class, 'addTask'])->name('projects.tasks.store');
        Route::put('projects/{project}/tasks/{task}', [ProjectController::class, 'updateTask'])->name('projects.tasks.update');
        Route::delete('projects/{project}/tasks/{task}', [ProjectController::class, 'deleteTask'])->name('projects.tasks.destroy');
        Route::post('projects/{project}/messages', [ProjectController::class, 'addMessage'])->name('projects.messages.store');
    });

    // Seeing the list is its own permission; changing anything is not.
    Route::middleware('admin.permission:list_projects|manage_projects')->group(function () {
        Route::resource('projects', ProjectController::class)->only(['index', 'show']);
    });

    // Logs — view_activity_log
    Route::middleware('admin.permission:view_activity_log')->group(function () {
        Route::get('logs', [LogController::class, 'index'])->name('logs.index');
        Route::get('logs/gateway', [LogController::class, 'gateway'])->name('logs.gateway');
        Route::get('logs/module', [LogController::class, 'module'])->name('logs.module');
        Route::get('logs/email', [LogController::class, 'email'])->name('logs.email');
    });

    // API Documentation — no permission required
    Route::get('api-docs', [ConfigController::class, 'apiDocs'])->name('api-docs');

    // Affiliates
    Route::middleware('admin.permission:manage_affiliates')->group(function () {
        Route::get('affiliates', [AffiliateController::class, 'index'])->name('affiliates.index');
        Route::get('affiliates/{affiliate}', [AffiliateController::class, 'show'])->name('affiliates.show');
        Route::put('affiliates/{affiliate}', [AffiliateController::class, 'update'])->name('affiliates.update');
        Route::post('affiliates/{affiliate}/payout', [AffiliateController::class, 'payout'])->name('affiliates.payout');
    });

    // WHOIS Lookup — no permission required
    Route::get('whois', [WhoisController::class, 'index'])->name('whois.index');
    Route::post('whois', [WhoisController::class, 'lookup'])->name('whois.lookup');

    // Bulk Actions — need respective permissions
    Route::middleware('admin.permission:manage_email_templates')->group(function () {
        Route::get('bulk/mass-email', [BulkActionController::class, 'massEmailForm'])->name('bulk.mass-email');
        Route::post('bulk/mass-email', [BulkActionController::class, 'massEmail'])->name('bulk.mass-email.send');
    });
    Route::middleware('admin.permission:create_invoices')->group(function () {
        Route::post('bulk/invoice', [BulkActionController::class, 'bulkInvoice'])->name('bulk.invoice');
    });
    Route::middleware('admin.permission:manage_services')->group(function () {
        Route::post('bulk/service-update', [BulkActionController::class, 'bulkServiceUpdate'])->name('bulk.service-update');
    });

    // Calendar — no permission required
    Route::get('calendar', [CalendarController::class, 'index'])->name('calendar');
    Route::post('calendar', [CalendarController::class, 'store'])->name('calendar.store');
    Route::put('calendar/{event}', [CalendarController::class, 'update'])->name('calendar.update');
    Route::delete('calendar/{event}', [CalendarController::class, 'destroy'])->name('calendar.destroy');
    Route::get('calendar/events', [CalendarController::class, 'apiEvents'])->name('calendar.events');

    // Languages & Translations — manage_settings
    Route::middleware('admin.permission:manage_settings')->prefix('config/languages')->name('config.languages.')->group(function () {
        Route::get('/', [TranslationController::class, 'index'])->name('index');
        Route::post('/toggle/{language}', [TranslationController::class, 'toggle'])->name('toggle');
        Route::post('/set-default', [TranslationController::class, 'setDefault'])->name('set-default');
        Route::get('/translations/{locale}', [TranslationController::class, 'translations'])->name('translations');
        Route::post('/translations/{locale}/save', [TranslationController::class, 'saveTranslation'])->name('save');
        Route::post('/translations/{locale}/bulk-save', [TranslationController::class, 'bulkSave'])->name('bulk-save');
        Route::post('/ai-translate/{locale}', [TranslationController::class, 'aiTranslate'])->name('ai-translate');
        Route::get('/export/{locale}', [TranslationController::class, 'export'])->name('export');
        Route::post('/import/{locale}', [TranslationController::class, 'import'])->name('import');
        Route::post('/cache-clear', [TranslationController::class, 'clearCache'])->name('cache-clear');
    });

    // SSL Orders
    Route::middleware('admin.permission:list_services')->group(function () {
        Route::get('ssl-orders', [SslOrderController::class, 'index'])->name('ssl.index');
    });
    Route::middleware('admin.permission:view_services')->group(function () {
        Route::get('ssl-orders/{sslOrder}', [SslOrderController::class, 'show'])->name('ssl.show');
        Route::get('ssl-orders/{sslOrder}/download', [SslOrderController::class, 'downloadCert'])->name('ssl.download');
    });
    Route::middleware('admin.permission:manage_services')->group(function () {
        Route::post('ssl-orders/{sslOrder}/action', [SslOrderController::class, 'moduleAction'])->name('ssl.action');
    });
});
