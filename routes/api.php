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

// Public
Route::get('/health', [SystemApiController::class, 'getHealthStatus']);

// Authenticated API (Sanctum or API key - to be implemented)
Route::prefix('v1')->group(function () {
    // System
    Route::get('getstats', [SystemApiController::class, 'getStats']);
    Route::get('gethealthstatus', [SystemApiController::class, 'getHealthStatus']);
    Route::get('pnlcsdetails', [SystemApiController::class, 'pnlcsDetails']);

    // Clients
    Route::get('getclients', [ClientApiController::class, 'getClients']);
    Route::get('getclientsdetails', [ClientApiController::class, 'getClientsDetails']);
    Route::post('addclient', [ClientApiController::class, 'addClient']);
    Route::post('updateclient', [ClientApiController::class, 'updateClient']);
    Route::post('deleteclient', [ClientApiController::class, 'deleteClient']);

    // Invoices
    Route::get('getinvoices', [InvoiceApiController::class, 'getInvoices']);
    Route::get('getinvoice', [InvoiceApiController::class, 'getInvoice']);
    Route::post('createinvoice', [InvoiceApiController::class, 'createInvoice']);

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

    // Products
    Route::get('getproducts', [ProductApiController::class, 'getProducts']);

    // Tickets
    Route::get('gettickets', [TicketApiController::class, 'getTickets']);
    Route::get('getticket', [TicketApiController::class, 'getTicket']);
    Route::post('openticket', [TicketApiController::class, 'openTicket']);
    Route::post('addticketreply', [TicketApiController::class, 'addTicketReply']);
});
