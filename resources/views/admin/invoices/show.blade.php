@extends('admin.layouts.app')
@section('title', 'Invoice #' . $invoice->invoice_num)
@section('content')

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;">
    <h1>Invoice #{{ $invoice->invoice_num }} <span class="badge-{{ strtolower($invoice->status) }}" style="font-size:14px;vertical-align:middle;">{{ ucfirst($invoice->status) }}</span></h1>
    <div style="display:flex;gap:6px;align-items:center;">
        @if(in_array($invoice->status, ['Unpaid', 'Overdue']))
        <button type="button" class="btn btn-success btn-sm" onclick="document.getElementById('mark-paid-form').style.display=document.getElementById('mark-paid-form').style.display==='none'?'block':'none'">Mark Paid</button>
        @endif
        @if($invoice->status !== 'Paid' && $invoice->status !== 'Cancelled')
        <form method="POST" action="{{ route('admin.invoices.cancel', $invoice) }}" style="display:inline;" onsubmit="return confirm('Cancel this invoice?')">
            @csrf
            <button type="submit" class="btn btn-danger btn-sm">Cancel</button>
        </form>
        @endif
        <a href="{{ route('admin.invoices.index') }}" class="btn btn-default btn-sm">&larr; Back</a>
    </div>
</div>

@if(session('success'))
<div style="padding:10px 15px;background:#dff0d8;border:1px solid #d6e9c6;border-radius:4px;color:#3c763d;margin-bottom:15px;font-size:13px;">{{ session('success') }}</div>
@endif
@if(session('error'))
<div style="padding:10px 15px;background:#f2dede;border:1px solid #ebccd1;border-radius:4px;color:#a94442;margin-bottom:15px;font-size:13px;">{{ session('error') }}</div>
@endif

@if(in_array($invoice->status, ['Unpaid', 'Overdue']))
<div id="mark-paid-form" style="display:none;margin-bottom:15px;">
    <div class="card">
        <div class="card-header"><strong>Mark Invoice as Paid</strong></div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.invoices.mark-paid', $invoice) }}">
                @csrf
                <div style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;">
                    <div class="form-group" style="margin:0;flex:1;min-width:160px;">
                        <label class="form-label">Gateway</label>
                        <select name="gateway" class="form-control">
                            <option value="manual">Manual</option>
                            <option value="banktransfer">Bank Transfer</option>
                            <option value="paypal">PayPal</option>
                            <option value="stripe">Stripe</option>
                            <option value="credit">Credit</option>
                        </select>
                    </div>
                    <div class="form-group" style="margin:0;flex:1;min-width:160px;">
                        <label class="form-label">Transaction ID (optional)</label>
                        <input type="text" name="transaction_id" class="form-control" placeholder="e.g. ch_abc123">
                    </div>
                    <div class="form-group" style="margin:0;flex:1;min-width:120px;">
                        <label class="form-label">Amount</label>
                        <input type="number" name="amount" step="0.01" value="{{ $invoice->total }}" class="form-control">
                    </div>
                    <button type="submit" class="btn btn-success btn-sm" style="margin-bottom:0;">Confirm Payment</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:15px;">

    {{-- Left (2/3): Line Items + Payment History --}}
    <div style="grid-column:span 2;">

        <div class="card" style="margin-bottom:15px;">
            <div class="card-header"><strong>Line Items</strong></div>
            <table class="data-table">
                <thead><tr>
                    <th>Description</th><th style="width:60px;text-align:center;">Taxed</th><th style="text-align:right;width:100px;">Amount</th>
                </tr></thead>
                <tbody>
                @forelse($invoice->items as $item)
                <tr>
                    <td><span style="font-size:11px;color:#999;text-transform:uppercase;margin-right:4px;">{{ $item->type }}</span>{{ $item->description }}</td>
                    <td style="text-align:center;">{{ $item->taxed ? '&#10003;' : '&mdash;' }}</td>
                    <td style="text-align:right;font-family:monospace;">${{ number_format($item->amount, 2) }}</td>
                </tr>
                @empty
                <tr><td colspan="3" style="text-align:center;color:#999;padding:20px;">No line items</td></tr>
                @endforelse
                </tbody>
                <tfoot>
                    <tr><td colspan="2" style="text-align:right;padding:8px 12px;color:#555;">Subtotal</td><td style="text-align:right;padding:8px 12px;font-weight:600;font-family:monospace;">${{ number_format($invoice->subtotal, 2) }}</td></tr>
                    @if($invoice->tax > 0)
                    <tr><td colspan="2" style="text-align:right;padding:4px 12px;color:#555;">Tax{{ $invoice->tax_rate > 0 ? " (" . $invoice->tax_rate . "%)" : "" }}</td><td style="text-align:right;padding:4px 12px;font-family:monospace;">${{ number_format($invoice->tax, 2) }}</td></tr>
                    @endif
                    @if($invoice->tax2 > 0)
                    <tr><td colspan="2" style="text-align:right;padding:4px 12px;color:#555;">Tax 2{{ $invoice->tax_rate2 > 0 ? " (" . $invoice->tax_rate2 . "%)" : "" }}</td><td style="text-align:right;padding:4px 12px;font-family:monospace;">${{ number_format($invoice->tax2, 2) }}</td></tr>
                    @endif
                    @if($invoice->credit > 0)
                    <tr><td colspan="2" style="text-align:right;padding:4px 12px;color:#5cb85c;">Credit Applied</td><td style="text-align:right;padding:4px 12px;font-family:monospace;color:#5cb85c;">-${{ number_format($invoice->credit, 2) }}</td></tr>
                    @endif
                    <tr style="border-top:2px solid #aaa;background:#f5f5f5;"><td colspan="2" style="text-align:right;padding:8px 12px;font-weight:700;font-size:14px;">Total</td><td style="text-align:right;padding:8px 12px;font-weight:700;font-size:14px;font-family:monospace;">${{ number_format($invoice->total, 2) }}</td></tr>
                </tfoot>
            </table>
        </div>

        @if($invoice->transactions->count() > 0)
        <div class="card" style="margin-bottom:15px;">
            <div class="card-header"><strong>Payment History</strong></div>
            <table class="data-table">
                <thead><tr><th>Date</th><th>Gateway</th><th>Transaction ID</th><th style="text-align:right;">Amount</th></tr></thead>
                <tbody>
                @foreach($invoice->transactions as $tx)
                <tr>
                    <td>{{ $tx->date?->format('d M Y') }}</td>
                    <td style="text-transform:capitalize;">{{ $tx->gateway }}</td>
                    <td style="font-family:monospace;font-size:12px;">{{ $tx->transaction_id ?? '&mdash;' }}</td>
                    <td style="text-align:right;color:#5cb85c;font-weight:600;">+${{ number_format($tx->amount_in, 2) }}</td>
                </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @endif

        @if($invoice->notes)
        <div class="card">
            <div class="card-header"><strong>Notes</strong></div>
            <div class="card-body" style="font-size:13px;white-space:pre-wrap;color:#555;">{{ $invoice->notes }}</div>
        </div>
        @endif
    </div>

    {{-- Right (1/3) --}}
    <div>
        @if($invoice->client)
        <div class="panel" style="margin-bottom:15px;">
            <div class="panel-heading panel-primary">Client Info</div>
            <div class="panel-body">
                <table style="width:100%;font-size:13px;border-collapse:collapse;">
                    <tr><td style="padding:4px 0;color:#777;width:40%;">Name</td><td style="padding:4px 0;"><a href="{{ route('admin.clients.show', $invoice->client) }}" style="color:#337ab7;font-weight:600;">{{ $invoice->client->display_name }}</a></td></tr>
                    <tr><td style="padding:4px 0;color:#777;">Email</td><td style="padding:4px 0;">{{ $invoice->client->email }}</td></tr>
                    @if($invoice->client->address1)
                    <tr><td style="padding:4px 0;color:#777;">Address</td><td style="padding:4px 0;">{{ $invoice->client->address1 }}<br>{{ $invoice->client->city }}, {{ $invoice->client->state }} {{ $invoice->client->postcode }}<br>{{ $invoice->client->country }}</td></tr>
                    @endif
                    @if($invoice->client->tax_id)
                    <tr><td style="padding:4px 0;color:#777;">Tax ID</td><td style="padding:4px 0;font-family:monospace;font-size:12px;">{{ $invoice->client->tax_id }}</td></tr>
                    @endif
                </table>
            </div>
        </div>
        @endif

        <div class="panel" style="margin-bottom:15px;">
            <div class="panel-heading panel-primary">Invoice Details</div>
            <div class="panel-body">
                <table style="width:100%;font-size:13px;border-collapse:collapse;">
                    <tr><td style="padding:4px 0;color:#777;width:45%;">Invoice #</td><td style="padding:4px 0;font-family:monospace;font-weight:600;">{{ $invoice->invoice_num }}</td></tr>
                    <tr><td style="padding:4px 0;color:#777;">Date</td><td style="padding:4px 0;">{{ $invoice->date?->format('d M Y') }}</td></tr>
                    <tr><td style="padding:4px 0;color:#777;">Due Date</td><td style="padding:4px 0;{{ ($invoice->due_date?->isPast() && $invoice->status !== 'Paid') ? 'color:#d9534f;font-weight:600;' : '' }}">{{ $invoice->due_date?->format('d M Y') }}</td></tr>
                    @if($invoice->payment_method)
                    <tr><td style="padding:4px 0;color:#777;">Payment</td><td style="padding:4px 0;text-transform:capitalize;">{{ $invoice->payment_method }}</td></tr>
                    @endif
                    @if($invoice->status === 'Paid' && $invoice->date_paid)
                    <tr><td style="padding:4px 0;color:#777;">Paid On</td><td style="padding:4px 0;color:#5cb85c;font-weight:600;">{{ $invoice->date_paid->format('d M Y H:i') }}</td></tr>
                    @endif
                </table>
            </div>
        </div>

        <div class="panel">
            <div class="panel-heading panel-primary">Actions</div>
            <div class="panel-body" style="display:flex;flex-direction:column;gap:6px;">
                @if($invoice->status === 'Paid')
                <div style="padding:8px;background:#dff0d8;border:1px solid #d6e9c6;border-radius:3px;text-align:center;color:#3c763d;font-size:13px;">&#10003; Paid on {{ $invoice->date_paid?->format('d M Y H:i') }}</div>
                @endif
                @if($invoice->status === 'Cancelled')
                <div style="padding:8px;background:#f5f5f5;border:1px solid #ddd;border-radius:3px;text-align:center;color:#777;font-size:13px;">Invoice Cancelled</div>
                @endif
            </div>
        </div>
    </div>
</div>

@endsection
