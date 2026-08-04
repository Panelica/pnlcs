<?php

namespace Modules\Reports;

use App\Reports\AbstractReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TopClientsReport extends AbstractReport
{
    public function getTitle(): string
    {
        return 'Top 10 Clients by Income';
    }

    public function getDescription(): string
    {
        return 'Highest revenue generating clients';
    }

    public function getCategory(): string
    {
        return 'Client';
    }

    public function generate(Request $request): array
    {
        // Read the ledger, not invoice status: a partial payment or a refund
        // takes an invoice off 'paid', which used to drop the customer out of
        // this list even though their money is still in the bank.
        $rows = DB::table('transactions')
            ->join('clients', 'clients.id', '=', 'transactions.client_id')
            ->whereNull('clients.deleted_at')
            ->whereNotIn('transactions.gateway', \App\Models\Transaction::NON_REVENUE_GATEWAYS)
            ->selectRaw("clients.id, CONCAT(clients.first_name, ' ', clients.last_name) as client, clients.email, clients.company_name, COUNT(DISTINCT transactions.invoice_id) as invoices, SUM(transactions.amount_in - transactions.amount_out) as revenue")
            ->groupBy('clients.id', 'clients.first_name', 'clients.last_name', 'clients.email', 'clients.company_name')
            ->havingRaw('SUM(transactions.amount_in - transactions.amount_out) > 0')
            ->orderBy('revenue', 'desc')->limit(10)->get();

        return ['columns' => ['ID', 'Client', 'Email', 'Company', 'Invoices', 'Revenue'], 'rows' => $rows->toArray()];
    }

    public function hasDateFilter(): bool
    {
        return false;
    }
}
