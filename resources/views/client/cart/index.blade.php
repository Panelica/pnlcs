@extends('client.layouts.app')
@section('title', 'Shopping Cart')
@section('content')

<div class="page-header">
    <h1>Shopping Cart</h1>
    <a href="{{ route('client.store') }}" class="btn btn-default btn-sm">+ Add More</a>
</div>

@if(empty($cartItems))
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
                        @foreach($cartItems as $key => $item)
                        <tr>
                            <td>
                                <div style="font-weight:500;">{{ $item['name'] }}</div>
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
    </div>
    <div>
        <div class="card">
            <div class="card-header">Order Summary</div>
            <div class="card-body">
                @php $total = collect($cartItems)->sum('price'); @endphp
                <div style="display:flex; justify-content:space-between; font-size:13px; padding:6px 0; border-bottom:1px solid #eee; margin-bottom:8px;">
                    <span style="color:#777;">Subtotal</span>
                    <span>${{ number_format($total, 2) }}</span>
                </div>
                <div style="display:flex; justify-content:space-between; font-size:15px; font-weight:600; padding:6px 0; margin-bottom:16px;">
                    <span>Total</span>
                    <span style="color:#1a4d80;">${{ number_format($total, 2) }}</span>
                </div>
                <a href="{{ route('client.cart.checkout') }}" class="btn btn-primary" style="width:100%; justify-content:center;">Checkout &rarr;</a>
            </div>
        </div>
    </div>
</div>
@endif

@endsection
