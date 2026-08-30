@extends("client.layouts.app")
@section("title", __("client.funds.title"))
@section("content")

<div class="pn-page-header">
    <div>
        <h1 class="pn-page-title">{{ __('client.nav.add_funds') }}</h1>
        <p class="pn-page-subtitle">{{ __('client.funds.subtitle') }}</p>
    </div>
</div>

@php $credit = auth()->user()->credit ?? 0; @endphp
<div class="pn-card mb-24" style="max-width:100%;background:linear-gradient(135deg,var(--primary),#1e5fa0);border:none">
    <div class="pn-card-body" style="text-align:center;padding:28px">
        <div style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:0.7px;color:rgba(255,255,255,0.65);margin-bottom:8px">{{ __('client.funds.current_credit') }}</div>
        <div style="font-size:42px;font-weight:900;color:#fff;letter-spacing:-1px">{{ money_fmt($credit) }}</div>
        <div style="font-size:13px;color:rgba(255,255,255,0.55);margin-top:6px">{{ __('client.funds.available_credit_desc') }}</div>
    </div>
</div>

<div class="pn-card">
    <div class="pn-card-header"><span class="pn-card-title">{{ __('client.funds.select_amount') }}</span></div>
    <div class="pn-card-body">
        @if($errors->any())
        <div class="pn-alert pn-alert-error">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="flex-shrink:0;margin-top:1px"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <ul>@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
        @endif

        <form method="POST" action="{{ route("client.funds.store") }}">
            @csrf
            <div class="form-group">
                <label class="form-label">{{ __('client.funds.quick_amounts') }}</label>
                <div class="pn-amount-grid">
                    @foreach([10, 25, 50, 100, 250, 500] as $preset)
                    <button type="button" class="pn-amount-btn" onclick="setAmount({{ $preset }}, this)">
                        {{ $currencyPrefix }}{{ $preset }}
                    </button>
                    @endforeach
                </div>
            </div>
            <div class="form-group">
                <label class="form-label" for="amount">{{ __('client.funds.custom_amount') }}</label>
                <div style="position:relative">
                    <span style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--muted);font-size:15px;font-weight:600">{{ $currencyPrefix }}</span>
                    <input type="number" id="amount" name="amount" value="{{ old("amount") }}" min="5" max="10000" step="0.01" required
                        class="form-control" style="padding-left:26px" placeholder="0.00">
                </div>
                <div class="form-hint">{{ __('client.funds.amount_range', ['min' => money_fmt(5), 'max' => money_fmt(10000)]) }}</div>
            </div>

            @if($taxRate > 0)
            <div style="background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:12px 14px;margin-bottom:14px;">
                <div style="display:flex;justify-content:space-between;padding:3px 0;font-size:13px;">
                    <span class="text-muted">{{ __('admin.invoices.net') }}</span>
                    <span id="funds-net">—</span>
                </div>
                <div style="display:flex;justify-content:space-between;padding:3px 0;font-size:13px;">
                    <span class="text-muted">{{ __('admin.invoices.tax') }} ({{ rtrim(rtrim(number_format($taxRate, 2), '0'), '.') }}%)</span>
                    <span id="funds-vat">—</span>
                </div>
                <div style="display:flex;justify-content:space-between;padding:5px 0 0;font-size:15px;font-weight:700;border-top:1px solid var(--border);margin-top:4px;">
                    <span>{{ __('admin.invoices.gross') }}</span>
                    <span id="funds-total">—</span>
                </div>
            </div>
            @endif
            <div class="form-group">
                <label class="form-label" for="payment_method">{{ __('client.checkout.payment_method') }} <span class="req">*</span></label>
                <select id="payment_method" name="payment_method" required class="form-control">
                    <option value="">-- {{ __('client.funds.select_payment_method') }} --</option>
                    @if(isset($gateways) && $gateways->isNotEmpty())
                        @foreach($gateways as $gateway)
                        <option value="{{ $gateway }}" {{ old("payment_method") === $gateway ? "selected" : "" }}>
                            {{ payment_method_label((string) $gateway) }}
                        </option>
                        @endforeach
                    @else
                        <option value="" disabled>{{ __('client.invoices.no_payment_methods') }}</option>
                    @endif
                </select>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center">
                {{ __('client.nav.add_funds') }} &rarr;
            </button>
        </form>
    </div>
</div>

@section("scripts")
<script>
const FUNDS_TAX_RATE = {{ $taxRate }};
const FUNDS_PREFIX = @json($currencyPrefix);
const FUNDS_SUFFIX = @json($currencySuffix);

function fundsFmt(n) {
    return FUNDS_PREFIX + Number(n).toFixed(2) + FUNDS_SUFFIX;
}

function fundsUpdate() {
    var total = document.getElementById("funds-total");
    if (!total) return;
    var amount = parseFloat(document.getElementById("amount").value) || 0;
    var vat = amount * FUNDS_TAX_RATE / 100;
    document.getElementById("funds-net").textContent = fundsFmt(amount);
    document.getElementById("funds-vat").textContent = fundsFmt(vat);
    total.textContent = fundsFmt(amount + vat);
}

function setAmount(v, btn) {
    document.getElementById("amount").value = v;
    document.querySelectorAll(".pn-amount-btn").forEach(b => b.classList.remove("selected"));
    btn.classList.add("selected");
    fundsUpdate();
}

document.getElementById("amount").addEventListener("input", fundsUpdate);
fundsUpdate();
</script>
@endsection

@endsection
