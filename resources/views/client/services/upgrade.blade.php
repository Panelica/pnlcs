@extends('client.layouts.app')
@section('title', __('client.services.upgrade_downgrade_title'))
@section('content')

<div class="page-header">
    <h1>{{ __('client.services.upgrade_downgrade') }}</h1>
    <a href="{{ route('client.services.show', $service) }}" class="btn btn-outline btn-sm">&larr; {{ __('client.services.back_to_service') }}</a>
</div>

@if(!isset($upgrades) || $upgrades->isEmpty())
<div class="pn-card">
    <div class="pn-card-body" style="text-align:center; padding:40px; color:#999;">
        <p style="margin:0 0 16px;">{{ __('client.services.no_upgrades') }}</p>
        <a href="{{ route('client.services.show', $service) }}" class="btn btn-outline btn-sm">&larr; {{ __('client.services.back_to_service') }}</a>
    </div>
</div>
@else
<div style="background:#d9edf7; border:1px solid #bce8f1; color:#31708f; padding:12px 16px; border-radius:4px; font-size:13px; margin-bottom:20px;">
    {{ __('client.services.currently_on') }}: <strong>{{ $service->product?->name ?? 'Service' }}</strong> &mdash; {{ money_fmt($service->amount) }}/{{ $service->billing_cycle }}
</div>

<div class="pn-card">
    <div class="pn-card-header">{{ __('client.services.select_new_plan') }}</div>
    <div class="pn-card-body">
        <form method="POST" action="{{ route('client.services.upgrade.process', $service) }}">
            @csrf
            @if($errors->any())
            <div style="background:#f2dede;border:1px solid #ebccd1;color:#a94442;padding:10px 14px;border-radius:4px;font-size:13px;margin-bottom:16px;">
                <ul style="margin:0; padding-left:18px;">
                    @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
                </ul>
            </div>
            @endif
            <div style="display:flex; flex-direction:column; gap:10px; margin-bottom:20px;">
                @foreach($upgrades as $product)
                @php
                    // The price for the term this customer is on, worked out
                    // where the customer is known rather than guessed at here.
                    $price = $upgradePrices[$product->id] ?? null;
                    $cycle = strtolower($upgradeCycle ?? '');
                @endphp
                <label style="display:flex; align-items:center; gap:12px; padding:14px; border:1px solid #ddd; border-radius:4px; cursor:pointer;">
                    <input type="radio" name="new_product_id" value="{{ $product->id }}" required style="margin:0;">
                    <div style="flex:1;">
                        <div style="font-weight:500; font-size:13px; color:#1a4d80;">{{ $product->name }}</div>
                        @if($product->description)
                        <div style="font-size:12px; color:#777; margin-top:3px;">{{ Str::limit(strip_tags($product->description), 100) }}</div>
                        @endif
                    </div>
                    @if($price)
                    <div style="text-align:right; white-space:nowrap;">
                        <div style="font-weight:600; font-size:14px;">{{ money_fmt($price) }}</div>
                        <div style="font-size:11px; color:#999;">{{ __('client.services.per_cycle', ['cycle' => $cycle]) }}</div>
                    </div>
                    @endif
                </label>
                @endforeach
            </div>
            <div style="display:flex; gap:8px;">
                <button type="submit" class="btn btn-primary">{{ __('client.services.request_change') }}</button>
                <a href="{{ route('client.services.show', $service) }}" class="btn btn-outline">{{ __('common.actions.cancel') }}</a>
            </div>
        </form>
    </div>
</div>
@endif

@endsection
