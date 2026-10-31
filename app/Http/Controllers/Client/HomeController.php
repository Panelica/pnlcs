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
use App\Support\ClientPermissions;

class HomeController extends Controller
{
    use ResolvesClient;

    public function index()
    {
        $user = auth()->user();

        // Each part of the page shows the accounts whose part the login was
        // given (ClientPermissions): a colleague invited for tickets sees the
        // tickets, not the invoices and services the pages behind them refuse.
        $accounts = $user->clients()->get();
        $ids = fn (string $permission) => $accounts
            ->filter(fn ($client) => ClientPermissions::allows($user, $client, $permission))
            ->pluck('id');
        [$services, $domains, $invoices, $tickets] = [$ids('products'), $ids('domains'), $ids('invoices'), $ids('tickets')];
        $current = $this->currentClient();

        $data = [
            'serviceCount' => Service::whereIn('client_id', $services)->where('status', ServiceStatus::Active->value)->count(),
            'domainCount' => Domain::whereIn('client_id', $domains)->where('status', DomainStatus::Active->value)->count(),
            'unpaidInvoices' => Invoice::whereIn('client_id', $invoices)->outstanding()->count(),
            'openTickets' => Ticket::whereIn('client_id', $tickets)->stillOpen()->count(),
            // Not a draft: it has not been issued, and its page answers 404.
            'recentInvoices' => Invoice::whereIn('client_id', $invoices)->excludeSettledProformas()
                ->where('status', '!=', InvoiceStatus::Draft->value)->orderBy('id', 'desc')->limit(5)->get(),
            'recentTickets' => Ticket::whereIn('client_id', $tickets)->orderBy('id', 'desc')->limit(5)->get(),
            'activeServices' => Service::whereIn('client_id', $services)->where('status', ServiceStatus::Active->value)->with('product')->limit(5)->get(),
            // The balance belongs to an account (clients.credit), not to the
            // signed-in user: the view read auth()->user()->credit, which is
            // not a column, so it always showed nothing. It is the account's
            // money, shown with its invoices.
            'accountCredit' => $current && $invoices->contains($current->id) ? (float) $current->credit : 0.0,
        ];

        return view('client.home', $data);
    }
}
