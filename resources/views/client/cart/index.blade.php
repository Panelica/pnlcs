@extends('client.layouts.app')
@section('title', 'Shopping Cart')
@section('content')

<div class="page-header">
    <h1>Shopping Cart</h1>
    <a href="{{ route('client.store') }}" class="btn btn-default btn-sm">+ Add More</a>
</div>

@if(session('success'))
<div style="background:#dff0d8;border:1px solid #d6e9c6;color:#3c763d;padding:10px 14px;border-radius:4px;font-size:13px;margin-bottom:16px;">{{ session('success') }}</div>
@endif
@if(session('error'))
<div style="background:#f2dede;border:1px solid #ebccd1;color:#a94442;padding:10px 14px;border-radius:4px;font-size:13px;margin-bottom:16px;">{{ session('error') }}</div>
@endif

@if(empty($totals['items']))
<div class="card">
    <div class="card-body" style="text-align:center; padding:48px; color:#999;">
        <div style="font-size:36px; margin-bottom:12px;">&#128722;</div>
        <p style="margin:0 0 16px;">Your cart is empty.</p>
        <a href="{{ route('client.store') }}" class="btn btn-primary">Browse Products</a>
    </div>
</div>
@else
<div style="display:grid; grid-template-columns:1fr 300px; gap:20px;">
    <div>
        <div class="card">
            <div class="card-body" style="padding:0;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Billing Cycle</th>
                            <th style="text-align:right;">Price</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($totals['items'] as $key => $item)
                        <tr>
                            <td>
                                <div style="font-weight:500;">{{ $item['product_name'] ?? $item['name'] ?? 'Product' }}</div>
                                @if(!empty($item['domain']))<div style="font-size:12px; color:#777;">{{ $item['domain'] }}</div>@endif
                            </td>
                            <td style="color:#777; text-transform:capitalize;">{{ $item['billing_cycle'] ?? '-' }}</td>
                            <td style="text-align:right; font-weight:500;">${{ number_format($item['price'] ?? 0, 2) }}</td>
                            <td>
                                <form method="POST" action="{{ route('client.cart.remove', $key) }}" style="display:inline;">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-xs">Remove</button>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Promo Code --}}
        <div class="card" style="margin-top:16px;">
            <div class="card-header">Promo Code</div>
            <div class="card-body">
                <form method="POST" action="{{ route('client.cart.promo') }}" style="display:flex; gap:8px;">
                    @csrf
                    <input type="text" name="code" class="form-control" placeholder="Enter promo code"
                        value="{{ $totals['promo_code'] ?? '' }}" style="max-width:220px;">
                    <button type="submit" class="btn btn-default btn-sm">Apply</button>
                </form>
                @if($totals['promo_code'] ?? false)
                <div style="margin-top:8px; font-size:13px; color:#5cb85c;">Promo code "{{ $totals['promo_code'] }}" applied!</div>
                @endif
            </div>
        </div>
    </div>

    <div>
        <div class="card">
            <div class="card-header">Order Summary</div>
            <div class="card-body">
                <div style="display:flex; justify-content:space-between; font-size:13px; padding:6px 0; border-bottom:1px solid #eee;">
                    <span style="color:#777;">Subtotal</span>
                    <span>${{ number_format($totals['subtotal'], 2) }}</span>
                </div>
                @if(($totals['discount'] ?? 0) > 0)
                <div style="display:flex; justify-content:space-between; font-size:13px; padding:6px 0; border-bottom:1px solid #eee; color:#5cb85c;">
                    <span>Discount</span>
                    <span>-${{ number_format($totals['discount'], 2) }}</span>
                </div>
                @endif
                @if(($totals['tax'] ?? 0) > 0)
                <div style="display:flex; justify-content:space-between; font-size:13px; padding:6px 0; border-bottom:1px solid #eee;">
                    <span style="color:#777;">Tax ({{ $totals['tax_rate'] ?? 0 }}%)</span>
                    <span>${{ number_format($totals['tax'], 2) }}</span>
                </div>
                @endif
                <div style="display:flex; justify-content:space-between; font-size:15px; font-weight:600; padding:10px 0 6px; margin-top:4px;">
                    <span>Total</span>
                    <span style="color:#1a4d80;">${{ number_format($totals['total'], 2) }}</span>
                </div>
                <a href="{{ route('client.cart.checkout') }}" class="btn btn-primary" style="width:100%; justify-content:center; text-align:center; display:block; margin-top:10px;">Checkout &rarr;</a>
            </div>
        </div>
    </div>
</div>
@endif

@endsection
