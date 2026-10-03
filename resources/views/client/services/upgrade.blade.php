@extends('client.layouts.app')
@section('title', __('client.services.upgrade_downgrade_title'))
@section('content')

<div class="page-header">
    <h1>{{ __('client.services.upgrade_downgrade') }}</h1>
    <a href="{{ route('client.services.show', $service) }}" class="btn btn-outline btn-sm">&larr; {{ __('client.services.back_to_service') }}</a>
</div>

@if(!isset($upgrades) || $upgrades->isEmpty())
<div class="pn-card">
    <div class="pn-card-body" style="text-align:center; padding:40px; color:var(--muted);">
        <p style="margin:0 0 16px;">{{ __('client.services.no_upgrades') }}</p>
        <a href="{{ route('client.services.show', $service) }}" class="btn btn-outline btn-sm">&larr; {{ __('client.services.back_to_service') }}</a>
    </div>
</div>
@else
<div style="background:#d9edf7; border:1px solid #bce8f1; color:#31708f; padding:12px 16px; border-radius:4px; font-size:13px; margin-bottom:20px;">
    {{ __('client.services.currently_on') }}: <strong>{{ $service->product?->name ?? 'Service' }}</strong> &mdash; {{ display_money_fmt($service->amount) }}/{{ $service->billing_cycle }}
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
                <label style="display:flex; align-items:center; gap:12px; padding:14px; border:1px solid var(--border); border-radius:4px; cursor:pointer;">
                    <input type="radio" name="new_product_id" value="{{ $product->id }}" required style="margin:0;">
                    <div style="flex:1;">
                        <div style="font-weight:500; font-size:13px; color:#1a4d80;">{{ $product->name }}</div>
                        @if($product->description)
                        <div style="font-size:12px; color:var(--muted); margin-top:3px;">{{ Str::limit(strip_tags($product->description), 100) }}</div>
                        @endif
                    </div>
                    @if($price)
                    <div style="text-align:right; white-space:nowrap;">
                        <div style="font-weight:600; font-size:14px;">{{ display_money_fmt($price) }}</div>
                        <div style="font-size:11px; color:var(--muted);">{{ __('client.services.per_cycle', ['cycle' => $cycle]) }}</div>
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

@if(($optionGroups ?? collect())->flatMap->options->filter(fn ($o) => $o->subs->isNotEmpty())->isNotEmpty())
{{-- Raise the options on this same package: more RAM, a bigger disk. Charged for the days left in the cycle. --}}
<div class="pn-card" style="margin-top:16px;">
    <div class="pn-card-header">{{ __('client.services.options_title') }}</div>
    <div class="pn-card-body">
        <p style="font-size:13px; color:var(--muted); margin-top:0;">{{ __('client.services.options_hint') }}</p>
        <form method="POST" action="{{ route('client.services.options.upgrade', $service) }}">
            @csrf
            @foreach($optionGroups as $group)
                @foreach($group->options as $option)
                    @continue($option->subs->isEmpty())
                    <div class="form-group" style="margin-bottom:12px;">
                        <label class="form-label" for="uopt-{{ $option->id }}">{{ $option->displayName() }}</label>
                        @if($option->isQuantity())
                            <input type="number" id="uopt-{{ $option->id }}" name="config_options[{{ $option->id }}]" class="form-control" style="max-width:160px;"
                                   value="{{ $currentOptions[$option->id] ?? ($option->qty_minimum ?? 0) }}"
                                   min="{{ $currentOptions[$option->id] ?? ($option->qty_minimum ?? 0) }}" @if($option->qty_maximum) max="{{ $option->qty_maximum }}" @endif>
                            <small style="color:var(--muted);">{{ display_money_fmt($option->subs->first()?->priceFor($upgradeCycle) ?? 0) }} {{ __('client.cart.per_unit') }}</small>
                        @elseif($option->isCheckbox())
                            <label style="display:flex; align-items:center; gap:8px; font-size:13px;">
                                <input type="checkbox" id="uopt-{{ $option->id }}" name="config_options[{{ $option->id }}]" value="1" @checked(isset($currentOptions[$option->id]))>
                                <span>{{ $option->subs->first()?->displayName() ?? $option->displayName() }} (+{{ display_money_fmt($option->subs->first()?->priceFor($upgradeCycle) ?? 0) }})</span>
                            </label>
                        @else
                            <select id="uopt-{{ $option->id }}" name="config_options[{{ $option->id }}]" class="form-control" style="max-width:320px;">
                                @foreach($option->subs as $sub)
                                <option value="{{ $sub->id }}" @selected((string) ($currentOptions[$option->id] ?? '') === (string) $sub->id)>{{ $sub->displayName() }} · {{ display_money_fmt($sub->priceFor($upgradeCycle)) }}</option>
                                @endforeach
                            </select>
                        @endif
                    </div>
                @endforeach
            @endforeach
            <button type="submit" class="btn btn-primary btn-sm">{{ __('client.services.options_submit') }}</button>
        </form>
    </div>
</div>
@endif

@endsection
