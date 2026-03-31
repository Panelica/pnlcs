@extends('client.layouts.app')
@section('title', 'Checkout')
@section('styles')
<style>
    .checkout-layout { display: grid; grid-template-columns: 1fr 320px; gap: 24px; }
    @media (max-width: 900px) { .checkout-layout { grid-template-columns: 1fr; } }
    .payment-option { border: 1px solid #ddd; border-radius: 4px; padding: 12px 14px; cursor: pointer; margin-bottom: 8px; display: flex; align-items: center; gap: 10px; transition: all 0.15s; }
    .payment-option:hover { border-color: #337ab7; background: #f0f6ff; }
    .payment-option input[type=radio] { margin: 0; }
    .payment-option-name { font-size: 13px; font-weight: 500; }
    .order-row { display: flex; justify-content: space-between; padding: 7px 0; font-size: 13px; border-bottom: 1px solid #f0f0f0; }
    .order-row:last-child { border-bottom: none; font-weight: 600; }
</style>
@endsection
@section('content')

<div class="page-header">
    <h1>Checkout</h1>
    <a href="{{ route('client.cart.index') }}" class="btn btn-default btn-sm">&larr; Back to Cart</a>
</div>

@if(session('error'))
<div style="background:#f2dede;border:1px solid #ebccd1;color:#a94442;padding:10px 14px;border-radius:4px;font-size:13px;margin-bottom:16px;">{{ session('error') }}</div>
@endif

<form method="POST" action="{{ route('client.cart.process') }}">
    @csrf
    <div class="checkout-layout">
        <div>
            {{-- Contact Details --}}
            <div class="card" style="margin-bottom:16px;">
                <div class="card-header">Contact Details</div>
                <div class="card-body">
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                        <div class="form-group">
                            <label class="form-label">First Name</label>
                            <input type="text" name="first_name" class="form-control" value="{{ auth()->user()?->first_name ?? old('first_name') }}" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Last Name</label>
                            <input type="text" name="last_name" class="form-control" value="{{ auth()->user()?->last_name ?? old('last_name') }}" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" value="{{ auth()->user()?->email ?? old('email') }}" required>
                    </div>
                </div>
            </div>

            {{-- Payment Method --}}
            <div class="card">
                <div class="card-header">Payment Method</div>
                <div class="card-body">
                    @foreach($paymentMethods as $value => $label)
                    <label class="payment-option">
                        <input type="radio" name="payment_method" value="{{ $value }}" {{ $loop->first ? 'checked' : '' }}>
                        <div class="payment-option-name">{{ $label }}</div>
                    </label>
                    @endforeach
                </div>
            </div>

            {{-- Terms --}}
            <div style="margin-top:14px; display:flex; align-items:flex-start; gap:8px;">
                <input type="checkbox" name="terms" id="terms" value="1" required style="margin-top:3px; flex-shrink:0;">
                <label for="terms" style="font-size:13px; color:#666; cursor:pointer;">
                    I agree to the <a href="#" style="color:#337ab7;">Terms of Service</a> and <a href="#" style="color:#337ab7;">Privacy Policy</a>.
                </label>
            </div>
        </div>

        {{-- Order Summary --}}
        <div>
            <div class="card" style="position:sticky; top:70px;">
                <div class="card-header">Order Summary</div>
                <div class="card-body">
                    @foreach($totals['items'] as $item)
                    <div class="order-row">
                        <span>{{ $item['product_name'] ?? 'Product' }}</span>
                        <span>${{ number_format($item['price'] ?? 0, 2) }}</span>
                    </div>
                    @endforeach
                    @if(($totals['discount'] ?? 0) > 0)
                    <div class="order-row" style="color:#5cb85c;">
                        <span>Discount</span>
                        <span>-${{ number_format($totals['discount'], 2) }}</span>
                    </div>
                    @endif
                    @if(($totals['tax'] ?? 0) > 0)
                    <div class="order-row">
                        <span style="color:#777;">Tax</span>
                        <span>${{ number_format($totals['tax'], 2) }}</span>
                    </div>
                    @endif
                    <div class="order-row" style="margin-top:4px;">
                        <span>Total</span>
                        <span style="color:#1a4d80; font-size:16px;">${{ number_format($totals['total'], 2) }}</span>
                    </div>
                    <button type="submit" class="btn btn-primary" style="width:100%; justify-content:center; text-align:center; display:block; margin-top:16px;">Place Order</button>
                    <p style="font-size:11px; color:#999; text-align:center; margin-top:8px;">Order total: ${{ number_format($totals['total'], 2) }}</p>
                </div>
            </div>
        </div>
    </div>
</form>

@endsection
