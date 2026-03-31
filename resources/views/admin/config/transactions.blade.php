@extends('admin.layouts.app')
@section('title', 'Transactions')
@section('content')

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;">
    <h1>Transactions</h1>
    <button type="button" onclick="document.getElementById('modal-add-tx').style.display='flex'" class="btn btn-primary btn-sm">+ Add Transaction</button>
</div>

@if(session('success'))
<div style="padding:10px 15px;background:#dff0d8;border:1px solid #d6e9c6;border-radius:4px;color:#3c763d;margin-bottom:15px;font-size:13px;">{{ session('success') }}</div>
@endif

<div class="card">
    @if(($transactions ?? collect())->isEmpty())
    <div class="card-body" style="text-align:center;padding:40px;color:#999;">No transactions found.</div>
    @else
    <table class="data-table">
        <thead><tr><th>Date</th><th>Client</th><th>Invoice</th><th>Gateway</th><th>Transaction ID</th><th>Amount In</th><th>Amount Out</th><th>Fee</th></tr></thead>
        <tbody>
        @foreach($transactions as $tx)
        <tr>
            <td style="font-size:12px;white-space:nowrap;">{{ $tx->date?->format('d M Y') ?? $tx->created_at->format('d M Y') }}</td>
            <td><a href="{{ route('admin.clients.show', $tx->client) }}" style="color:#337ab7;">{{ $tx->client->full_name ?? 'N/A' }}</a></td>
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

<div id="modal-add-tx" style="display:none;position:fixed;inset:0;z-index:1050;align-items:center;justify-content:center;">
    <div style="position:fixed;inset:0;background:rgba(0,0,0,0.5);" onclick="document.getElementById('modal-add-tx').style.display='none'"></div>
    <div style="position:relative;background:#fff;border-radius:4px;width:480px;max-width:95%;box-shadow:0 5px 30px rgba(0,0,0,0.3);">
        <div style="padding:15px 20px;border-bottom:1px solid #e5e5e5;display:flex;align-items:center;justify-content:space-between;">
            <h4 style="margin:0;font-size:16px;">Add Transaction</h4>
            <button type="button" onclick="document.getElementById('modal-add-tx').style.display='none'" style="background:none;border:none;font-size:22px;cursor:pointer;color:#777;">&times;</button>
        </div>
        <form method="POST" action="{{ route('admin.config.transactions.store') }}">
            @csrf
            <div style="padding:20px;">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div class="form-group" style="grid-column:span 2;"><label class="form-label">Client</label><select name="client_id" required class="form-control"><option value="">— Select —</option>@foreach($clients ?? [] as $c)<option value="{{ $c->id }}">{{ $c->full_name }} ({{ $c->email }})</option>@endforeach</select></div>
                    <div class="form-group"><label class="form-label">Invoice ID <small style="color:#999;">(optional)</small></label><input type="number" name="invoice_id" class="form-control"></div>
                    <div class="form-group"><label class="form-label">Date</label><input type="date" name="date" value="{{ now()->toDateString() }}" required class="form-control"></div>
                    <div class="form-group"><label class="form-label">Gateway</label><select name="gateway" class="form-control"><option value="manual">Manual</option><option value="banktransfer">Bank Transfer</option><option value="paypal">PayPal</option><option value="stripe">Stripe</option><option value="credit">Credit</option></select></div>
                    <div class="form-group"><label class="form-label">Transaction ID</label><input type="text" name="transaction_id" class="form-control"></div>
                    <div class="form-group"><label class="form-label">Amount In ($)</label><input type="number" name="amount_in" step="0.01" value="0" class="form-control"></div>
                    <div class="form-group"><label class="form-label">Amount Out ($)</label><input type="number" name="amount_out" step="0.01" value="0" class="form-control"></div>
                </div>
            </div>
            <div style="padding:12px 20px;border-top:1px solid #e5e5e5;display:flex;gap:8px;justify-content:flex-end;">
                <button type="button" onclick="document.getElementById('modal-add-tx').style.display='none'" class="btn btn-default btn-sm">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm">Add Transaction</button>
            </div>
        </form>
    </div>
</div>
@endsection
