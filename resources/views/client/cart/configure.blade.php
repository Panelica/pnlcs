@extends('client.layouts.app')
@section('title', 'Configure: '. $product->name)
@section('styles')
<style>
    .config-layout { display: grid; grid-template-columns: 1fr 320px; gap: 24px; }
    @media (max-width: 900px) { .config-layout { grid-template-columns: 1fr; } }
    .cycle-options { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; }
    .cycle-option { border: 1px solid #ddd; border-radius: 4px; padding: 10px; cursor: pointer; text-align: center; transition: all 0.15s; }
    .cycle-option:hover { border-color: #337ab7; background: #f0f6ff; }
    .cycle-option input[type=radio] { display: none; }
    .cycle-option.selected { border-color: #337ab7; background: #e8f0fe; }
    .cycle-price { font-size: 16px; font-weight: 600; color: #1a4d80; }
    .cycle-label { font-size: 11px; color: #777; margin-top: 3px; text-transform: capitalize; }
    .order-summary { position: sticky; top: 70px; }
    .summary-row { display: flex; justify-content: space-between; padding: 7px 0; font-size: 13px; border-bottom: 1px solid #f0f0f0; }
    .summary-row:last-child { border-bottom: none; font-weight: 600; }
</style>
@endsection
@section('content')

<div class="page-header">
    <h1>Configure: {{ $product->name }}</h1>
    <a href="{{ route('client.store') }}" class="btn btn-outline btn-sm">&larr; Back</a>
</div>

<form method="POST" action="{{ route('client.cart.add') }}" id="configForm">
    @csrf
    <input type="hidden" name="product_id" value="{{ $product->id }}">

    <div class="config-layout">
        <div>
            {{-- Billing Cycle --}}
            <div class="pn-card" style="margin-bottom:16px;">
                <div class="pn-card-header">Billing Cycle</div>
                <div class="pn-card-body">
                    @php $pricing = $product->pricing->first(); $cycles = ['monthly','quarterly','semiannually','annually','biennially','triennially']; @endphp
                    <div class="cycle-options">
                        @if($pricing)
                            @foreach($cycles as $cycle)
                                @if(isset($pricing->{$cycle}) && (float)$pricing->{$cycle} > 0)
                                <label class="cycle-option {{ $loop->first ? 'selected' : '' }}">
                                    <input type="radio" name="billing_cycle" value="{{ $cycle }}" {{ $loop->first ? 'checked' : '' }}
                                        onchange="document.querySelectorAll('.cycle-option').forEach(e=>e.classList.remove('selected')); this.closest('.cycle-option').classList.add('selected')">
                                    <div class="cycle-price">${{ number_format($pricing->{$cycle}, 2) }}</div>
                                    <div class="cycle-label">{{ $cycle }}</div>
                                </label>
                                @endif
                            @endforeach
                        @else
                            <div style="color:#999; font-size:13px; grid-column:1/-1;">No pricing available.</div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Domain --}}
            @if($product->require_domain ?? false)
            <div class="pn-card" style="margin-bottom:16px;">
                <div class="pn-card-header">Domain</div>
                <div class="pn-card-body">
                    <div style="display:flex; gap:8px; margin-bottom:12px;">
                        <label style="display:flex; align-items:center; gap:6px; font-size:13px; cursor:pointer;">
                            <input type="radio" name="domain_option" value="register" checked> Register new domain
                        </label>
                        <label style="display:flex; align-items:center; gap:6px; font-size:13px; cursor:pointer;">
                            <input type="radio" name="domain_option" value="transfer"> Transfer existing
                        </label>
                        <label style="display:flex; align-items:center; gap:6px; font-size:13px; cursor:pointer;">
                            <input type="radio" name="domain_option" value="own"> Use own domain
                        </label>
                    </div>
                    <div class="form-group">
                        <input type="text" name="domain" class="form-control" placeholder="yourdomain.com" value="{{ old('domain') }}">
                    </div>
                </div>
            </div>
            @endif

            {{-- Additional Notes --}}
            <div class="pn-card">
                <div class="pn-card-header">Additional Notes <span style="font-weight:400; color:#999;">(optional)</span></div>
                <div class="pn-card-body">
                    <textarea name="notes" rows="3" class="form-control" placeholder="Any special requirements...">{{ old('notes') }}</textarea>
                </div>
            </div>
        </div>

        {{-- Order Summary --}}
        <div class="order-summary">
            <div class="pn-card">
                <div class="pn-card-header">Order Summary</div>
                <div class="pn-card-body">
                    <div class="summary-row">
                        <span style="color:#777;">Product</span>
                        <span style="font-weight:500;">{{ $product->name }}</span>
                    </div>
                    <div class="summary-row">
                        <span style="color:#777;">Billing Cycle</span>
                        <span id="summaryBilling">&mdash;</span>
                    </div>
                    <div class="summary-row">
                        <span>Total</span>
                        <span id="summaryTotal" style="color:#1a4d80;">&mdash;</span>
                    </div>
                    <button type="submit" class="btn btn-primary" style="width:100%; margin-top:14px; justify-content:center;">Add to Cart &rarr;</button>
                </div>
            </div>
        </div>
    </div>
</form>

@section('scripts')
<script>
document.querySelectorAll('input[name=billing_cycle]').forEach(function(radio) {
    radio.addEventListener('change', function() {
        var labels = { monthly: 'Monthly', quarterly: 'Quarterly', semiannually: 'Semi-Annually', annually: 'Annually', biennially: 'Biennially', triennially: 'Triennially' };
        document.getElementById('summaryBilling').textContent = labels[this.value] || this.value;
        var label = this.closest('.cycle-option');
        var price = label ? label.querySelector('.cycle-price').textContent : '-';
        document.getElementById('summaryTotal').textContent = price;
    });
});
var first = document.querySelector('input[name=billing_cycle]:checked');
if (first) { first.dispatchEvent(new Event('change')); }
</script>
@endsection

@endsection
