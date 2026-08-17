@extends('client.layouts.app')
@section('title', __('client.cart.configure') . ': '. $product->name)
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
    <h1>{{ __('client.cart.configure') }}: {{ $product->name }}</h1>
    <a href="{{ route('client.store') }}" class="btn btn-outline btn-sm">&larr; {{ __('client.actions.back') }}</a>
</div>

<form method="POST" action="{{ route('client.cart.add') }}" id="configForm">
    @csrf
    <input type="hidden" name="product_id" value="{{ $product->id }}">

    @if(! empty($apps))
    {{-- One product, any app. Selling ninety-eight apps as ninety-eight
         products does not scale, so the choice is made here and the order
         installs it. --}}
    <div class="pn-card" style="margin-bottom:16px;">
        <div class="pn-card-header">{{ __('client.cart.choose_app') }}</div>
        <div class="pn-card-body">
            <div class="ap-search">
                <input type="text" id="ap-q" class="form-control" autocomplete="off"
                       placeholder="{{ __('client.cart.app_search_ph') }}" oninput="apFilter()"
                       onkeydown="if(event.key==='Enter')event.preventDefault()">
                <span class="ap-count" id="ap-count">{{ __('client.cart.app_count', ['count' => count($apps)]) }}</span>
            </div>
            <input type="hidden" name="app_slug" id="ap-slug" value="{{ old('app_slug') }}">
            <div class="ap-grid">
                @foreach($apps as $a)
                @php
                    $hue = crc32($a['slug']) % 360;
                    $initial = mb_strtoupper(mb_substr(trim($a['name']) ?: $a['slug'], 0, 1));
                    $line = $a['tagline'] ?: $a['description'];
                @endphp
                <div class="ap-app{{ old('app_slug') === $a['slug'] ? ' on' : '' }}" data-slug="{{ $a['slug'] }}"
                     data-find="{{ mb_strtolower($a['name'].' '.$a['slug'].' '.$line.' '.implode(' ', $a['categories'] ?? [])) }}"
                     onclick="apPick(this)" title="{{ $line }}">
                    @if($a['is_featured'])<span class="ap-star" title="{{ __('client.cart.app_featured') }}">&#9733;</span>@endif
                    @if($a['logo_url_local'])
                        <img src="{{ $a['logo_url_local'] }}" alt="" loading="lazy" class="ap-logo">
                    @else
                        <div class="ap-mark" style="background:hsl({{ $hue }},62%,94%);color:hsl({{ $hue }},52%,34%);border-color:hsl({{ $hue }},45%,84%)">{{ $initial }}</div>
                    @endif
                    <div class="ap-nm">{{ $a['name'] }}</div>
                    <div class="ap-ds">{{ $line }}</div>
                    @if(($a['extra_services'] ?? 0) > 0 || ($a['min_memory_mb'] ?? 0) > 0)
                    <div class="ap-req">
                        @if(($a['min_memory_mb'] ?? 0) > 0)<span>{{ $a['min_memory_mb'] >= 1024 ? round($a['min_memory_mb']/1024, 1).' GB' : $a['min_memory_mb'].' MB' }}</span>@endif
                        @if(($a['extra_services'] ?? 0) > 0)<span>{{ trans_choice('client.hosting.containers.services', $a['extra_services'] + 1, ['count' => $a['extra_services'] + 1]) }}</span>@endif
                    </div>
                    @endif
                </div>
                @endforeach
            </div>
            <div class="ap-none" id="ap-none" hidden>{{ __('client.cart.app_search_none') }}</div>
            @error('app_slug')<div class="text-danger" style="font-size:12px;margin-top:8px">{{ $message }}</div>@enderror
        </div>
    </div>
    @endif

    <div class="config-layout">
        <div>
            {{-- Billing Cycle --}}
            <div class="pn-card" style="margin-bottom:16px;">
                <div class="pn-card-header">{{ __('client.cart.billing_cycle') }}</div>
                <div class="pn-card-body">
                    @php $pricedCycles = $product->pricedCycles($currency?->id); @endphp
                    <div class="cycle-options">
                        @if($pricedCycles !== [])
                            {{-- Only the cycles the product is sold on, so the first one
                                 really is the one that comes up selected. --}}
                            @foreach($pricedCycles as $cycle => $cyclePrice)
                                <label class="cycle-option {{ $loop->first ? 'selected' : '' }}">
                                    <input type="radio" name="billing_cycle" value="{{ $cycle }}" {{ $loop->first ? 'checked' : '' }}
                                        onchange="document.querySelectorAll('.cycle-option').forEach(e=>e.classList.remove('selected')); this.closest('.cycle-option').classList.add('selected')">
                                    <div class="cycle-price">{{ $currency?->prefix }}{{ number_format($cyclePrice, 2) }}{{ $currency?->suffix }}</div>
                                    <div class="cycle-label">{{ $cycle }}</div>
                                </label>
                            @endforeach
                        @else
                            <div style="color:#999; font-size:13px; grid-column:1/-1;">{{ __('client.cart.no_pricing') }}</div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Configurable Options --}}
            @if(($optionGroups ?? collect())->isNotEmpty())
            <div class="pn-card" style="margin-bottom:16px;">
                <div class="pn-card-header">{{ __('client.cart.configurable_options') }}</div>
                <div class="pn-card-body">
                    @foreach($optionGroups as $group)
                        @foreach($group->options as $option)
                            @continue($option->subs->isEmpty())
                            @php
                                $selectedCycle = old('billing_cycle', (string) (array_key_first($pricedCycles) ?: 'monthly'));
                                $previous = old('config_options.'.$option->id);
                            @endphp
                            <div class="form-group" style="margin-bottom:14px;">
                                <label class="form-label" for="opt-{{ $option->id }}">{{ $option->option_name }}</label>

                                @if($option->isQuantity())
                                    @php $unit = $option->subs->first(); @endphp
                                    <input type="number" id="opt-{{ $option->id }}"
                                           name="config_options[{{ $option->id }}]"
                                           class="form-control config-option"
                                           data-unit-price="{{ $unit?->priceFor($selectedCycle) ?? 0 }}"
                                           value="{{ $previous ?? $option->qty_minimum ?? 0 }}"
                                           min="{{ $option->qty_minimum ?? 0 }}"
                                           @if($option->qty_maximum) max="{{ $option->qty_maximum }}" @endif>
                                    <small style="color:#777;">
                                        {{ $currency?->prefix }}{{ number_format($unit?->priceFor($selectedCycle) ?? 0, 2) }}
                                        {{ __('client.cart.per_unit') }}
                                    </small>

                                @elseif($option->isCheckbox())
                                    @php $sub = $option->subs->first(); @endphp
                                    <label style="display:flex; align-items:center; gap:8px; font-size:13px; cursor:pointer;">
                                        <input type="checkbox" id="opt-{{ $option->id }}"
                                               name="config_options[{{ $option->id }}]" value="1"
                                               class="config-option"
                                               data-unit-price="{{ $sub?->priceFor($selectedCycle) ?? 0 }}"
                                               @checked($previous)>
                                        <span>{{ $sub?->option_name ?? $option->option_name }}
                                            @if(($sub?->priceFor($selectedCycle) ?? 0) > 0)
                                                (+{{ $currency?->prefix }}{{ number_format($sub->priceFor($selectedCycle), 2) }})
                                            @endif
                                        </span>
                                    </label>

                                @else
                                    <select id="opt-{{ $option->id }}"
                                            name="config_options[{{ $option->id }}]"
                                            class="form-control config-option" required>
                                        @foreach($option->subs as $sub)
                                            @php $price = $sub->priceFor($selectedCycle); @endphp
                                            <option value="{{ $sub->id }}"
                                                    data-unit-price="{{ $price }}"
                                                    @selected((string) $previous === (string) $sub->id)>
                                                {{ $sub->option_name }}@if($price > 0) (+{{ $currency?->prefix }}{{ number_format($price, 2) }})@endif
                                            </option>
                                        @endforeach
                                    </select>
                                @endif

                                @error('config_options.'.$option->id)
                                    <div style="color:#c0392b; font-size:12px; margin-top:4px;">{{ $message }}</div>
                                @enderror
                            </div>
                        @endforeach
                    @endforeach
                </div>
            </div>
            @endif


            {{-- Addons --}}
            @if(($addons ?? collect())->isNotEmpty())
            <div class="pn-card" style="margin-bottom:16px;">
                <div class="pn-card-header">{{ __('client.cart.addons') }}</div>
                <div class="pn-card-body">
                    @php $selectedCycle = old('billing_cycle', (string) (array_key_first($pricedCycles) ?: 'monthly')); @endphp
                    @php $previousAddons = array_map('intval', (array) old('addons', [])); @endphp
                    @foreach($addons as $addon)
                        @php $addonPrice = $addon->priceFor($selectedCycle); @endphp
                        <label style="display:flex; align-items:flex-start; gap:8px; margin-bottom:10px; cursor:pointer;">
                            <input type="checkbox" name="addons[]" value="{{ $addon->id }}"
                                   class="cart-addon" data-unit-price="{{ $addonPrice }}"
                                   @checked(in_array($addon->id, $previousAddons, true))>
                            <span>
                                <strong>{{ $addon->name }}</strong>
                                @if($addonPrice > 0)
                                    <span style="color:#777;">(+{{ $currency?->prefix }}{{ number_format($addonPrice, 2) }})</span>
                                @endif
                                @if($addon->description)
                                    <br><small style="color:#777;">{{ $addon->description }}</small>
                                @endif
                            </span>
                        </label>
                    @endforeach
                </div>
            </div>
            @endif
            {{-- Domain --}}
            @if($product->show_domain_options)
            <div class="pn-card" style="margin-bottom:16px;">
                <div class="pn-card-header">{{ __('client.cart.domain') }}</div>
                <div class="pn-card-body">
                    <div style="display:flex; gap:8px; margin-bottom:12px;">
                        <label style="display:flex; align-items:center; gap:6px; font-size:13px; cursor:pointer;">
                            <input type="radio" name="domain_option" value="register" checked> {{ __('client.cart.register_new_domain') }}
                        </label>
                        <label style="display:flex; align-items:center; gap:6px; font-size:13px; cursor:pointer;">
                            <input type="radio" name="domain_option" value="transfer"> {{ __('client.cart.transfer_existing') }}
                        </label>
                        <label style="display:flex; align-items:center; gap:6px; font-size:13px; cursor:pointer;">
                            <input type="radio" name="domain_option" value="own"> {{ __('client.cart.use_own_domain') }}
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
                <div class="pn-card-header">{{ __('client.cart.additional_notes') }} <span style="font-weight:400; color:#999;">({{ __('client.form.optional') }})</span></div>
                <div class="pn-card-body">
                    <textarea name="notes" rows="3" class="form-control" placeholder="{{ __('client.cart.special_requirements') }}">{{ old('notes') }}</textarea>
                </div>
            </div>
        </div>

        {{-- Order Summary --}}
        <div class="order-summary">
            <div class="pn-card">
                <div class="pn-card-header">{{ __('client.cart.order_summary') }}</div>
                <div class="pn-card-body">
                    <div class="summary-row">
                        <span style="color:#777;">{{ __('client.cart.product') }}</span>
                        <span style="font-weight:500;">{{ $product->name }}</span>
                    </div>
                    <div class="summary-row">
                        <span style="color:#777;">{{ __('client.cart.billing_cycle') }}</span>
                        <span id="summaryBilling">&mdash;</span>
                    </div>
                    <div class="summary-row">
                        <span>{{ __('client.cart.total') }}</span>
                        <span id="summaryTotal" style="color:#1a4d80;">&mdash;</span>
                    </div>
                    <button type="submit" class="btn btn-primary" style="width:100%; margin-top:14px; justify-content:center;">{{ __('client.cart.add_to_cart') }} &rarr;</button>
                </div>
            </div>
        </div>
    </div>
</form>

@section('scripts')
<script>
document.querySelectorAll('input[name=billing_cycle]').forEach(function(radio) {
    radio.addEventListener('change', function() {
        var labels = { monthly: '{{ __("client.cart.cycle_monthly") }}', quarterly: '{{ __("client.cart.cycle_quarterly") }}', semiannually: '{{ __("client.cart.cycle_semiannually") }}', annually: '{{ __("client.cart.cycle_annually") }}', biennially: '{{ __("client.cart.cycle_biennially") }}', triennially: '{{ __("client.cart.cycle_triennially") }}' };
        document.getElementById('summaryBilling').textContent = labels[this.value] || this.value;
        var label = this.closest('.cycle-option');
        var price = label ? label.querySelector('.cycle-price').textContent : '-';
        document.getElementById('summaryTotal').textContent = price;
    });
});
var first = document.querySelector('input[name=billing_cycle]:checked');
if (first) { first.dispatchEvent(new Event('change')); }

function apPick(el){
    document.querySelectorAll('.ap-app').forEach(function(a){ a.classList.remove('on'); });
    el.classList.add('on');
    document.getElementById('ap-slug').value = el.getAttribute('data-slug') || '';
}
function apFilter(){
    var q = (document.getElementById('ap-q').value || '').toLowerCase().trim();
    var terms = q ? q.split(/\s+/) : [];
    var shown = 0;
    document.querySelectorAll('.ap-app').forEach(function(a){
        var hay = a.getAttribute('data-find') || '';
        var ok = terms.every(function(t){ return hay.indexOf(t) !== -1; });
        a.hidden = !ok;
        if (ok) shown++;
    });
    document.getElementById('ap-none').hidden = shown !== 0;
    var c = document.getElementById('ap-count');
    c.textContent = c.getAttribute('data-tpl').replace(':count', shown);
}
document.addEventListener('DOMContentLoaded', function(){
    var c = document.getElementById('ap-count');
    if (c) c.setAttribute('data-tpl', @json(__('client.cart.app_count', ['count' => ':count'])));
});
</script>
<style>
    .ap-search{display:flex;align-items:center;gap:10px;margin-bottom:12px}
    .ap-count{font-size:11.5px;color:var(--muted);white-space:nowrap}
    .ap-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:10px}
    .ap-app{border:1px solid var(--border);border-radius:11px;padding:12px 10px;text-align:center;cursor:pointer;
        background:var(--bg);transition:transform .14s,border-color .14s;position:relative}
    .ap-app:hover{transform:translateY(-2px);border-color:var(--primary)}
    .ap-app.on{border-color:var(--primary);background:var(--primary-light);box-shadow:0 0 0 1px var(--primary) inset}
    .ap-logo,.ap-mark{width:38px;height:38px;margin:0 auto 8px;display:block;object-fit:contain}
    .ap-mark{border-radius:10px;border:1px solid;display:flex;align-items:center;justify-content:center;font-size:17px;font-weight:800}
    .ap-nm{font-size:12.5px;font-weight:700;line-height:1.25;color:var(--text)}
    .ap-ds{font-size:10.5px;color:var(--muted);margin-top:3px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    .ap-star{position:absolute;top:6px;right:7px;color:#f0a92b;font-size:11px;line-height:1}
    .ap-req{display:flex;justify-content:center;gap:5px;flex-wrap:wrap;margin-top:6px}
    .ap-req span{font-size:10px;font-weight:600;color:var(--muted);background:rgba(127,127,127,.09);border-radius:5px;padding:2px 5px}
    .ap-none{padding:18px;text-align:center;color:var(--muted);font-size:13px}
</style>
@endsection

@endsection
