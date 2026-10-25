<?php

namespace App\Http\Controllers\Client;

use App\Enums\DomainStatus;
use App\Enums\InvoiceStatus;
use App\Enums\ServiceStatus;
use App\Http\Controllers\Concerns\ResolvesClient;
use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Models\Invoice;
use App\Models\Service;
use App\Models\Ticket;

class HomeController extends Controller
{
    use ResolvesClient;

    public function index()
    {
        $user = auth()->user();
        $clientIds = $user->clients()->pluck('clients.id');

        $data = [
            'serviceCount' => Service::whereIn('client_id', $clientIds)->where('status', ServiceStatus::Active->value)->count(),
            'domainCount' => Domain::whereIn('client_id', $clientIds)->where('status', DomainStatus::Active->value)->count(),
            'unpaidInvoices' => Invoice::whereIn('client_id', $clientIds)->outstanding()->count(),
            'openTickets' => Ticket::whereIn('client_id', $clientIds)->stillOpen()->count(),
            // Not a draft: it has not been issued, and its page answers 404.
            'recentInvoices' => Invoice::whereIn('client_id', $clientIds)->excludeSettledProformas()
                ->where('status', '!=', InvoiceStatus::Draft->value)->orderBy('id', 'desc')->limit(5)->get(),
            'recentTickets' => Ticket::whereIn('client_id', $clientIds)->orderBy('id', 'desc')->limit(5)->get(),
            'activeServices' => Service::whereIn('client_id', $clientIds)->where('status', ServiceStatus::Active->value)->with('product')->limit(5)->get(),
            // The balance belongs to an account (clients.credit), not to the
            // signed-in user: the view read auth()->user()->credit, which is
            // not a column, so it always showed nothing.
            'accountCredit' => (float) ($this->currentClient()?->credit ?? 0),
        ];

        return view('client.home', $data);
    }
}
