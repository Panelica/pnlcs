<?php

use App\Http\Controllers\Api\ClientApiController;
use App\Http\Controllers\Api\DomainApiController;
use App\Http\Controllers\Api\InvoiceApiController;
use App\Http\Controllers\Api\OrderApiController;
use App\Http\Controllers\Api\ProductApiController;
use App\Http\Controllers\Api\ServiceApiController;
use App\Http\Controllers\Api\SystemApiController;
use App\Http\Controllers\Api\TicketApiController;
use Illuminate\Support\Facades\Route;

Route::get('/health', [SystemApiController::class, 'getHealthStatus']);

Route::prefix('v1')->group(function () {
    // System
    Route::get('getstats', [SystemApiController::class, 'getStats']);
    Route::get('gethealthstatus', [SystemApiController::class, 'getHealthStatus']);
    Route::get('pnlcsdetails', [SystemApiController::class, 'pnlcsDetails']);
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

    // Clients
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

    // Invoices & Billing
    Route::get('getinvoices', [InvoiceApiController::class, 'getInvoices']);
    Route::get('getinvoice', [InvoiceApiController::class, 'getInvoice']);
    Route::post('createinvoice', [InvoiceApiController::class, 'createInvoice']);
    Route::post('updateinvoice', [InvoiceApiController::class, 'updateInvoice']);
    Route::post('addinvoicepayment', [InvoiceApiController::class, 'addInvoicePayment']);
    Route::post('addtransaction', [InvoiceApiController::class, 'addTransaction']);
    Route::get('gettransactions', [InvoiceApiController::class, 'getTransactions']);
    Route::get('getcurrencies', [InvoiceApiController::class, 'getCurrencies']);

    // Orders
    Route::get('getorders', [OrderApiController::class, 'getOrders']);
    Route::post('addorder', [OrderApiController::class, 'addOrder']);
    Route::post('acceptorder', [OrderApiController::class, 'acceptOrder']);
    Route::post('cancelorder', [OrderApiController::class, 'cancelOrder']);

    // Services
    Route::get('getclientsproducts', [ServiceApiController::class, 'getClientsProducts']);
    Route::post('updateclientproduct', [ServiceApiController::class, 'updateClientProduct']);

    // Domains
    Route::get('getclientsdomains', [DomainApiController::class, 'getClientsDomains']);
    Route::get('gettldpricing', [DomainApiController::class, 'getTldPricing']);

    // Tickets
    Route::get('gettickets', [TicketApiController::class, 'getTickets']);
    Route::get('getticket', [TicketApiController::class, 'getTicket']);
    Route::post('openticket', [TicketApiController::class, 'openTicket']);
    Route::post('addticketreply', [TicketApiController::class, 'addTicketReply']);
    Route::post('addticketnote', [TicketApiController::class, 'addTicketNote']);
    Route::post('updateticket', [TicketApiController::class, 'updateTicket']);
    Route::post('deleteticket', [TicketApiController::class, 'deleteTicket']);
    Route::get('getticketcounts', [TicketApiController::class, 'getTicketCounts']);
    Route::get('getsupportdepartments', [TicketApiController::class, 'getSupportDepartments']);
    Route::get('getsupportstatuses', [TicketApiController::class, 'getSupportStatuses']);
    Route::get('getticketpredefinedcats', [TicketApiController::class, 'getTicketPredefinedCats']);
    Route::get('getticketpredefinedreplies', [TicketApiController::class, 'getTicketPredefinedReplies']);
    Route::post('mergeticket', [TicketApiController::class, 'mergeTicket']);
});
