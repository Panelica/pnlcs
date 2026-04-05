@extends("client.layouts.app")
@section("title", __("client.funds.title"))
@section("content")

<div class="pn-page-header">
    <div>
        <h1 class="pn-page-title">Add Funds</h1>
        <p class="pn-page-subtitle">Top up your account credit balance.</p>
    </div>
</div>

@php $credit = auth()->user()->credit ?? 0; @endphp
<div class="pn-card mb-24" style="max-width:100%;background:linear-gradient(135deg,var(--primary),#1e5fa0);border:none">
    <div class="pn-card-body" style="text-align:center;padding:28px">
        <div style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:0.7px;color:rgba(255,255,255,0.65);margin-bottom:8px">Current Account Credit</div>
        <div style="font-size:42px;font-weight:900;color:#fff;letter-spacing:-1px">${{ number_format($credit, 2) }}</div>
        <div style="font-size:13px;color:rgba(255,255,255,0.55);margin-top:6px">Available to use on invoices and orders</div>
    </div>
</div>

<div class="pn-card">
    <div class="pn-card-header"><span class="pn-card-title">Select Amount</span></div>
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
                <label class="form-label">Quick Amounts</label>
                <div class="pn-amount-grid">
                    @foreach([10, 25, 50, 100, 250, 500] as $preset)
                    <button type="button" class="pn-amount-btn" onclick="setAmount({{ $preset }}, this)">
                        ${{ $preset }}
                    </button>
                    @endforeach
                </div>
            </div>
            <div class="form-group">
                <label class="form-label" for="amount">Custom Amount (USD)</label>
                <div style="position:relative">
                    <span style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--muted);font-size:15px;font-weight:600">$</span>
                    <input type="number" id="amount" name="amount" value="{{ old("amount") }}" min="5" max="10000" step="0.01" required
                        class="form-control" style="padding-left:26px" placeholder="0.00">
                </div>
                <div class="form-hint">Minimum $5.00 · Maximum $10,000.00</div>
            </div>
            <div class="form-group">
                <label class="form-label" for="payment_method">Payment Method <span class="req">*</span></label>
                <select id="payment_method" name="payment_method" required class="form-control">
                    <option value="">-- Select payment method --</option>
                    @if(isset($gateways) && $gateways->isNotEmpty())
                        @foreach($gateways as $gateway)
                        <option value="{{ $gateway }}" {{ old("payment_method") === $gateway ? "selected" : "" }}>
                            {{ ucwords(str_replace("_", " ", $gateway)) }}
                        </option>
                        @endforeach
                    @else
                        <option value="banktransfer">Bank Transfer</option>
                        <option value="paypal">PayPal</option>
                    @endif
                </select>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center">
                Add Funds &rarr;
            </button>
        </form>
    </div>
</div>

@section("scripts")
<script>
function setAmount(v, btn) {
    document.getElementById("amount").value = v;
    document.querySelectorAll(".pn-amount-btn").forEach(b => b.classList.remove("selected"));
    btn.classList.add("selected");
}
</script>
@endsection

@endsection
