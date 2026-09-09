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
            @php
                // The customer pays in the billing currency; the balance is
                // kept in the shop currency. Limits and presets follow the
                // rate so 5 of one currency is never mistaken for 5 of another.
                $converted = ($exchangeRate ?? null) !== null;
                $sign      = $converted ? ($billingCurrency->suffix ?: $billingCurrency->code) : ($shopCurrency?->prefix ?: '$');
                $presets   = collect([10, 25, 50, 100, 250, 500])->map(fn ($v) => $converted ? funds_round_preset($v * $exchangeRate) : $v)->unique()->values();
                $minimum   = $converted ? funds_round_preset(5 * $exchangeRate) : 5;
                $maximum   = $converted ? funds_round_preset(10000 * $exchangeRate) : 10000;
            @endphp

            <div class="form-group">
                <label class="form-label">{{ __('client.funds.quick_amounts') }}</label>
                <div class="pn-amount-grid">
                    @foreach($presets as $preset)
                    <button type="button" class="pn-amount-btn" onclick="setAmount({{ $preset }}, this)">
                        {{ number_format($preset, 0, ',', '.') }} {{ $sign }}
                    </button>
                    @endforeach
                </div>
            </div>
            <div class="form-group">
                <label class="form-label" for="amount">{{ __('client.funds.custom_amount') }}</label>
                <div style="position:relative">
                    <span style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--muted);font-size:15px;font-weight:600">{{ $sign }}</span>
                    <input type="number" id="amount" name="amount" value="{{ old("amount") }}"
                        min="{{ $minimum }}" max="{{ $maximum }}" step="0.01" required
                        class="form-control" style="padding-left:34px" placeholder="0,00"
                        @if($converted) data-rate="{{ $exchangeRate }}" @endif>
                </div>
                <div class="form-hint">
                    {{ number_format($minimum, 0, ',', '.') }} {{ $sign }} – {{ number_format($maximum, 0, ',', '.') }} {{ $sign }}
                </div>
            </div>

            @if($converted)
            {{-- Conversion box: the customer pays in one currency and the
                 services are priced in another; how much balance they get and
                 at which rate has to be visible before they pay. --}}
            <div class="form-group">
                <div style="border:1px solid var(--border);border-radius:10px;padding:14px 16px;background:var(--bg);">
                    <div style="display:flex;justify-content:space-between;align-items:baseline;gap:12px;flex-wrap:wrap;">
                        <span style="font-size:13px;color:var(--muted);">{{ __('client.funds.you_will_receive') }}</span>
                        <strong id="convertedAmount" style="font-size:19px;font-weight:800;">{{ $shopCurrency?->prefix ?: '$' }}0.00</strong>
                    </div>
                    <div style="font-size:12px;color:var(--muted);margin-top:8px;line-height:1.55;">
                        1 {{ $shopCurrency->code }} = {{ number_format($exchangeRate, 4, ',', '.') }} {{ $billingCurrency->code }}
                        @if($rateSource)
                            · {{ $rateSource }}@if($rateDate) {{ $rateDate }}@endif
                            @if($rateBulletin) ({{ __('client.funds.bulletin') }} {{ $rateBulletin }})@endif
                        @endif
                    </div>
                    <div style="font-size:12px;color:var(--muted);margin-top:6px;">{{ __('client.funds.rate_notice') }}</div>
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
function setAmount(v, btn) {
    document.getElementById("amount").value = v;
    if (window.updateConversion) { window.updateConversion(); }
    document.querySelectorAll(".pn-amount-btn").forEach(b => b.classList.remove("selected"));
    btn.classList.add("selected");
}
</script>
@endsection

@endsection

<script>
(function () {
    var input = document.getElementById('amount');
    var target = document.getElementById('convertedAmount');

    if (!input || !target) { return; }

    var rate = parseFloat(input.getAttribute('data-rate') || '0');
    var sign = @json($shopCurrency?->prefix ?: '$');

    window.updateConversion = function () {
        var paid = parseFloat(input.value);

        if (!rate || !isFinite(paid) || paid <= 0) {
            target.textContent = sign + '0.00';
            return;
        }

        target.textContent = sign + (paid / rate).toFixed(2);
    };

    input.addEventListener('input', window.updateConversion);
    window.updateConversion();
})();
</script>
