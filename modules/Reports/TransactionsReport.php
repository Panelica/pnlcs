<?php

namespace Modules\Reports;

use App\Reports\AbstractReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransactionsReport extends AbstractReport
{
    public function getTitle(): string { return 'Transactions List'; }
    public function getDescription(): string { return 'All payment transactions with details'; }
    public function getCategory(): string { return 'Financial'; }

    public function generate(Request $request): array
    {
        [$from, $to] = $this->getDateRange($request);
        $rows = DB::table("transactions")
            ->leftJoin("clients", "clients.id", "=", "transactions.client_id")
            ->selectRaw("transactions.id, transactions.date, CONCAT(clients.first_name, ' ', clients.last_name) as client, transactions.gateway, transactions.transaction_id, transactions.amount_in, transactions.amount_out, transactions.fees")
            ->whereBetween("transactions.date", [$from, $to])
            ->orderBy("transactions.date", "desc")->limit(500)->get();
        return ["columns" => ["ID", "Date", "Client", "Gateway", "Transaction ID", "In", "Out", "Fees"], "rows" => $rows->toArray()];
    }
}
