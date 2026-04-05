@extends("client.layouts.app")
@section("title", __("client.checkout.title"))
@section("content")

<a href="{{ route("client.cart.index") }}" class="pn-back">
    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
    {{ __('client.cart.back_to_cart') }}
</a>

<div class="pn-page-header">
    <div>
        <h1 class="pn-page-title">{{ __('common.actions.checkout') }}</h1>
        <p class="pn-page-subtitle">{{ __('client.checkout.subtitle') }}</p>
    </div>
</div>

@if(session("error"))<div class="pn-alert pn-alert-error mb-16">{{ session("error") }}</div>@endif

<form method="POST" action="{{ route("client.cart.process") }}">
    @csrf
    <div class="pn-checkout-grid">
        <div>
            <div class="pn-card mb-16">
                <div class="pn-card-header"><span class="pn-card-title">{{ __('client.checkout.contact_details') }}</span></div>
                <div class="pn-card-body">
                    <div class="form-grid-2">
                        <div class="form-group">
                            <label class="form-label">{{ __('common.form.first_name') }}<span class="req">*</span></label>
                            <input type="text" name="first_name" class="form-control" value="{{ auth()->user()?->first_name ?? old("first_name") }}" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">{{ __('common.form.last_name') }}<span class="req">*</span></label>
                            <input type="text" name="last_name" class="form-control" value="{{ auth()->user()?->last_name ?? old("last_name") }}" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">{{ __('common.form.email_address') }}<span class="req">*</span></label>
                        <input type="email" name="email" class="form-control" value="{{ auth()->user()?->email ?? old("email") }}" required>
                    </div>
                </div>
            </div>

            <div class="pn-card mb-16">
                <div class="pn-card-header"><span class="pn-card-title">{{ __('client.checkout.payment_method') }}</span></div>
                <div class="pn-card-body">
                    @foreach($paymentMethods as $value => $label)
                    <label class="pn-pay-option {{ $loop->first ? "selected" : "" }}" onclick="selectPay(this)">
                        <input type="radio" name="payment_method" value="{{ $value }}" {{ $loop->first ? "checked" : "" }} style="margin:0;accent-color:var(--primary)">
                        <div>
                            <div style="font-size:13.5px;font-weight:600">{{ $label }}</div>
                        </div>
                    </label>
                    @endforeach
                </div>
            </div>

            <label style="display:flex;align-items:flex-start;gap:10px;cursor:pointer;padding:14px;background:#f8fafc;border:1.5px solid var(--border);border-radius:var(--radius-sm)">
                <input type="checkbox" name="terms" id="terms" value="1" required style="margin-top:2px;flex-shrink:0;accent-color:var(--primary)">
                <span style="font-size:13px;color:var(--muted)">
                    {{ __('client.auth.i_agree_to') }} <a href="#" class="link">{{ __('client.auth.terms_of_service') }}</a> {{ __('client.auth.and') }} <a href="#" class="link">{{ __('client.auth.privacy_policy') }}</a>.
                </span>
            </label>
        </div>

        <div>
            <div class="pn-card" style="position:sticky;top:80px">
                <div class="pn-card-header"><span class="pn-card-title">{{ __('client.cart.order_summary') }}</span></div>
                <div class="pn-card-body">
                    @foreach($totals["items"] as $item)
                    <div class="pn-order-row">
                        <span class="key" style="font-size:13px">{{ $item["product_name"] ?? "Product" }}</span>
                        <span style="font-weight:600">${{ number_format($item["price"] ?? 0, 2) }}</span>
                    </div>
                    @endforeach
                    @if(($totals["discount"] ?? 0) > 0)
                    <div class="pn-order-row" style="color:var(--success)">
                        <span>{{ __('client.cart.discount') }}</span><span>-${{ number_format($totals["discount"], 2) }}</span>
                    </div>
                    @endif
                    @if(($totals["tax"] ?? 0) > 0)
                    <div class="pn-order-row">
                        <span class="key">{{ __('client.cart.tax') }}</span><span>${{ number_format($totals["tax"], 2) }}</span>
                    </div>
                    @endif
                    <div class="pn-order-row">
                        <span>{{ __('client.cart.total') }}</span>
                        <span style="color:var(--primary);font-size:18px">${{ number_format($totals["total"], 2) }}</span>
                    </div>
                    <button type="submit" class="btn btn-accent" style="width:100%;justify-content:center;margin-top:20px;font-size:15px;padding:12px">
                        {{ __('client.checkout.place_order') }} &rarr;
                    </button>
                    <p class="text-muted text-sm" style="text-align:center;margin-top:10px">
                        {{ __('client.checkout.secure_checkout') }} &mdash; ${{ number_format($totals["total"], 2) }}
                    </p>
                </div>
            </div>
        </div>
    </div>
</form>

@section("scripts")
<script>
function selectPay(el) {
    document.querySelectorAll(".pn-pay-option").forEach(o => o.classList.remove("selected"));
    el.classList.add("selected");
}
</script>
@endsection

@endsection
