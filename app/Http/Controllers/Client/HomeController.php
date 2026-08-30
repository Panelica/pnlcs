<?php

namespace App\Http\Controllers\Client;

use App\Enums\DomainStatus;
use App\Enums\ServiceStatus;
use App\Http\Controllers\Controller;
use App\Models\Client;
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
            'serviceCount' => Service::whereIn('client_id', $clientIds)->where('status', ServiceStatus::Active->value)->count(),
            'domainCount' => Domain::whereIn('client_id', $clientIds)->where('status', DomainStatus::Active->value)->count(),
            'unpaidInvoices' => Invoice::whereIn('client_id', $clientIds)->outstanding()->count(),
            'openTickets' => Ticket::whereIn('client_id', $clientIds)->stillOpen()->count(),
            'credit' => (float) Client::whereIn('id', $clientIds)->sum('credit'),
            'recentInvoices' => Invoice::whereIn('client_id', $clientIds)->orderBy('id', 'desc')->limit(5)->get(),
            'recentTickets' => Ticket::whereIn('client_id', $clientIds)->orderBy('id', 'desc')->limit(5)->get(),
            'activeServices' => Service::whereIn('client_id', $clientIds)->where('status', ServiceStatus::Active->value)->with('product')->limit(5)->get(),
        ];

        return view('client.home', $data);
    }
}
