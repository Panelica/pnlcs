<?php

use App\Http\Controllers\Api\ClientApiController;
use App\Http\Controllers\Api\DomainApiController;
use App\Http\Controllers\Api\InvoiceApiController;
use App\Http\Controllers\Api\OrderApiController;
use App\Http\Controllers\Api\ServiceApiController;
use App\Http\Controllers\Api\SystemApiController;
use App\Http\Controllers\Api\TicketApiController;
use Illuminate\Support\Facades\Route;

Route::get('/health', [SystemApiController::class, 'getHealthStatus']);

Route::prefix('v1')->group(function () {
    // ===== SYSTEM (26 endpoints) =====
    Route::get('getstats', [SystemApiController::class, 'getStats']);
    Route::get('gethealthstatus', [SystemApiController::class, 'getHealthStatus']);
    Route::get('pnlcsdetails', [SystemApiController::class, 'pnlcsDetails']);
    Route::get('whmcsdetails', [SystemApiController::class, 'pnlcsDetails']); // WHMCS compat alias
    Route::get('getactivitylog', [SystemApiController::class, 'getActivityLog']);
    Route::post('logactivity', [SystemApiController::class, 'logActivity']);
    Route::get('getadminusers', [SystemApiController::class, 'getAdminUsers']);
    Route::get('getadmindetails', [SystemApiController::class, 'getAdminDetails']);
    Route::get('getstaffonline', [SystemApiController::class, 'getStaffOnline']);
    Route::get('getconfigurationvalue', [SystemApiController::class, 'getConfigurationValue']);
    Route::post('setconfigurationvalue', [SystemApiController::class, 'setConfigurationValue']);
    Route::get('getannouncements', [SystemApiController::class, 'getAnnouncements']);
    Route::post('addannouncement', [SystemApiController::class, 'addAnnouncement']);
    Route::post('updateannouncement', [SystemApiController::class, 'updateAnnouncement']);
    Route::post('deleteannouncement', [SystemApiController::class, 'deleteAnnouncement']);
    Route::get('getemailtemplates', [SystemApiController::class, 'getEmailTemplates']);
    Route::get('getemails', [SystemApiController::class, 'getEmails']);
    Route::get('getservers', [SystemApiController::class, 'getServers']);
    Route::get('getregistrars', [SystemApiController::class, 'getRegistrars']);
    Route::get('getproducts', [SystemApiController::class, 'getProducts']);
    Route::get('getpromotions', [SystemApiController::class, 'getPromotions']);
    Route::get('gettodoitems', [SystemApiController::class, 'getTodoItems']);
    Route::post('updatetodoitem', [SystemApiController::class, 'updateTodoItem']);
    Route::get('getpaymentmethods', [SystemApiController::class, 'getPaymentMethods']);
    Route::get('getorderstatuses', [SystemApiController::class, 'getOrderStatuses']);
    Route::post('addbannedip', [SystemApiController::class, 'addBannedIp']);
    Route::post('validatelogin', [SystemApiController::class, 'validateLogin']);
    Route::post('sendemail', [SystemApiController::class, 'sendEmail']);
    Route::post('sendadminemail', [SystemApiController::class, 'sendEmail']);
    Route::post('resetpassword', [SystemApiController::class, 'resetPassword']);
    Route::get('getautomationlog', [SystemApiController::class, 'getActivityLog']); // alias
    Route::post('activatemodule', [SystemApiController::class, 'activateModule']);
    Route::post('deactivatemodule', [SystemApiController::class, 'deactivateModule']);
    Route::get('getmoduleconfigurationparameters', [SystemApiController::class, 'getModuleConfigParams']);
    Route::post('updatemoduleconfiguration', [SystemApiController::class, 'updateModuleConfig']);
    Route::get('getmodulequeue', [SystemApiController::class, 'getModuleQueue']);
    Route::get('getpermissionslist', [SystemApiController::class, 'getPermissionsList']);
    Route::post('triggernotificationevent', [SystemApiController::class, 'triggerNotification']);
    Route::post('encryptpassword', [SystemApiController::class, 'encryptPassword']);
    Route::post('decryptpassword', [SystemApiController::class, 'decryptPassword']);
    Route::get('gettodoitemstatuses', [SystemApiController::class, 'getTodoItemStatuses']);
    Route::post('updateadminnotes', [SystemApiController::class, 'updateAdminNotes']);

    // ===== CLIENTS (20 endpoints) =====
    Route::get('getclients', [ClientApiController::class, 'getClients']);
    Route::get('getclientsdetails', [ClientApiController::class, 'getClientsDetails']);
    Route::post('addclient', [ClientApiController::class, 'addClient']);
    Route::post('updateclient', [ClientApiController::class, 'updateClient']);
    Route::post('deleteclient', [ClientApiController::class, 'deleteClient']);
    Route::post('closeclient', [ClientApiController::class, 'closeClient']);
    Route::post('addclientnote', [ClientApiController::class, 'addClientNote']);
    Route::get('getcontacts', [ClientApiController::class, 'getContacts']);
    Route::post('addcontact', [ClientApiController::class, 'addContact']);
    Route::post('updatecontact', [ClientApiController::class, 'updateContact']);
    Route::post('deletecontact', [ClientApiController::class, 'deleteContact']);
    Route::get('getclientgroups', [ClientApiController::class, 'getClientGroups']);
    Route::get('getcredits', [ClientApiController::class, 'getCredits']);
    Route::post('addcredit', [ClientApiController::class, 'addCredit']);
    Route::post('applycredit', [ClientApiController::class, 'addCredit']); // alias
    Route::get('getusers', [ClientApiController::class, 'getUsers']);
    Route::post('adduser', [ClientApiController::class, 'addUser']);
    Route::post('updateuser', [ClientApiController::class, 'updateUser']);
    Route::post('deleteuserclient', [ClientApiController::class, 'deleteUserClient']);
    Route::post('createclientinvite', [ClientApiController::class, 'createClientInvite']);
    Route::get('getuserpermissions', [ClientApiController::class, 'getUserPermissions']);
    Route::post('updateuserpermissions', [ClientApiController::class, 'updateUserPermissions']);
    Route::get('getclientpassword', [ClientApiController::class, 'getClientPassword']);
    Route::post('createssotoken', [ClientApiController::class, 'createSSOToken']);

    // ===== INVOICES & BILLING (16 endpoints) =====
    Route::get('getinvoices', [InvoiceApiController::class, 'getInvoices']);
    Route::get('getinvoice', [InvoiceApiController::class, 'getInvoice']);
    Route::post('createinvoice', [InvoiceApiController::class, 'createInvoice']);
    Route::post('updateinvoice', [InvoiceApiController::class, 'updateInvoice']);
    Route::post('addinvoicepayment', [InvoiceApiController::class, 'addInvoicePayment']);
    Route::post('addtransaction', [InvoiceApiController::class, 'addTransaction']);
    Route::get('gettransactions', [InvoiceApiController::class, 'getTransactions']);
    Route::post('updatetransaction', [InvoiceApiController::class, 'updateTransaction']);
    Route::get('getcurrencies', [InvoiceApiController::class, 'getCurrencies']);
    Route::post('geninvoices', [InvoiceApiController::class, 'genInvoices']);
    Route::post('capturepayment', [InvoiceApiController::class, 'capturePayment']);
    Route::post('addbillableitem', [InvoiceApiController::class, 'addBillableItem']);
    Route::post('addpaymethod', [InvoiceApiController::class, 'addPayMethod']);
    Route::post('updatepaymethod', [InvoiceApiController::class, 'updatePayMethod']);
    Route::post('deletepaymethod', [InvoiceApiController::class, 'deletePayMethod']);
    Route::get('getpaymethods', [InvoiceApiController::class, 'getPayMethods']);

    // ===== ORDERS (8 endpoints) =====
    Route::get('getorders', [OrderApiController::class, 'getOrders']);
    Route::post('addorder', [OrderApiController::class, 'addOrder']);
    Route::post('acceptorder', [OrderApiController::class, 'acceptOrder']);
    Route::post('cancelorder', [OrderApiController::class, 'cancelOrder']);
    Route::post('pendingorder', [OrderApiController::class, 'pendingOrder']);
    Route::post('fraudorder', [OrderApiController::class, 'fraudOrder']);
    Route::post('deleteorder', [OrderApiController::class, 'deleteOrder']);
    Route::post('orderfraudcheck', [OrderApiController::class, 'orderFraudCheck']);

    // ===== SERVICES (12 endpoints) =====
    Route::get('getclientsproducts', [ServiceApiController::class, 'getClientsProducts']);
    Route::post('updateclientproduct', [ServiceApiController::class, 'updateClientProduct']);
    Route::get('getclientsaddons', [ServiceApiController::class, 'getClientsAddons']);
    Route::post('updateclientaddon', [ServiceApiController::class, 'updateClientAddon']);
    Route::post('modulecreate', [ServiceApiController::class, 'moduleCreate']);
    Route::post('modulesuspend', [ServiceApiController::class, 'moduleSuspend']);
    Route::post('moduleunsuspend', [ServiceApiController::class, 'moduleUnsuspend']);
    Route::post('moduleterminate', [ServiceApiController::class, 'moduleTerminate']);
    Route::post('modulechangepw', [ServiceApiController::class, 'moduleChangePw']);
    Route::post('modulechangepackage', [ServiceApiController::class, 'moduleChangePackage']);
    Route::post('modulecustom', [ServiceApiController::class, 'moduleCustom']);
    Route::post('upgradeproduct', [ServiceApiController::class, 'upgradeProduct']);
    Route::post('addcancelrequest', [ServiceApiController::class, 'addCancelRequest']);
    Route::get('getcancelledpackages', [ServiceApiController::class, 'getCancelledPackages']);
    Route::post('addproduct', [ServiceApiController::class, 'addProduct']);

    // ===== DOMAINS (14 endpoints) =====
    Route::get('getclientsdomains', [DomainApiController::class, 'getClientsDomains']);
    Route::get('gettldpricing', [DomainApiController::class, 'getTldPricing']);
    Route::post('domainregister', [DomainApiController::class, 'domainRegister']);
    Route::post('domaintransfer', [DomainApiController::class, 'domainTransfer']);
    Route::post('domainrenew', [DomainApiController::class, 'domainRenew']);
    Route::get('domaingetnameservers', [DomainApiController::class, 'domainGetNameservers']);
    Route::post('domainupdatenameservers', [DomainApiController::class, 'domainUpdateNameservers']);
    Route::get('domaingetlockingstatus', [DomainApiController::class, 'domainGetLockingStatus']);
    Route::post('domainupdatelockingstatus', [DomainApiController::class, 'domainUpdateLockingStatus']);
    Route::get('domaingetwhoisinfo', [DomainApiController::class, 'domainGetWhoisInfo']);
    Route::post('domainupdatewhoisinfo', [DomainApiController::class, 'domainUpdateWhoisInfo']);
    Route::get('domainrequestepp', [DomainApiController::class, 'domainRequestEpp']);
    Route::post('domaintoggleidprotect', [DomainApiController::class, 'domainToggleIdProtect']);
    Route::post('domainrelease', [DomainApiController::class, 'domainRelease']);
    Route::get('domainwhois', [DomainApiController::class, 'domainWhois']);
    Route::post('updateclientdomain', [DomainApiController::class, 'updateClientDomain']);
    Route::post('createorupdatetld', [DomainApiController::class, 'createOrUpdateTld']);

    // ===== TICKETS (18 endpoints) =====
    Route::get('gettickets', [TicketApiController::class, 'getTickets']);
    Route::get('getticket', [TicketApiController::class, 'getTicket']);
    Route::post('openticket', [TicketApiController::class, 'openTicket']);
    Route::post('addticketreply', [TicketApiController::class, 'addTicketReply']);
    Route::post('addticketnote', [TicketApiController::class, 'addTicketNote']);
    Route::post('updateticket', [TicketApiController::class, 'updateTicket']);
    Route::post('updateticketreply', [TicketApiController::class, 'updateTicketReply']);
    Route::post('deleteticket', [TicketApiController::class, 'deleteTicket']);
    Route::post('deleteticketnote', [TicketApiController::class, 'deleteTicketNote']);
    Route::post('deleteticketreply', [TicketApiController::class, 'deleteTicketReply']);
    Route::get('getticketcounts', [TicketApiController::class, 'getTicketCounts']);
    Route::get('getticketnotes', [TicketApiController::class, 'getTicketNotes']);
    Route::get('getticketattachment', [TicketApiController::class, 'getTicketAttachment']);
    Route::get('getsupportdepartments', [TicketApiController::class, 'getSupportDepartments']);
    Route::get('getsupportstatuses', [TicketApiController::class, 'getSupportStatuses']);
    Route::get('getticketpredefinedcats', [TicketApiController::class, 'getTicketPredefinedCats']);
    Route::get('getticketpredefinedreplies', [TicketApiController::class, 'getTicketPredefinedReplies']);
    Route::post('mergeticket', [TicketApiController::class, 'mergeTicket']);
    Route::post('blockticketsender', [TicketApiController::class, 'blockTicketSender']);

    // ===== QUOTES (6 endpoints) =====
    Route::get('getquotes', [SystemApiController::class, 'getQuotes']);
    Route::post('createquote', [SystemApiController::class, 'createQuote']);
    Route::post('updatequote', [SystemApiController::class, 'updateQuote']);
    Route::post('deletequote', [SystemApiController::class, 'deleteQuote']);
    Route::post('sendquote', [SystemApiController::class, 'sendQuote']);
    Route::post('acceptquote', [SystemApiController::class, 'acceptQuote']);

    // ===== PROJECTS (7 endpoints) =====
    Route::get('getprojects', [SystemApiController::class, 'getProjects']);
    Route::get('getproject', [SystemApiController::class, 'getProject']);
    Route::post('createproject', [SystemApiController::class, 'createProject']);
    Route::post('updateproject', [SystemApiController::class, 'updateProject']);
    Route::post('addprojectmessage', [SystemApiController::class, 'addProjectMessage']);
    Route::post('addprojecttask', [SystemApiController::class, 'addProjectTask']);
    Route::post('deleteprojecttask', [SystemApiController::class, 'deleteProjectTask']);
    Route::post('updateprojecttask', [SystemApiController::class, 'updateProjectTask']);
    Route::post('starttasktimer', [SystemApiController::class, 'startTaskTimer']);
    Route::post('endtasktimer', [SystemApiController::class, 'endTaskTimer']);

    // ===== AFFILIATES (2 endpoints) =====
    Route::get('getaffiliates', [SystemApiController::class, 'getAffiliates']);
    Route::post('affiliateactivate', [SystemApiController::class, 'affiliateActivate']);

    // ===== OAUTH (4 endpoints) =====
    Route::get('listoauthcredentials', [SystemApiController::class, 'listOAuthCredentials']);
    Route::post('createoauthcredential', [SystemApiController::class, 'createOAuthCredential']);
    Route::post('updateoauthcredential', [SystemApiController::class, 'updateOAuthCredential']);
    Route::post('deleteoauthcredential', [SystemApiController::class, 'deleteOAuthCredential']);
});

// ── SSL API ──────────────────────────────────────────────────
Route::prefix('v1')->group(function () {
    Route::get('/getsslorders', [\App\Http\Controllers\Api\SslApiController::class, 'getSslOrders']);
    Route::get('/getsslorder', [\App\Http\Controllers\Api\SslApiController::class, 'getSslOrder']);
    Route::post('/addsslorder', [\App\Http\Controllers\Api\SslApiController::class, 'addSslOrder']);
    Route::post('/configsslorder', [\App\Http\Controllers\Api\SslApiController::class, 'configSslOrder']);
    Route::post('/cancelsslorder', [\App\Http\Controllers\Api\SslApiController::class, 'cancelSslOrder']);
    Route::post('/reissuesslorder', [\App\Http\Controllers\Api\SslApiController::class, 'reissueSslOrder']);
    Route::post('/resendsslvalidation', [\App\Http\Controllers\Api\SslApiController::class, 'resendSslValidation']);
    Route::get('/getsslapproveremails', [\App\Http\Controllers\Api\SslApiController::class, 'getSslApproverEmails']);
});
