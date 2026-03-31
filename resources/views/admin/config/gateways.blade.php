@extends('admin.layouts.app')
@section('title', 'Payment Gateways')
@section('content')

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;">
    <h1>Payment Gateways</h1>
</div>

<p style="color:#666;font-size:13px;margin-bottom:15px;">Configure payment gateway modules for accepting payments from customers.</p>

@php
$modules = [
    ['name' => 'banktransfer', 'label' => 'Bank Transfer', 'icon' => 'fas fa-university', 'desc' => 'Accept payments via manual bank wire transfer.'],
    ['name' => 'paypal', 'label' => 'PayPal', 'icon' => 'fab fa-paypal', 'desc' => 'Accept payments via PayPal standard checkout.'],
    ['name' => 'stripe', 'label' => 'Stripe', 'icon' => 'fab fa-stripe-s', 'desc' => 'Accept credit/debit card payments via Stripe.'],
    ['name' => 'razorpay', 'label' => 'Razorpay', 'icon' => 'fas fa-rupee-sign', 'desc' => 'Accept payments via Razorpay (India).'],
    ['name' => 'mollie', 'label' => 'Mollie', 'icon' => 'fas fa-euro-sign', 'desc' => 'Accept payments via Mollie (Europe).'],
    ['name' => 'authorize', 'label' => 'Authorize.net', 'icon' => 'fas fa-credit-card', 'desc' => 'Accept credit card payments via Authorize.net.'],
];
@endphp

<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:15px;">
@foreach($modules as $gw)
    @php
        $settings = \App\Models\GatewaySettings::where('gateway', $gw['name'])->pluck('value', 'setting')->toArray() ?? [];
        $isActive = !empty($settings['active']) && $settings['active'] === '1';
    @endphp
    <div class="card">
        <div class="card-header" style="display:flex;align-items:center;gap:10px;">
            <i class="{{ $gw['icon'] }}" style="font-size:18px;color:#337ab7;width:24px;text-align:center;"></i>
            <span style="flex:1;font-weight:600;">{{ $gw['label'] }}</span>
            <span class="badge {{ $isActive ? 'badge-active' : 'badge-cancelled' }}">{{ $isActive ? 'Active' : 'Inactive' }}</span>
        </div>
        <div class="card-body">
            <p style="font-size:12px;color:#777;margin-bottom:12px;">{{ $gw['desc'] }}</p>
            <form method="POST" action="{{ route('admin.config.gateways.settings.update', $gw['name']) }}">
                @csrf
                <div class="form-group">
                    <label style="font-size:13px;display:flex;align-items:center;gap:6px;cursor:pointer;">
                        <input type="checkbox" name="active" value="1" {{ $isActive ? 'checked' : '' }}> Enable this gateway
                    </label>
                </div>
                @if($gw['name'] === 'banktransfer')
                <div class="form-group"><label class="form-label">Bank Name</label><input type="text" name="bank_name" value="{{ $settings['bank_name'] ?? '' }}" class="form-control" placeholder="e.g. First National Bank"></div>
                <div class="form-group"><label class="form-label">Account Name</label><input type="text" name="account_name" value="{{ $settings['account_name'] ?? '' }}" class="form-control"></div>
                <div class="form-group"><label class="form-label">Account Number / IBAN</label><input type="text" name="account_number" value="{{ $settings['account_number'] ?? '' }}" class="form-control"></div>
                <div class="form-group"><label class="form-label">Sort Code / SWIFT</label><input type="text" name="sort_code" value="{{ $settings['sort_code'] ?? '' }}" class="form-control"></div>
                @elseif($gw['name'] === 'paypal')
                <div class="form-group"><label class="form-label">PayPal Email</label><input type="email" name="email" value="{{ $settings['email'] ?? '' }}" class="form-control" placeholder="paypal@example.com"></div>
                <div class="form-group"><label class="form-label">Sandbox Mode</label><select name="sandbox" class="form-control"><option value="0" {{ ($settings['sandbox'] ?? '0') === '0' ? 'selected' : '' }}>Live</option><option value="1" {{ ($settings['sandbox'] ?? '0') === '1' ? 'selected' : '' }}>Sandbox</option></select></div>
                @elseif($gw['name'] === 'stripe')
                <div class="form-group"><label class="form-label">Publishable Key</label><input type="text" name="publishable_key" value="{{ $settings['publishable_key'] ?? '' }}" class="form-control" placeholder="pk_live_..."></div>
                <div class="form-group"><label class="form-label">Secret Key</label><input type="password" name="secret_key" value="{{ $settings['secret_key'] ?? '' }}" class="form-control" placeholder="sk_live_..."></div>
                @elseif($gw['name'] === 'authorize')
                <div class="form-group"><label class="form-label">API Login ID</label><input type="text" name="api_login" value="{{ $settings['api_login'] ?? '' }}" class="form-control"></div>
                <div class="form-group"><label class="form-label">Transaction Key</label><input type="password" name="transaction_key" value="{{ $settings['transaction_key'] ?? '' }}" class="form-control"></div>
                @else
                <div class="form-group"><label class="form-label">API Key</label><input type="text" name="api_key" value="{{ $settings['api_key'] ?? '' }}" class="form-control"></div>
                <div class="form-group"><label class="form-label">API Secret</label><input type="password" name="api_secret" value="{{ $settings['api_secret'] ?? '' }}" class="form-control"></div>
                @endif
                <button type="submit" class="btn btn-primary btn-sm" style="margin-top:8px;">Save Settings</button>
            </form>
        </div>
    </div>
@endforeach
</div>
@endsection
