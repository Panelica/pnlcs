@extends('admin.layouts.app')
@section('title', __('admin.orders.order_hash') . $order->order_num)
@section('content')

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;">
    <h1>Order #{{ $order->order_num }} <span class="badge-{{ strtolower($order->status) }}" style="font-size:14px;vertical-align:middle;">{{ ucfirst($order->status) }}</span></h1>
    <a href="{{ route('admin.orders.index') }}" class="btn btn-default btn-sm">&larr; {{ __('admin.orders.back') }}</a>
</div>
<div style="display:grid;grid-template-columns:2fr 1fr;gap:15px;">

    {{-- Left column --}}
    <div>
        <div class="panel" style="margin-bottom:15px;">
            <div class="panel-heading panel-primary">{{ __('admin.orders.order_details') }}</div>
            <div class="panel-body">
                <table style="width:100%;font-size:13px;border-collapse:collapse;">
                    <tr><td style="padding:5px 0;color:#777;width:35%;">{{ __('admin.orders.order_number') }}</td><td style="padding:5px 0;font-family:monospace;font-weight:600;">{{ $order->order_num }}</td></tr>
                    <tr><td style="padding:5px 0;color:#777;">{{ __('admin.orders.date') }}</td><td style="padding:5px 0;">{{ $order->date?->format(date_fmt()) }}</td></tr>
                    <tr><td style="padding:5px 0;color:#777;">{{ __('admin.orders.amount') }}</td><td style="padding:5px 0;font-weight:700;font-size:15px;">${{ number_format($order->amount, 2) }}</td></tr>
                    <tr><td style="padding:5px 0;color:#777;">{{ __('admin.orders.payment_method_label') }}</td><td style="padding:5px 0;text-transform:capitalize;">{{ $order->payment_method ?? '&mdash;' }}</td></tr>
                    @if($order->promo_code)
                    <tr><td style="padding:5px 0;color:#777;">{{ __('admin.orders.promo_code_label') }}</td><td style="padding:5px 0;"><span style="background:#fcf8e3;color:#8a6d3b;padding:2px 6px;border-radius:3px;font-family:monospace;font-size:12px;">{{ $order->promo_code }}</span></td></tr>
                    @endif
                    <tr><td style="padding:5px 0;color:#777;">{{ __('admin.orders.ip_address') }}</td><td style="padding:5px 0;font-family:monospace;font-size:12px;color:#777;">{{ $order->ip_address }}</td></tr>
                </table>
            </div>
        </div>

        @if($order->services->count() > 0)
        <div class="card" style="margin-bottom:15px;">
            <div class="card-header"><strong>{{ __('admin.orders.services_header') }} ({{ $order->services->count() }})</strong></div>
            <table class="data-table">
                <thead><tr><th>{{ __('common.table.product') }}</th><th>{{ __('common.table.domain') }}</th><th>{{ __('admin.orders.billing') }}</th><th style="text-align:right;">{{ __('common.table.amount') }}</th><th>{{ __('common.table.status') }}</th></tr></thead>
                <tbody>
                @foreach($order->services as $svc)
                <tr>
                    <td><a href="{{ route('admin.services.show', $svc) }}" style="color:#337ab7;">{{ $svc->product?->name ?? 'N/A' }}</a></td>
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
            <div class="card-header"><strong>{{ __('admin.orders.domains_header') }} ({{ $order->domains->count() }})</strong></div>
            <table class="data-table">
                <thead><tr><th>{{ __('common.table.domain') }}</th><th>{{ __('common.table.type') }}</th><th>{{ __('admin.orders.expiry') }}</th><th>{{ __('common.table.status') }}</th></tr></thead>
                <tbody>
                @foreach($order->domains as $dom)
                <tr>
                    <td style="font-family:monospace;font-weight:600;">{{ $dom->domain }}</td>
                    <td style="text-transform:capitalize;">{{ $dom->type }}</td>
                    <td>{{ $dom->expiry_date?->format(date_fmt()) ?? '&mdash;' }}</td>
                    <td><span class="badge-{{ strtolower($dom->status) }}">{{ ucfirst($dom->status) }}</span></td>
                </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @endif

        @if(strtolower($order->status) === 'fraud' && $order->fraud_output)
        <div style="padding:12px 15px;background:#f2dede;border:1px solid #ebccd1;border-radius:4px;margin-bottom:15px;">
            <strong style="color:#a94442;">{{ __('admin.orders.fraud_info') }}</strong>
            <p style="margin:6px 0 0;font-size:13px;color:#a94442;">{{ $order->fraud_output }}</p>
        </div>
        @endif

        @if($order->notes)
        <div class="card">
            <div class="card-header"><strong>{{ __('admin.orders.notes') }}</strong></div>
            <div class="card-body" style="font-size:13px;white-space:pre-wrap;color:#555;">{{ $order->notes }}</div>
        </div>
        @endif
    </div>

    {{-- Right column --}}
    <div>
        @if($order->client)
        <div class="panel" style="margin-bottom:15px;">
            <div class="panel-heading panel-primary">{{ __('admin.orders.client_info') }}</div>
            <div class="panel-body">
                <table style="width:100%;font-size:13px;border-collapse:collapse;">
                    <tr><td style="padding:4px 0;color:#777;width:35%;">{{ __('admin.orders.name') }}</td><td style="padding:4px 0;"><a href="{{ $order->client ? route("admin.clients.show", $order->client) : "#" }}" style="color:#337ab7;font-weight:600;">{{ $order->client?->display_name ?? "Deleted Client" }}</a></td></tr>
                    <tr><td style="padding:4px 0;color:#777;">{{ __('admin.orders.email') }}</td><td style="padding:4px 0;">{{ $order->client?->email ?? "-" }}</td></tr>
                    @if($order->client?->company_name)
                    <tr><td style="padding:4px 0;color:#777;">{{ __('admin.orders.company') }}</td><td style="padding:4px 0;">{{ $order->client?->company_name }}</td></tr>
                    @endif
                </table>
            </div>
        </div>
        @endif

        <div class="panel">
            <div class="panel-heading panel-primary">{{ __('admin.orders.actions') }}</div>
            <div class="panel-body" style="display:flex;flex-direction:column;gap:6px;">
                @if(strtolower($order->status) === 'pending')
                <form method="POST" action="{{ route('admin.orders.accept', $order) }}">
                    @csrf
                    <button type="submit" class="btn btn-success btn-sm" style="width:100%;">{{ __('admin.orders.accept_order') }}</button>
                </form>
                @endif

                @if(! in_array(strtolower($order->status), ['cancelled', 'fraud']))
                <form method="POST" action="{{ route('admin.orders.cancel', $order) }}" onsubmit="return confirm('{{ __('admin.orders.confirm_cancel') }}')">
                    @csrf
                    <button type="submit" class="btn btn-warning btn-sm" style="width:100%;">{{ __('admin.orders.cancel_order') }}</button>
                </form>
                @endif

                @if(strtolower($order->status) !== 'fraud')
                <form method="POST" action="{{ route('admin.orders.fraud', $order) }}" onsubmit="return confirm('{{ __('admin.orders.confirm_fraud') }}')">
                    @csrf
                    <button type="submit" class="btn btn-danger btn-sm" style="width:100%;">{{ __('common.actions.mark_fraud') }}</button>
                </form>
                @endif

                @if(in_array(strtolower($order->status), ['cancelled', 'fraud', 'pending']))
                <form method="POST" action="{{ route('admin.orders.delete', $order) }}" onsubmit="return confirm('{{ __('admin.orders.confirm_delete') }}')">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-default btn-sm" style="width:100%;color:#d9534f;">{{ __('admin.orders.delete_order') }}</button>
                </form>
                @endif

                @if($order->invoice)
                <a href="{{ route('admin.invoices.show', $order->invoice) }}" class="btn btn-default btn-sm" style="width:100%;text-align:center;">
                    {{ __('admin.orders.view_invoice') }} #{{ $order->invoice->invoice_num }}
                </a>
                @endif
            </div>
        </div>
    </div>
</div>

@endsection
