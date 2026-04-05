@extends('admin.layouts.app')
@section('title', 'Transactions')
@section('content')

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;">
    <h1>Transactions</h1>
</div>
<div class="card">
    @if(($transactions ?? collect())->isEmpty())
    <div class="card-body" style="text-align:center;padding:40px;color:#999;">No transactions found.</div>
    @else
    <table class="data-table">
        <thead><tr><th>{{ __('common.table.date') }}</th><th>{{ __('common.table.client') }}</th><th>Invoice</th><th>Gateway</th><th>Transaction ID</th><th>Amount In</th><th>Amount Out</th><th>Fee</th></tr></thead>
        <tbody>
        @foreach($transactions as $tx)
        <tr>
            <td style="font-size:12px;white-space:nowrap;">{{ $tx->date?->format('d M Y') ?? $tx->created_at->format('d M Y') }}</td>
            <td><a href="{{ $tx->client ? route("admin.clients.show", $tx->client) : "#" }}" style="color:#337ab7;">{{ $tx->client?->full_name ?? "Deleted Client" ?? 'N/A' }}</a></td>
            <td>@if($tx->invoice_id)<a href="{{ route('admin.invoices.show', $tx->invoice_id) }}" style="color:#337ab7;">#{{ $tx->invoice_id }}</a>@else &mdash; @endif</td>
            <td style="text-transform:capitalize;">{{ $tx->gateway }}</td>
            <td style="font-family:monospace;font-size:11px;">{{ Str::limit($tx->transaction_id ?? '', 20) }}</td>
            <td style="color:#5cb85c;font-weight:600;">{{ $tx->amount_in > 0 ? '+$'.number_format($tx->amount_in, 2) : '' }}</td>
            <td style="color:#d9534f;">{{ $tx->amount_out > 0 ? '-$'.number_format($tx->amount_out, 2) : '' }}</td>
            <td style="font-size:12px;color:#777;">{{ $tx->fees > 0 ? '$'.number_format($tx->fees, 2) : '' }}</td>
        </tr>
        @endforeach
        </tbody>
    </table>
    @if(method_exists($transactions, 'links'))
    <div style="padding:10px 15px;">{{ $transactions->links() }}</div>
    @endif
    @endif
</div>
@endsection
