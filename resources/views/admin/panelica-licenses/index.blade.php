@extends('admin.layouts.app')
@section('title', __('panelica_license.page.title'))
@section('content')
@php
    $money = fn ($amount) => '$'.number_format((float) $amount, 2);
    $local = fn ($amount) => $currency ? $currency->formatAmount((float) $amount) : number_format((float) $amount, 2);
    $muted = 'color:#777;';
    $th = 'padding:8px 10px;text-align:left;font-size:12px;color:#777;border-bottom:1px solid #e5e7eb;white-space:nowrap;';
    $td = 'padding:9px 10px;border-bottom:1px solid #f0f0f0;font-size:13px;vertical-align:middle;';
@endphp
<style>
    .pl-hero{display:grid;grid-template-columns:minmax(0,1.3fr) minmax(0,1fr);gap:22px;align-items:start;background:linear-gradient(135deg,#1a4d80 0%,#2563a8 100%);color:#fff;border-radius:10px;padding:26px 28px;margin-bottom:16px}
    .pl-hero h2{margin:0 0 8px;font-size:22px;font-weight:700;color:#fff}
    .pl-hero p{margin:0 0 14px;font-size:14px;line-height:1.55;opacity:.92}
    .pl-steps3{list-style:none;margin:0;padding:0;display:grid;gap:10px}
    .pl-steps3 li{display:flex;gap:10px;align-items:flex-start;font-size:13.5px;line-height:1.45}
    .pl-steps3 b{flex:0 0 24px;height:24px;border-radius:50%;background:rgba(255,255,255,.18);display:flex;align-items:center;justify-content:center;font-size:12px}
    .pl-connect{background:#fff;color:#333;border-radius:8px;padding:18px}
    .pl-connect label{font-size:12px;font-weight:600;color:#555;display:block;margin-bottom:4px}
    .pl-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px;margin-bottom:16px}
    .pl-stat{background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:14px 16px}
    .pl-stat .k{font-size:11.5px;font-weight:700;color:#777;text-transform:uppercase;letter-spacing:.3px}
    .pl-stat .v{font-size:22px;font-weight:700;color:#1a4d80;margin-top:4px}
    .pl-stat .s{font-size:12px;color:#777;margin-top:2px}
    .pl-badge{display:inline-block;padding:2px 9px;border-radius:999px;font-size:11.5px;font-weight:600;background:#eef2f7;color:#555}
    .pl-badge.ok{background:#e7f6ec;color:#1e7b3a}.pl-badge.wait{background:#fff4e0;color:#9a5b00}.pl-badge.bad{background:#fdecea;color:#a12a1f}
    .pl-price{width:96px;font-size:12.5px;padding:4px 6px}
    @media (max-width:900px){.pl-hero{grid-template-columns:1fr}}
</style>

<div class="page-header">
    <h1><i class="fas fa-key" style="color:#1a4d80;"></i> {{ __('panelica_license.page.title') }}</h1>
    @if($connected && $account)
    <div style="display:flex;gap:6px;flex-wrap:wrap;">
        <form method="POST" action="{{ route('admin.panelica-licenses.sync') }}">@csrf
            <button type="submit" class="btn btn-default btn-sm"><i class="fas fa-sync"></i> {{ __('panelica_license.page.sync') }}</button>
        </form>
        <a href="{{ $topUp }}" target="_blank" rel="noopener" class="btn btn-success btn-sm"><i class="fas fa-wallet"></i> {{ __('panelica_license.page.top_up') }}</a>
        <a href="{{ $portal }}" target="_blank" rel="noopener" class="btn btn-default btn-sm"><i class="fas fa-external-link-alt"></i> {{ __('panelica_license.page.portal') }}</a>
    </div>
    @endif
</div>

@if(! $connected || ! $account)
{{-- Not connected (or the key no longer works): what this is, and the key. --}}
<div class="pl-hero">
    <div>
        <h2>{{ __('panelica_license.page.hero_title') }}</h2>
        <p>{{ __('panelica_license.page.hero_text') }}</p>
        <ol class="pl-steps3">
            <li><b>1</b><span>{{ __('panelica_license.page.hero_step1') }}</span></li>
            <li><b>2</b><span>{{ __('panelica_license.page.hero_step2') }}</span></li>
            <li><b>3</b><span>{{ __('panelica_license.page.hero_step3') }}</span></li>
        </ol>
    </div>
    <div class="pl-connect">
        @if($accountError)
        <div style="margin-bottom:12px;padding:8px 10px;border-radius:5px;background:#fdecea;border:1px solid #f5c2c0;font-size:12.5px;color:#8a1f17;">{{ __('panelica_license.page.key_not_working', ['error' => $accountError]) }}</div>
        @endif
        <form method="POST" action="{{ route('admin.panelica-licenses.connect') }}">
            @csrf
            <div class="form-group" style="margin-bottom:10px;">
                <label for="pl-key">{{ __('panelica_license.page.api_key') }}</label>
                <input type="password" id="pl-key" name="api_key" required autocomplete="off" class="form-control" placeholder="{{ $keyExample }}" style="font-family:monospace;">
                <small style="{{ $muted }}">{{ __('panelica_license.page.api_key_hint') }}</small>
                @error('api_key')<div style="color:#a94442;font-size:12px;">{{ $message }}</div>@enderror
            </div>
            <details style="margin-bottom:12px;" @if($customCentral || $errors->has('central_url')) open @endif>
                <summary style="font-size:12px;cursor:pointer;{{ $muted }}">{{ __('panelica_license.page.advanced') }}</summary>
                <label for="pl-central" style="margin-top:8px;">{{ __('panelica_license.page.central_url') }}</label>
                <input type="url" id="pl-central" name="central_url" class="form-control" value="{{ old('central_url', $customCentral ? $centralUrl : '') }}" placeholder="{{ $defaultCentral }}">
                @error('central_url')<div style="color:#a94442;font-size:12px;">{{ $message }}</div>@enderror
            </details>
            <button type="submit" class="btn btn-primary" style="width:100%;"><i class="fas fa-plug"></i> {{ __('panelica_license.page.connect') }}</button>
        </form>
        <p style="font-size:12.5px;margin:12px 0 0;text-align:center;">
            {{ __('panelica_license.page.no_account') }}
            <a href="{{ $becomePartner }}" target="_blank" rel="noopener" style="font-weight:600;">{{ __('panelica_license.page.become_partner') }}</a>
        </p>
    </div>
</div>
@else
{{-- Connected. --}}
<div class="pl-stats">
    <div class="pl-stat">
        <div class="k">{{ __('panelica_license.page.partner') }}</div>
        <div class="v" style="font-size:16px;">{{ $account['company_name'] ?? '—' }}</div>
        <div class="s">{{ __('panelica_license.page.discount', ['percent' => rtrim(rtrim(number_format((float) ($account['discount_percentage'] ?? 0), 2), '0'), '.')]) }}</div>
    </div>
    <div class="pl-stat">
        <div class="k">{{ __('panelica_license.page.balance') }}</div>
        <div class="v">{{ $money($account['available_balance'] ?? $account['balance'] ?? 0) }}</div>
        <div class="s"><a href="{{ $topUp }}" target="_blank" rel="noopener">{{ __('panelica_license.page.top_up') }}</a></div>
    </div>
    <div class="pl-stat">
        <div class="k">{{ __('panelica_license.page.licensed') }}</div>
        <div class="v">{{ $count['licensed'] }}</div>
        <div class="s">{{ __('panelica_license.page.waiting_count', ['count' => $count['waiting']]) }}</div>
    </div>
    <div class="pl-stat" @if($count['attention'] > 0) style="border-color:#f5c2c0;background:#fffafa;" @endif>
        <div class="k">{{ __('panelica_license.page.attention') }}</div>
        <div class="v" @if($count['attention'] > 0) style="color:#a12a1f;" @endif>{{ $count['attention'] }}</div>
        <div class="s">{{ __('panelica_license.page.attention_hint') }}</div>
    </div>
</div>

<div class="card" style="margin-bottom:16px;">
    <div class="card-header"><strong>{{ __('panelica_license.page.plans_title') }}</strong><span style="font-weight:400;font-size:12px;{{ $muted }}">{{ __('panelica_license.page.plans_hint') }}</span></div>
    <div style="overflow-x:auto;">
    <table style="width:100%;border-collapse:collapse;">
        <tr>
            <th style="{{ $th }}">{{ __('panelica_license.page.plan') }}</th>
            <th style="{{ $th }}">{{ __('panelica_license.page.your_cost') }}</th>
            <th style="{{ $th }}">{{ __('panelica_license.page.list_price') }}</th>
            <th style="{{ $th }}">{{ __('panelica_license.page.your_product') }}</th>
            <th style="{{ $th }}">{{ __('panelica_license.page.margin') }}</th>
        </tr>
        @forelse($plans as $plan)
        @php
            $products = $productsByPlan->get((string) $plan['id'], collect());
            $costM = (float) ($plan['reseller_price_monthly'] ?? 0);
            $costY = (float) ($plan['reseller_price_yearly'] ?? 0);
            $suggestM = $usdRate ? round((float) ($plan['price_monthly'] ?? 0) * $usdRate, 2) : null;
            $suggestY = $usdRate ? round((float) ($plan['price_yearly'] ?? 0) * $usdRate, 2) : null;
            $first = $products->first();
        @endphp
        <tr>
            <td style="{{ $td }}"><strong>{{ $plan['name'] ?? '' }}</strong>@if(! empty($plan['description']))<br><small style="{{ $muted }}">{{ \Illuminate\Support\Str::limit((string) $plan['description'], 80) }}</small>@endif</td>
            <td style="{{ $td }}white-space:nowrap;">{{ __('panelica_license.page.per_month', ['price' => $money($costM)]) }}<br>{{ __('panelica_license.page.per_year', ['price' => $money($costY)]) }}</td>
            <td style="{{ $td }}white-space:nowrap;{{ $muted }}">{{ __('panelica_license.page.per_month', ['price' => $money($plan['price_monthly'] ?? 0)]) }}<br>{{ __('panelica_license.page.per_year', ['price' => $money($plan['price_yearly'] ?? 0)]) }}</td>
            <td style="{{ $td }}">
                @foreach($products as $product)
                <div style="margin-bottom:4px;">
                    <a href="{{ route('admin.products.edit', $product) }}" style="font-weight:600;">{{ $product->name }}</a>
                    <span style="{{ $muted }}font-size:12px;">
                        @if(($m = $product->priceFor('monthly')) !== null) · {{ __('panelica_license.page.per_month', ['price' => $local($m)]) }}@endif
                        @if(($y = $product->priceFor('annually')) !== null) · {{ __('panelica_license.page.per_year', ['price' => $local($y)]) }}@endif
                    </span>
                </div>
                @endforeach
                @if($products->isEmpty())
                <form method="POST" action="{{ route('admin.panelica-licenses.products.store') }}" style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;">
                    @csrf
                    <input type="hidden" name="plan_id" value="{{ $plan['id'] }}">
                    <input type="text" name="name" required maxlength="255" class="form-control" style="width:170px;font-size:12.5px;padding:4px 6px;" value="{{ __('panelica_license.page.product_name', ['plan' => $plan['name'] ?? '']) }}" aria-label="{{ __('panelica_license.page.product_name_label') }}">
                    <input type="number" name="monthly" step="0.01" min="0.01" class="form-control pl-price" value="{{ $suggestM ?: '' }}" placeholder="{{ __('panelica_license.page.monthly') }}" aria-label="{{ __('panelica_license.page.monthly') }}" title="{{ __('panelica_license.page.monthly') }}">
                    <input type="number" name="annually" step="0.01" min="0.01" class="form-control pl-price" value="{{ $suggestY ?: '' }}" placeholder="{{ __('panelica_license.page.annually') }}" aria-label="{{ __('panelica_license.page.annually') }}" title="{{ __('panelica_license.page.annually') }}">
                    <span style="font-size:12px;{{ $muted }}">{{ $currency?->code }}</span>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> {{ __('panelica_license.page.create_product') }}</button>
                </form>
                @endif
            </td>
            <td style="{{ $td }}white-space:nowrap;">
                @if($first && $usdRate && ($first->priceFor('monthly') !== null || $first->priceFor('annually') !== null))
                    @if(($m = $first->priceFor('monthly')) !== null)<span class="pl-badge {{ $m - $costM * $usdRate >= 0 ? 'ok' : 'bad' }}">{{ __('panelica_license.page.per_month', ['price' => $local($m - $costM * $usdRate)]) }}</span><br>@endif
                    @if(($y = $first->priceFor('annually')) !== null)<span class="pl-badge {{ $y - $costY * $usdRate >= 0 ? 'ok' : 'bad' }}" style="margin-top:3px;">{{ __('panelica_license.page.per_year', ['price' => $local($y - $costY * $usdRate)]) }}</span>@endif
                @elseif(! $first && $usdRate)
                    <span style="{{ $muted }}font-size:12px;">{{ __('panelica_license.page.per_year', ['price' => $local(max(0, ($suggestY ?? 0) - $costY * $usdRate))]) }}</span>
                @else
                    <span style="{{ $muted }}">—</span>
                @endif
            </td>
        </tr>
        @empty
        <tr><td colspan="5" style="{{ $td }}{{ $muted }}">{{ __('panelica_license.page.no_plans') }}</td></tr>
        @endforelse
    </table>
    </div>
    @if(! $usdRate)
    <div style="padding:8px 12px;font-size:12px;{{ $muted }}border-top:1px solid #f0f0f0;">{{ __('panelica_license.page.no_usd_rate') }}</div>
    @endif
</div>

<div class="card" style="margin-bottom:16px;">
    <div class="card-header"><strong>{{ __('panelica_license.page.sales_title') }}</strong></div>
    <div style="overflow-x:auto;">
    <table style="width:100%;border-collapse:collapse;">
        <tr>
            <th style="{{ $th }}">{{ __('panelica_license.page.customer') }}</th>
            <th style="{{ $th }}">{{ __('panelica_license.page.product') }}</th>
            <th style="{{ $th }}">{{ __('panelica_license.field.status') }}</th>
            <th style="{{ $th }}">{{ __('panelica_license.field.key') }}</th>
            <th style="{{ $th }}">{{ __('panelica_license.field.server') }}</th>
            <th style="{{ $th }}">{{ __('panelica_license.field.valid_until') }}</th>
        </tr>
        @forelse($services as $service)
        @php
            $d = $service->module_data ?? [];
            $state = ! empty($d['ended']) ? 'ended' : (! empty($d['license_id']) ? 'licensed' : (! empty($d['install_ref']) ? 'waiting' : 'none'));
        @endphp
        <tr>
            <td style="{{ $td }}"><a href="{{ route('admin.services.show', $service) }}" style="font-weight:600;">{{ $service->client?->full_name ?: ('#'.$service->client_id) }}</a></td>
            <td style="{{ $td }}">{{ $service->product?->name }}</td>
            <td style="{{ $td }}">
                <span class="pl-badge {{ $state === 'licensed' ? 'ok' : ($state === 'waiting' ? 'wait' : '') }}">{{ __('panelica_license.state.'.$state) }}</span>
                @if(! empty($d['last_error']))<span class="pl-badge bad" title="{{ $d['last_error'] }}">{{ __('panelica_license.page.attention') }}</span>@endif
            </td>
            <td style="{{ $td }}font-family:monospace;font-size:12px;">{{ $d['license_key'] ?? '—' }}</td>
            <td style="{{ $td }}">{{ trim(($d['license_server_hostname'] ?? ($d['install']['hostname'] ?? '')).' '.(filled($d['license_server_ip'] ?? ($d['install']['ip_address'] ?? null)) ? '('.($d['license_server_ip'] ?? $d['install']['ip_address']).')' : '')) ?: '—' }}</td>
            <td style="{{ $td }}white-space:nowrap;">{{ ! empty($d['license_expires_at']) ? \Illuminate\Support\Carbon::parse($d['license_expires_at'])->timezone(config('app.timezone'))->format(date_fmt()) : '—' }}</td>
        </tr>
        @empty
        <tr><td colspan="6" style="{{ $td }}{{ $muted }}">{{ __('panelica_license.page.no_sales') }}</td></tr>
        @endforelse
    </table>
    </div>
</div>

<details class="card" style="margin-bottom:16px;">
    <summary class="card-header" style="cursor:pointer;"><strong>{{ __('panelica_license.page.account_settings') }}</strong></summary>
    <div class="card-body" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(300px,100%),1fr));gap:18px;">
        <form method="POST" action="{{ route('admin.panelica-licenses.connect') }}">
            @csrf
            <label for="pl-key2" style="font-size:12px;font-weight:600;">{{ __('panelica_license.page.change_key') }}</label>
            <div style="display:flex;gap:6px;">
                <input type="password" id="pl-key2" name="api_key" required autocomplete="off" class="form-control" placeholder="{{ $keyExample }}" style="font-family:monospace;">
                <input type="hidden" name="central_url" value="{{ $customCentral ? $centralUrl : '' }}">
                <button type="submit" class="btn btn-primary btn-sm">{{ __('common.actions.save') }}</button>
            </div>
        </form>
        <form method="POST" action="{{ route('admin.panelica-licenses.disconnect') }}" onsubmit="return pnConfirm(event, @js(__('panelica_license.page.disconnect_confirm')))">
            @csrf
            <div style="font-size:12px;font-weight:600;margin-bottom:4px;">{{ __('panelica_license.page.disconnect') }}</div>
            <p style="font-size:12px;{{ $muted }}margin:0 0 6px;">{{ __('panelica_license.page.disconnect_hint') }}</p>
            <button type="submit" class="btn btn-danger btn-sm">{{ __('panelica_license.page.disconnect') }}</button>
        </form>
    </div>
</details>
@endif
@endsection
