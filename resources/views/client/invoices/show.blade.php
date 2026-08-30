@extends("client.layouts.app")
@section("title", __("client.invoices.invoice_prefix", ["id" => $invoice->invoice_num ?? $invoice->id]))
@section("content")

<a href="{{ route("client.invoices.index") }}" class="pn-back">
    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
    {{ __('client.invoices.back_to_invoices') }}
</a>

<div class="pn-page-header">
    <div>
        <h1 class="pn-page-title">{{ __('client.invoices.invoice_prefix', ['id' => $invoice->invoice_num ?? $invoice->id]) }}</h1>
        <p class="pn-page-subtitle">{{ __('client.invoices.issued') }} {{ $invoice->date?->format(date_fmt()) ?? "N/A" }}</p>
    </div>
    <a href="{{ route('client.invoices.pdf', $invoice) }}" class="pn-btn" style="background:var(--primary);color:#fff;padding:6px 14px;border-radius:6px;text-decoration:none;font-size:13px;margin-right:8px;">{{ __('client.invoices.download_pdf') }}</a>
    <span class="badge badge-{{ strtolower($invoice->status) }}" style="font-size:13px;padding:5px 14px">{{ __('client.status.' . strtolower($invoice->status)) }}</span>
</div>

<div class="pn-card mb-24">
    <div class="pn-card-header">
        <span class="pn-card-title">{{ __('client.invoices.invoice_details') }}</span>
        <div style="font-size:13px;color:var(--muted)">
            {{ __('client.invoices.due') }}: <strong>{{ $invoice->due_date?->format(date_fmt()) ?? "N/A" }}</strong>
            @if($invoice->payment_method) &nbsp;·&nbsp; {{ __('client.invoices.paid_via') }} {{ payment_method_label((string) $invoice->payment_method) }} @endif
        </div>
    </div>
    <div class="pn-card-body-flush">
        <table class="pn-table">
            <thead>
                <tr>
                    <th>{{ __('common.table.description') }}</th>
                    <th style="text-align:right;width:70px">{{ __('common.table.qty') }}</th>
                    <th style="text-align:right;width:110px">{{ __('common.table.price') }}</th>
                    <th style="text-align:right;width:120px">{{ __('common.table.amount') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($invoice->items as $item)
                <tr>
                    <td>{{ $item->description }}</td>
                    <td style="text-align:right">{{ (int) $item->qty }}</td>
                    <td style="text-align:right">{{ money_fmt($item->amount) }}</td>
                    <td style="text-align:right;font-weight:600">{{ money_fmt($item->amount * (int) $item->qty) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="pn-card-body" style="border-top:1px solid var(--border)">
        <div style="max-width:280px;margin-left:auto">
            @if($invoice->subtotal && $invoice->subtotal != $invoice->total)
            <div style="display:flex;justify-content:space-between;padding:7px 0;font-size:13.5px;border-bottom:1px solid var(--border)">
                    <span class="text-muted">{{ __('admin.invoices.net') }}</span>
                <span>{{ money_fmt($invoice->subtotal) }}</span>
            </div>
            @endif
            @if($invoice->tax ?? false)
            <div style="display:flex;justify-content:space-between;padding:7px 0;font-size:13.5px;border-bottom:1px solid var(--border)">
                <span class="text-muted">{{ __('client.cart.tax') }}{{ $invoice->tax_rate > 0 ? " (" . rtrim(rtrim(number_format((float) $invoice->tax_rate, 2), '0'), '.') . "%)" : '' }}</span>
                <span>{{ money_fmt($invoice->tax) }}</span>
            </div>
            @endif
            @if(($invoice->tax2 ?? 0) > 0)
            <div style="display:flex;justify-content:space-between;padding:7px 0;font-size:13.5px;border-bottom:1px solid var(--border)">
                <span class="text-muted">{{ __('client.cart.tax').' 2' }}{{ $invoice->tax_rate2 > 0 ? " (" . rtrim(rtrim(number_format((float) $invoice->tax_rate2, 2), '0'), '.') . "%)" : '' }}</span>
                <span>{{ money_fmt($invoice->tax2) }}</span>
            </div>
            @endif
            <div style="display:flex;justify-content:space-between;padding:12px 0 4px;font-size:17px;font-weight:800;color:var(--primary)">
                <span>{{ __('admin.invoices.gross') }}</span>
                <span>{{ money_fmt($invoice->total) }}</span>
            </div>
            @if(isset($balance) && $balance > 0 && $balance < (float) $invoice->total)
            <div style="display:flex;justify-content:space-between;padding:7px 0;font-size:13.5px;border-top:1px solid var(--border)">
                <span class="text-muted">{{ __('client.invoices.amount_paid') }}</span>
                <span>{{ money_fmt((float) $invoice->total - $balance) }}</span>
            </div>
            <div style="display:flex;justify-content:space-between;padding:7px 0;font-size:14px;font-weight:700;color:#dc3545">
                <span>{{ __('client.invoices.remaining_balance') }}</span>
                <span>{{ money_fmt($balance) }}</span>
            </div>
            @endif
        </div>
    </div>
</div>

@if(strtolower($invoice->status) === "payment_pending" || (isset($pendingNotification) && $pendingNotification))
<div class="pn-alert pn-alert-info mb-24" style="padding:14px 18px;">
    <strong>{{ __('client.invoices.payment_notification_pending_title') }}</strong><br>
    {{ __('client.invoices.payment_notification_pending_text') }}
    @if(isset($pendingNotification) && $pendingNotification)
    <br><small class="text-muted">{{ __('client.invoices.reported_on') }} {{ $pendingNotification->created_at->timezone(display_tz())->format(datetime_fmt()) }} — {{ money_fmt((float) $pendingNotification->amount) }}</small>
    @endif
</div>
@endif

@if(in_array(strtolower($invoice->status), ["unpaid", "overdue", "partially_paid"]))
<div class="pn-card mb-24">
    <div class="pn-card-header" style="background:linear-gradient(135deg,var(--primary),#1e5fa0);border-radius:12px 12px 0 0">
        <span style="font-size:15px;font-weight:700;color:#fff">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display:inline;vertical-align:-2px;margin-right:6px"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
            {{ __('client.invoices.pay_this_invoice') }} — {{ money_fmt($balance ?? $invoice->total) }}
        </span>
    </div>
    <div class="pn-card-body">
        @if(!empty($gateways))
        <p class="text-muted text-sm mb-16">{{ __('client.invoices.select_payment_method') }}</p>
        <div class="gw-tabs">
            @foreach($gateways as $i => $gw)
            <div class="gw-tab {{ $i === 0 ? "active" : "" }}" onclick="switchGw(event, '{{ $gw }}')">
                {{ $gatewayLabels[$gw] ?? ucfirst($gw) }}
            </div>
            @endforeach
        </div>
        @foreach($gateways as $i => $gw)
        <div id="gw-{{ $gw }}" class="gw-panel {{ $i === 0 ? "active" : "" }}">
            @if(isset($gatewayForms[$gw]) && $gatewayForms[$gw])
                {!! $gatewayForms[$gw] !!}
                @if($gw === "banktransfer" && !(isset($pendingNotification) && $pendingNotification))
                    @include("client.invoices.partials.payment-notification-form")
                @endif
            @elseif($gw === "stripe")
                <div class="gw-form-box">
                    <p class="text-muted text-sm mb-16">{{ __('client.invoices.stripe_desc') }}</p>
                    <div id="stripe-card-element" style="border:1.5px solid var(--border);padding:12px;border-radius:var(--radius-sm);background:var(--card);margin-bottom:16px">
                        <em style="color:var(--muted);font-size:13px">{{ __('client.invoices.stripe_placeholder') }}</em>
                    </div>
                    <button type="button" onclick="stripePayNow({{ $invoice->id }})" class="btn btn-primary">
                        {{ __('client.invoices.pay_with_card', ['amount' => number_format($invoice->total, 2)]) }}
                    </button>
                </div>
            @elseif($gw === "paypal")
                <div class="gw-form-box">
                    <p class="text-muted text-sm mb-16">{{ __('client.invoices.paypal_desc') }}</p>
                    <div id="paypal-button-container-{{ $invoice->id }}" style="max-width:280px"></div>
                </div>
            @else
                <div class="gw-form-box">
                    <p class="text-muted text-sm">{{ __('client.invoices.payment_form_for') }} <strong>{{ $gatewayLabels[$gw] ?? ucfirst($gw) }}</strong> {{ __('client.invoices.not_configured') }}</p>
                </div>
            @endif
        </div>
        @endforeach
        @else
        <div class="pn-alert pn-alert-warning">
            {{ __('client.invoices.no_payment_methods') }}
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
        if (d.success) { alert("{{ __('client.invoices.payment_intent') }} " + d.client_secret); }
        else { alert("{{ __('client.invoices.payment_error') }} " + (d.message || "{{ __('client.invoices.unknown_error') }}")); }
    }).catch(e => alert("{{ __('client.invoices.network_error') }} " + e.message));
}
</script>
@endsection

@endsection
