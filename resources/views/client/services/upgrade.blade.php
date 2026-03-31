@extends('client.layouts.app')
@section('title', 'Upgrade / Downgrade Service')
@section('content')

<div class="page-header">
    <h1>Upgrade / Downgrade</h1>
    <a href="{{ route('client.services.show', $service) }}" class="btn btn-default btn-sm">&larr; Back to Service</a>
</div>

@if(!isset($upgrades) || $upgrades->isEmpty())
<div class="card">
    <div class="card-body" style="text-align:center; padding:40px; color:#999;">
        <p style="margin:0 0 16px;">No upgrade options are available for this service at this time.</p>
        <a href="{{ route('client.services.show', $service) }}" class="btn btn-default btn-sm">&larr; Back to Service</a>
    </div>
</div>
@else
<div style="background:#d9edf7; border:1px solid #bce8f1; color:#31708f; padding:12px 16px; border-radius:4px; font-size:13px; margin-bottom:20px;">
    Currently on: <strong>{{ $service->product->name ?? 'Service' }}</strong> &mdash; ${{ number_format($service->amount, 2) }}/{{ $service->billing_cycle }}
</div>

<div class="card">
    <div class="card-header">Select a New Plan</div>
    <div class="card-body">
        <form method="POST" action="{{ route('client.services.upgrade.submit', $service) }}">
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
                    $pr = $product->pricing->first();
                    $cycles = ['monthly','quarterly','semiannually','annually'];
                    $price = null;
                    $cycle = ;
                    if ($pr) {
                        foreach ($cycles as $c) {
                            if (isset($pr->{$c}) && (float)$pr->{$c} > 0) { $price = $pr->{$c}; $cycle = $c; break; }
                        }
                    }
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
                        <div style="font-weight:600; font-size:14px;">${{ number_format($price, 2) }}</div>
                        <div style="font-size:11px; color:#999;">per {{ $cycle }}</div>
                    </div>
                    @endif
                </label>
                @endforeach
            </div>
            <div style="display:flex; gap:8px;">
                <button type="submit" class="btn btn-primary">Request Change</button>
                <a href="{{ route('client.services.show', $service) }}" class="btn btn-default">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endif

@endsection
