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

<form method="POST" action="{{ route('client.cart.order') }}">
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
                    @if(isset($gateways) && $gateways->isNotEmpty())
                        @foreach($gateways as $gateway)
                        <label class="payment-option">
                            <input type="radio" name="payment_method" value="{{ $gateway->module ?? $gateway }}" {{ $loop->first ? 'checked' : '' }}>
                            <div class="payment-option-name">{{ $gateway->display_name ?? ucwords(str_replace('_', ' ', $gateway)) }}</div>
                        </label>
                        @endforeach
                    @else
                        <label class="payment-option">
                            <input type="radio" name="payment_method" value="banktransfer" checked>
                            <div class="payment-option-name">Bank Transfer</div>
                        </label>
                        <label class="payment-option">
                            <input type="radio" name="payment_method" value="paypal">
                            <div class="payment-option-name">PayPal</div>
                        </label>
                    @endif
                </div>
            </div>
        </div>

        {{-- Order Summary --}}
        <div>
            <div class="card" style="position:sticky; top:70px;">
                <div class="card-header">Order Summary</div>
                <div class="card-body">
                    @if(isset($cartItems))
                        @foreach($cartItems as $item)
                        <div class="order-row">
                            <span>{{ $item['name'] }}</span>
                            <span>${{ number_format($item['price'] ?? 0, 2) }}</span>
                        </div>
                        @endforeach
                    @endif
                    @php $total = isset($cartItems) ? collect($cartItems)->sum('price') : 0; @endphp
                    <div class="order-row" style="margin-top:4px;">
                        <span>Total</span>
                        <span style="color:#1a4d80; font-size:16px;">${{ number_format($total, 2) }}</span>
                    </div>
                    <button type="submit" class="btn btn-primary" style="width:100%; justify-content:center; margin-top:16px;">Place Order</button>
                    <p style="font-size:11px; color:#999; text-align:center; margin-top:8px;">By placing your order, you agree to our Terms of Service.</p>
                </div>
            </div>
        </div>
    </div>
</form>

@endsection
