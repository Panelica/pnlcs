@extends('admin.layouts.app')
@section('title', __('admin.orders.new_title'))
@section('content')
<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;">
    <h1>{{ __('admin.orders.new_title') }}</h1>
    <a href="{{ route('admin.orders.index') }}" class="btn btn-default btn-sm">&larr; {{ __('admin.orders.title') }}</a>
</div>

@if($errors->any())
<div class="alert alert-danger" style="margin-bottom:15px;">
    <ul style="margin:0;padding-left:18px;">
        @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
    </ul>
</div>
@endif

@php
    $acceptOld = (bool) old('accept', false);
@endphp
<div class="card" style="max-width:760px;">
    <form method="POST" action="{{ route('admin.orders.store') }}">
        @csrf
        <div class="card-body">
            <div class="form-group">
                <label class="form-label" for="o-client">{{ __('admin.orders.new_client') }}</label>
                @if($client)
                <div style="font-weight:600;margin-bottom:4px;">{{ $client->full_name }} <span style="font-weight:400;color:#777;">{{ $client->email }}</span></div>
                <input type="hidden" name="client" value="{{ $client->id }}">
                @else
                <input type="text" id="o-client" name="client" value="{{ old('client') }}" required class="form-control">
                <p style="font-size:12px;color:#777;margin-top:4px;">{{ __('admin.tickets.open_client_hint') }}</p>
                @endif
            </div>
            <div class="form-group">
                <label class="form-label" for="o-product">{{ __('admin.orders.new_product') }}</label>
                <select id="o-product" name="product_id" required class="form-control">
                    @foreach($products->groupBy(fn ($p) => $p->group?->name ?? '') as $groupName => $groupProducts)
                    <optgroup label="{{ $groupName }}">
                        @foreach($groupProducts as $p)
                        <option value="{{ $p->id }}" @selected(old('product_id') == $p->id)>{{ $p->name }}</option>
                        @endforeach
                    </optgroup>
                    @endforeach
                </select>
            </div>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;">
                <div class="form-group">
                    <label class="form-label" for="o-cycle">{{ __('admin.orders.new_cycle') }}</label>
                    <select id="o-cycle" name="billing_cycle" required class="form-control">
                        @foreach($cycles as $c)
                        <option value="{{ $c }}" @selected(old('billing_cycle', 'monthly') === $c)>{{ __('common.billing.'.str_replace('semiannually', 'semi_annually', $c)) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="o-price">{{ __('admin.orders.new_price') }}</label>
                    <input type="number" id="o-price" name="price" value="{{ old('price') }}" step="0.01" min="0" class="form-control">
                    <p style="font-size:12px;color:#777;margin-top:4px;">{{ __('admin.orders.new_price_hint') }}</p>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label" for="o-domain">{{ __('admin.orders.new_domain') }}</label>
                <input type="text" id="o-domain" name="domain" value="{{ old('domain') }}" maxlength="255" class="form-control" autocomplete="off" spellcheck="false">
            </div>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;">
                <div class="form-group">
                    <label class="form-label" for="o-gateway">{{ __('admin.orders.payment_method') }}</label>
                    <select id="o-gateway" name="payment_method" required class="form-control">
                        @foreach($gateways as $g)
                        <option value="{{ $g }}" @selected(old('payment_method') === $g)>{{ $g }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="o-promo">{{ __('admin.orders.promo_code') }}</label>
                    <input type="text" id="o-promo" name="promo_code" value="{{ old('promo_code') }}" maxlength="255" class="form-control">
                </div>
            </div>
            <div class="form-group">
                <input type="hidden" name="accept" value="0">
                <label style="display:flex;gap:8px;align-items:flex-start;font-weight:400;">
                    <input type="checkbox" name="accept" value="1" @checked($acceptOld) style="margin-top:3px;">
                    <span>{{ __('admin.orders.new_accept') }}</span>
                </label>
            </div>
        </div>
        <div style="padding:12px 20px;border-top:1px solid #e5e5e5;display:flex;gap:8px;justify-content:flex-end;">
            <a href="{{ route('admin.orders.index') }}" class="btn btn-default btn-sm">{{ __('common.actions.cancel') }}</a>
            <button type="submit" class="btn btn-primary btn-sm">{{ __('admin.orders.new_submit') }}</button>
        </div>
    </form>
</div>
@endsection
