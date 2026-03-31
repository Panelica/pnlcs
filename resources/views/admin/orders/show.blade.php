@extends('admin.layouts.app')
@section('title', 'Order #' . $order->order_num)
@section('content')

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;">
    <h1>Order #{{ $order->order_num }} <span class="badge-{{ strtolower($order->status) }}" style="font-size:14px;vertical-align:middle;">{{ ucfirst($order->status) }}</span></h1>
    <a href="{{ route('admin.orders.index') }}" class="btn btn-default btn-sm">&larr; Back</a>
</div>
<div style="display:grid;grid-template-columns:2fr 1fr;gap:15px;">

    {{-- Left column --}}
    <div>
        <div class="panel" style="margin-bottom:15px;">
            <div class="panel-heading panel-primary">Order Details</div>
            <div class="panel-body">
                <table style="width:100%;font-size:13px;border-collapse:collapse;">
                    <tr><td style="padding:5px 0;color:#777;width:35%;">Order Number</td><td style="padding:5px 0;font-family:monospace;font-weight:600;">{{ $order->order_num }}</td></tr>
                    <tr><td style="padding:5px 0;color:#777;">Date</td><td style="padding:5px 0;">{{ $order->date?->format('d M Y') }}</td></tr>
                    <tr><td style="padding:5px 0;color:#777;">Amount</td><td style="padding:5px 0;font-weight:700;font-size:15px;">${{ number_format($order->amount, 2) }}</td></tr>
                    <tr><td style="padding:5px 0;color:#777;">Payment Method</td><td style="padding:5px 0;text-transform:capitalize;">{{ $order->payment_method ?? '&mdash;' }}</td></tr>
                    @if($order->promo_code)
                    <tr><td style="padding:5px 0;color:#777;">Promo Code</td><td style="padding:5px 0;"><span style="background:#fcf8e3;color:#8a6d3b;padding:2px 6px;border-radius:3px;font-family:monospace;font-size:12px;">{{ $order->promo_code }}</span></td></tr>
                    @endif
                    <tr><td style="padding:5px 0;color:#777;">IP Address</td><td style="padding:5px 0;font-family:monospace;font-size:12px;color:#777;">{{ $order->ip_address }}</td></tr>
                </table>
            </div>
        </div>

        @if($order->services->count() > 0)
        <div class="card" style="margin-bottom:15px;">
            <div class="card-header"><strong>Services ({{ $order->services->count() }})</strong></div>
            <table class="data-table">
                <thead><tr><th>Product</th><th>Domain</th><th>Billing</th><th style="text-align:right;">Amount</th><th>Status</th></tr></thead>
                <tbody>
                @foreach($order->services as $svc)
                <tr>
                    <td><a href="{{ route('admin.services.show', $svc) }}" style="color:#337ab7;">{{ $svc->product->name ?? 'N/A' }}</a></td>
                    <td style="font-family:monospace;font-size:12px;">{{ $svc->domain ?? '&mdash;' }}</td>
                    <td>{{ $svc->billing_cycle }}</td>
                    <td style="text-align:right;font-family:monospace;">${{ number_format($svc->amount, 2) }}</td>
                    <td><span class="badge-{{ strtolower($svc->status) }}">{{ ucfirst($svc->status) }}</span></td>
                </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @endif

        @if($order->domains->count() > 0)
        <div class="card" style="margin-bottom:15px;">
            <div class="card-header"><strong>Domains ({{ $order->domains->count() }})</strong></div>
            <table class="data-table">
                <thead><tr><th>Domain</th><th>Type</th><th>Expiry</th><th>Status</th></tr></thead>
                <tbody>
                @foreach($order->domains as $dom)
                <tr>
                    <td style="font-family:monospace;font-weight:600;">{{ $dom->domain }}</td>
                    <td style="text-transform:capitalize;">{{ $dom->type }}</td>
                    <td>{{ $dom->expiry_date?->format('d M Y') ?? '&mdash;' }}</td>
                    <td><span class="badge-{{ strtolower($dom->status) }}">{{ ucfirst($dom->status) }}</span></td>
                </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @endif

        @if($order->status === 'Fraud' && $order->fraud_output)
        <div style="padding:12px 15px;background:#f2dede;border:1px solid #ebccd1;border-radius:4px;margin-bottom:15px;">
            <strong style="color:#a94442;">Fraud Information</strong>
            <p style="margin:6px 0 0;font-size:13px;color:#a94442;">{{ $order->fraud_output }}</p>
        </div>
        @endif

        @if($order->notes)
        <div class="card">
            <div class="card-header"><strong>Notes</strong></div>
            <div class="card-body" style="font-size:13px;white-space:pre-wrap;color:#555;">{{ $order->notes }}</div>
        </div>
        @endif
    </div>

    {{-- Right column --}}
    <div>
        @if($order->client)
        <div class="panel" style="margin-bottom:15px;">
            <div class="panel-heading panel-primary">Client Info</div>
            <div class="panel-body">
                <table style="width:100%;font-size:13px;border-collapse:collapse;">
                    <tr><td style="padding:4px 0;color:#777;width:35%;">Name</td><td style="padding:4px 0;"><a href="{{ route('admin.clients.show', $order->client) }}" style="color:#337ab7;font-weight:600;">{{ $order->client->display_name }}</a></td></tr>
                    <tr><td style="padding:4px 0;color:#777;">Email</td><td style="padding:4px 0;">{{ $order->client->email }}</td></tr>
                    @if($order->client->company_name)
                    <tr><td style="padding:4px 0;color:#777;">Company</td><td style="padding:4px 0;">{{ $order->client->company_name }}</td></tr>
                    @endif
                </table>
            </div>
        </div>
        @endif

        <div class="panel">
            <div class="panel-heading panel-primary">Actions</div>
            <div class="panel-body" style="display:flex;flex-direction:column;gap:6px;">
                @if($order->status === 'Pending')
                <form method="POST" action="{{ route('admin.orders.accept', $order) }}">
                    @csrf
                    <button type="submit" class="btn btn-success btn-sm" style="width:100%;">Accept Order</button>
                </form>
                @endif

                @if(!in_array($order->status, ['Cancelled', 'Fraud']))
                <form method="POST" action="{{ route('admin.orders.cancel', $order) }}" onsubmit="return confirm('Cancel this order and terminate its services?')">
                    @csrf
                    <button type="submit" class="btn btn-warning btn-sm" style="width:100%;">Cancel Order</button>
                </form>
                @endif

                @if($order->status !== 'Fraud')
                <form method="POST" action="{{ route('admin.orders.fraud', $order) }}" onsubmit="return confirm('Mark this order as fraud? Services will be suspended.')">
                    @csrf
                    <button type="submit" class="btn btn-danger btn-sm" style="width:100%;">Mark as Fraud</button>
                </form>
                @endif

                @if(in_array($order->status, ['Cancelled', 'Fraud', 'Pending']))
                <form method="POST" action="{{ route('admin.orders.delete', $order) }}" onsubmit="return confirm('Permanently delete order #{{ $order->order_num }}?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-default btn-sm" style="width:100%;color:#d9534f;">Delete Order</button>
                </form>
                @endif

                @if($order->invoice)
                <a href="{{ route('admin.invoices.show', $order->invoice) }}" class="btn btn-default btn-sm" style="width:100%;text-align:center;">
                    View Invoice #{{ $order->invoice->invoice_num }}
                </a>
                @endif
            </div>
        </div>
    </div>
</div>

@endsection
