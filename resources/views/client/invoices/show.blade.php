@extends("client.layouts.app")
@section("title", "Invoice #". ($invoice->invoice_num ?? $invoice->id))
@section("content")

<a href="{{ route("client.invoices.index") }}" class="pn-back">
    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
    Back to Invoices
</a>

<div class="pn-page-header">
    <div>
        <h1 class="pn-page-title">Invoice #{{ $invoice->invoice_num ?? $invoice->id }}</h1>
        <p class="pn-page-subtitle">Issued {{ $invoice->date?->format("d M Y") ?? "N/A" }}</p>
    </div>
    <a href="{{ route('client.invoices.pdf', $invoice) }}" class="pn-btn" style="background:var(--primary);color:#fff;padding:6px 14px;border-radius:6px;text-decoration:none;font-size:13px;margin-right:8px;">Download PDF</a>
    <span class="badge badge-{{ strtolower($invoice->status) }}" style="font-size:13px;padding:5px 14px">{{ ucfirst($invoice->status) }}</span>
</div>

<div class="pn-card mb-24">
    <div class="pn-card-header">
        <span class="pn-card-title">Invoice Details</span>
        <div style="font-size:13px;color:var(--muted)">
            Due: <strong>{{ $invoice->due_date?->format("d M Y") ?? "N/A" }}</strong>
            @if($invoice->payment_method) &nbsp;·&nbsp; Paid via {{ ucwords(str_replace("_", " ", $invoice->payment_method)) }} @endif
        </div>
    </div>
    <div class="pn-card-body-flush">
        <table class="pn-table">
            <thead>
                <tr>
                    <th>{{ __('common.table.description') }}</th>
                    <th style="text-align:right;width:140px">{{ __('common.table.amount') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($invoice->items as $item)
                <tr>
                    <td>{{ $item->description }}</td>
                    <td style="text-align:right;font-weight:600">${{ number_format($item->amount, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="pn-card-body" style="border-top:1px solid var(--border)">
        <div style="max-width:280px;margin-left:auto">
            @if($invoice->subtotal && $invoice->subtotal != $invoice->total)
            <div style="display:flex;justify-content:space-between;padding:7px 0;font-size:13.5px;border-bottom:1px solid #f1f5f9">
                <span class="text-muted">Subtotal</span>
                <span>${{ number_format($invoice->subtotal, 2) }}</span>
            </div>
            @endif
            @if($invoice->tax ?? false)
            <div style="display:flex;justify-content:space-between;padding:7px 0;font-size:13.5px;border-bottom:1px solid #f1f5f9">
                <span class="text-muted">Tax</span>
                <span>${{ number_format($invoice->tax, 2) }}</span>
            </div>
            @endif
            <div style="display:flex;justify-content:space-between;padding:12px 0 4px;font-size:17px;font-weight:800;color:var(--primary)">
                <span>Total Due</span>
                <span>${{ number_format($invoice->total, 2) }}</span>
            </div>
        </div>
    </div>
</div>

@if(in_array(strtolower($invoice->status), ["unpaid", "overdue"]))
<div class="pn-card mb-24">
    <div class="pn-card-header" style="background:linear-gradient(135deg,var(--primary),#1e5fa0);border-radius:12px 12px 0 0">
        <span style="font-size:15px;font-weight:700;color:#fff">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display:inline;vertical-align:-2px;margin-right:6px"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
            Pay This Invoice — ${{ number_format($invoice->total, 2) }}
        </span>
    </div>
    <div class="pn-card-body">
        @if(!empty($gateways))
        <p class="text-muted text-sm mb-16">Select a payment method to complete your payment.</p>
        <div class="gw-tabs">
            @foreach($gateways as $i => $gw)
            <div class="gw-tab {{ $i === 0 ? "active" : "" }}" onclick="switchGw(event, "{{ $gw }}")">
                {{ $gatewayLabels[$gw] ?? ucfirst($gw) }}
            </div>
            @endforeach
        </div>
        @foreach($gateways as $i => $gw)
        <div id="gw-{{ $gw }}" class="gw-panel {{ $i === 0 ? "active" : "" }}">
            @if(isset($gatewayForms[$gw]) && $gatewayForms[$gw])
                {!! $gatewayForms[$gw] !!}
            @elseif($gw === "stripe")
                <div class="gw-form-box">
                    <p class="text-muted text-sm mb-16">Pay securely with your credit or debit card via Stripe.</p>
                    <div id="stripe-card-element" style="border:1.5px solid var(--border);padding:12px;border-radius:var(--radius-sm);background:#fff;margin-bottom:16px">
                        <em style="color:var(--muted);font-size:13px">Stripe card form will load here when configured.</em>
                    </div>
                    <button type="button" onclick="stripePayNow({{ $invoice->id }})" class="btn btn-primary">
                        Pay ${{ number_format($invoice->total, 2) }} with Card
                    </button>
                </div>
            @elseif($gw === "paypal")
                <div class="gw-form-box">
                    <p class="text-muted text-sm mb-16">Click below to pay securely via PayPal.</p>
                    <div id="paypal-button-container-{{ $invoice->id }}" style="max-width:280px"></div>
                </div>
            @else
                <div class="gw-form-box">
                    <p class="text-muted text-sm">Payment form for <strong>{{ $gatewayLabels[$gw] ?? ucfirst($gw) }}</strong> is not yet configured. Please contact support.</p>
                </div>
            @endif
        </div>
        @endforeach
        @else
        <div class="pn-alert pn-alert-warning">
            No payment methods are currently active. Please contact support to complete your payment.
        </div>
        @endif
    </div>
</div>
@endif

@section("scripts")
<script>
function switchGw(e, gw) {
    document.querySelectorAll(".gw-tab").forEach(t => t.classList.remove("active"));
    document.querySelectorAll(".gw-panel").forEach(p => p.classList.remove("active"));
    e.currentTarget.classList.add("active");
    const panel = document.getElementById("gw-" + gw);
    if (panel) panel.classList.add("active");
}
function stripePayNow(id) {
    fetch("/gateway/stripe/intent/" + id, {
        method: "POST",
        headers: {"Content-Type": "application/json","X-CSRF-TOKEN": document.querySelector("meta[name=csrf-token]")?.content || ""}
    }).then(r => r.json()).then(d => {
        if (d.success) { alert("Payment intent: " + d.client_secret); }
        else { alert("Error: " + (d.message || "Unknown error")); }
    }).catch(e => alert("Network error: " + e.message));
}
</script>
@endsection

@endsection
