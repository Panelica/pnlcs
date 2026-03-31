@extends('client.layouts.app')
@section('title', 'Invoice #'. ($invoice->invoice_num ?? $invoice->id))
@section('styles')
<style>
    .invoice-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; }
    .invoice-meta { font-size: 13px; color: #777; line-height: 1.8; }
    .totals-table { width: 300px; margin-left: auto; font-size: 13px; }
    .totals-table td { padding: 5px 10px; }
    .totals-table .grand-total { font-weight: 600; font-size: 15px; border-top: 2px solid #333; }
    .pay-now-section { margin-top: 24px; background: #fff; border: 1px solid #d5e5f5; border-radius: 6px; overflow: hidden; }
    .pay-now-header { background: #1A4D80; color: #fff; padding: 14px 20px; font-size: 15px; font-weight: 600; }
    .pay-now-body { padding: 20px; }
    .gateway-tab-nav { display: flex; gap: 4px; flex-wrap: wrap; margin-bottom: 20px; }
    .gateway-tab { padding: 8px 18px; border: 1px solid #ddd; border-radius: 4px; cursor: pointer; font-size: 13px; font-weight: 600; color: #555; background: #f8f8f8; transition: all 0.15s; }
    .gateway-tab:hover { border-color: #337ab7; color: #337ab7; }
    .gateway-tab.active { background: #337ab7; border-color: #337ab7; color: #fff; }
    .gateway-form-panel { display: none; }
    .gateway-form-panel.active { display: block; }
    .gateway-direct-form { background: #f8fafe; border: 1px solid #e3eeff; border-radius: 4px; padding: 16px; }
    .badge-unpaid { background: #f0ad4e; color: #fff; }
    .badge-overdue { background: #d9534f; color: #fff; }
    .badge-paid { background: #5cb85c; color: #fff; }
    .badge-cancelled { background: #777; color: #fff; }
    .badge { display: inline-block; border-radius: 3px; font-size: 12px; }
</style>
@endsection
@section('content')

<div class="page-header">
    <h1>Invoice #{{ $invoice->invoice_num ?? $invoice->id }}</h1>
    <span class="badge badge-{{ strtolower($invoice->status) }}" style="font-size:12px; padding:4px 12px;">{{ ucfirst($invoice->status) }}</span>
</div>

<div class="card" style="margin-bottom:20px;">
    <div class="card-body">
        <div class="invoice-header">
            <div class="invoice-meta">
                <div><strong>Date:</strong> {{ $invoice->date?->format('d M Y') ?? 'N/A' }}</div>
                <div><strong>Due Date:</strong> {{ $invoice->due_date?->format('d M Y') ?? 'N/A' }}</div>
                @if($invoice->payment_method)
                <div><strong>Payment Method:</strong> {{ ucwords(str_replace('_', ' ', $invoice->payment_method)) }}</div>
                @endif
            </div>
        </div>

        <table class="data-table" style="margin-bottom:16px;">
            <thead>
                <tr>
                    <th>Description</th>
                    <th style="text-align:right; width:120px;">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach($invoice->items as $item)
                <tr>
                    <td>{{ $item->description }}</td>
                    <td style="text-align:right;">${{ number_format($item->amount, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <table class="totals-table">
            @if($invoice->subtotal && $invoice->subtotal != $invoice->total)
            <tr>
                <td style="color:#777;">Subtotal:</td>
                <td style="text-align:right;">${{ number_format($invoice->subtotal, 2) }}</td>
            </tr>
            @endif
            @if($invoice->tax ?? false)
            <tr>
                <td style="color:#777;">Tax:</td>
                <td style="text-align:right;">${{ number_format($invoice->tax, 2) }}</td>
            </tr>
            @endif
            <tr class="grand-total">
                <td>Total:</td>
                <td style="text-align:right;">${{ number_format($invoice->total, 2) }}</td>
            </tr>
        </table>
    </div>
</div>

{{-- PAY NOW SECTION --}}
@if(in_array(strtolower($invoice->status), ['unpaid', 'overdue']))
<div class="pay-now-section">
    <div class="pay-now-header">&#128179; Pay This Invoice — ${{ number_format($invoice->total, 2) }}</div>
    <div class="pay-now-body">
        @if(!empty($gateways))
        <p style="font-size:13px; color:#666; margin-bottom:16px;">Select a payment method to pay this invoice.</p>

        {{-- Gateway Tabs --}}
        <div class="gateway-tab-nav">
            @foreach($gateways as $i => $gw)
            <div class="gateway-tab {{ $i === 0 ? 'active' : '' }}" onclick="switchGateway('{{ $gw }}')">
                {{ $gatewayLabels[$gw] ?? ucfirst($gw) }}
            </div>
            @endforeach
        </div>

        {{-- Gateway Panels --}}
        @foreach($gateways as $i => $gw)
        <div id="gateway-panel-{{ $gw }}" class="gateway-form-panel {{ $i === 0 ? 'active' : '' }}">
            @if(isset($gatewayForms[$gw]) && $gatewayForms[$gw])
                {!! $gatewayForms[$gw] !!}
            @elseif($gw === 'stripe')
                <div class="gateway-direct-form">
                    <p style="font-size:13px; color:#555; margin-bottom:14px;">Pay securely with your credit or debit card via Stripe.</p>
                    <div id="stripe-card-element" style="border:1px solid #ddd; padding:12px; border-radius:4px; background:#fff; margin-bottom:14px;">
                        <em style="color:#999; font-size:13px;">Stripe card form loads here when configured.</em>
                    </div>
                    <button type="button" onclick="stripePayNow({{ $invoice->id }})" class="btn btn-primary">
                        Pay ${{ number_format($invoice->total, 2) }} with Card
                    </button>
                </div>
            @elseif($gw === 'paypal')
                <div class="gateway-direct-form">
                    <p style="font-size:13px; color:#555; margin-bottom:14px;">Click below to pay via PayPal.</p>
                    <div id="paypal-button-container-{{ $invoice->id }}" style="max-width:300px;"></div>
                </div>
            @else
                <div class="gateway-direct-form">
                    <p style="font-size:13px; color:#777;">Payment form for {{ $gatewayLabels[$gw] ?? ucfirst($gw) }} is not yet configured. Please contact support.</p>
                </div>
            @endif
        </div>
        @endforeach

        @else
        <div style="background:#fcf8e3; border:1px solid #faebcc; color:#8a6d3b; padding:12px 14px; border-radius:4px; font-size:13px;">
            No payment methods are currently active. Please contact support to complete your payment.
        </div>
        @endif
    </div>
</div>

@section('scripts')
<script>
function switchGateway(gw) {
    document.querySelectorAll('.gateway-tab').forEach(function(t) { t.classList.remove('active'); });
    document.querySelectorAll('.gateway-form-panel').forEach(function(p) { p.classList.remove('active'); });
    event.currentTarget.classList.add('active');
    var panel = document.getElementById('gateway-panel-' + gw);
    if (panel) { panel.classList.add('active'); }
}

function stripePayNow(invoiceId) {
    fetch('/gateway/stripe/intent/' + invoiceId, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
        }
    })
    .then(function(r) { return r.json(); })
    .then(function(d) {
        if (d.success) {
            alert('Payment intent created. Client secret: ' + d.client_secret);
        } else {
            alert('Error: ' + (d.message || 'Unknown error'));
        }
    })
    .catch(function(e) { alert('Network error: ' + e.message); });
}
</script>
@endsection
@endif

<a href="{{ route('client.invoices.index') }}" style="color:#337ab7; font-size:13px; display:inline-block; margin-top:16px;">&larr; Back to Invoices</a>

@endsection
