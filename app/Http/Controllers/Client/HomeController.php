<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Models\Invoice;
use App\Models\Service;
use App\Models\Ticket;

class HomeController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $clientIds = $user->clients()->pluck('clients.id');

        $data = [
            'serviceCount'   => Service::whereIn('client_id', $clientIds)->where('status', 'Active')->count(),
            'domainCount'    => Domain::whereIn('client_id', $clientIds)->where('status', 'Active')->count(),
            'unpaidInvoices' => Invoice::whereIn('client_id', $clientIds)->where('status', 'Unpaid')->count(),
            'openTickets'    => Ticket::whereIn('client_id', $clientIds)->whereIn('status', ['Open', 'Customer-Reply'])->count(),
            'recentInvoices' => Invoice::whereIn('client_id', $clientIds)->orderBy('id', 'desc')->limit(5)->get(),
            'recentTickets'  => Ticket::whereIn('client_id', $clientIds)->orderBy('id', 'desc')->limit(5)->get(),
            'activeServices' => Service::whereIn('client_id', $clientIds)->where('status', 'Active')->with('product')->limit(5)->get(),
        ];

        return view('client.home', $data);
    }
}
