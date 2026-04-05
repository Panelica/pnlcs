@extends("client.layouts.app")
@section("title", $service->product?->name ?? "Service")
@section("content")

<a href="{{ route("client.services.index") }}" class="pn-back">
    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
    {{ __('client.services.back_to_services') }}
</a>

<div class="pn-page-header">
    <div>
        <h1 class="pn-page-title">{{ $service->product?->name ?? "Service" }}</h1>
        @if($service->domain)<p class="pn-page-subtitle">{{ $service->domain }}</p>@endif
    </div>
    <span class="badge badge-{{ strtolower($service->status) }}" style="font-size:13px;padding:5px 14px">{{ ucfirst($service->status) }}</span>
</div>

<div class="pn-2col mb-24">
    <div class="pn-card">
        <div class="pn-card-header"><span class="pn-card-title">{{ __('client.services.service_details') }}</span></div>
        <div class="pn-card-body">
            <ul class="pn-detail-list">
                <li><span class="key">{{ __('client.cart.product') }}</span><span class="val">{{ $service->product?->name ?? "N/A" }}</span></li>
                <li><span class="key">{{ __('client.cart.billing_cycle') }}</span><span class="val" style="text-transform:capitalize">{{ $service->billing_cycle ?? "N/A" }}</span></li>
                <li><span class="key">{{ __('client.services.amount') }}</span><span class="val">${{ number_format($service->amount, 2) }} / {{ $service->billing_cycle }}</span></li>
                <li><span class="key">{{ __('client.services.next_due_date') }}</span><span class="val">{{ $service->next_due_date?->format("d M Y") ?? "N/A" }}</span></li>
                <li><span class="key">{{ __('client.services.registration_date') }}</span><span class="val">{{ $service->registration_date?->format("d M Y") ?? "N/A" }}</span></li>
                <li><span class="key">{{ __('client.checkout.payment_method') }}</span><span class="val" style="text-transform:capitalize">{{ $service->payment_method ?? "N/A" }}</span></li>
                <li>
                    <span class="key">{{ __('client.services.auto_renew') }}</span>
                    <span class="val">
                        <form method="POST" action="{{ route("client.services.autorenew", $service) }}" style="display:inline;">
                            @csrf
                            <button type="submit" style="display:inline-flex;align-items:center;gap:6px;padding:4px 14px;font-size:12px;font-weight:600;border-radius:6px;border:1px solid {{ $service->auto_renew ? '#22c55e' : '#d1d5db' }};background:{{ $service->auto_renew ? '#f0fdf4' : '#f9fafb' }};color:{{ $service->auto_renew ? '#16a34a' : '#6b7280' }};cursor:pointer;transition:all .15s;">
                                <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:{{ $service->auto_renew ? '#22c55e' : '#d1d5db' }}"></span>
                                {{ $service->auto_renew ? __('client.status.enabled') : __('client.status.disabled') }}
                            </button>
                        </form>
                    </span>
                </li>
            </ul>
        </div>
    </div>

    <div class="pn-card">
        <div class="pn-card-header"><span class="pn-card-title">{{ __('client.services.server_info') }}</span></div>
        <div class="pn-card-body">
            <ul class="pn-detail-list">
                <li><span class="key">{{ __('client.services.server') }}</span><span class="val">{{ $service->server->name ?? "N/A" }}</span></li>
                <li><span class="key">{{ __('client.services.username') }}</span><span class="val"><span class="pn-code">{{ $service->username ?? "-" }}</span></span></li>
                @if($service->server?->hostname)
                <li><span class="key">{{ __('client.services.hostname') }}</span><span class="val">{{ $service->server->hostname }}</span></li>
                @endif
                @if($service->server?->ip)
                <li><span class="key">{{ __('client.services.ip_address') }}</span><span class="val"><span class="pn-code">{{ $service->server->ip }}</span></span></li>
                @endif
            </ul>
        </div>
    </div>
</div>

@if($service->disk_limit || $service->bw_limit)
<div class="pn-card mb-24">
    <div class="pn-card-header"><span class="pn-card-title">{{ __('client.services.resource_usage') }}</span></div>
    <div class="pn-card-body">
        @if($service->disk_limit)
        @php $dp = $service->disk_limit > 0 ? min(100, round(($service->disk_usage / $service->disk_limit) * 100)) : 0; $dc = $dp >= 90 ? "var(--danger)" : ($dp >= 75 ? "var(--warning)" : "var(--primary)"); @endphp
        <div style="margin-bottom:20px">
            <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:8px">
                <span style="font-weight:600">{{ __('client.services.disk_usage') }}</span>
                <span class="text-muted">{{ number_format($service->disk_usage) }} / {{ number_format($service->disk_limit) }} MB — <strong>{{ $dp }}%</strong></span>
            </div>
            <div class="pn-progress-wrap"><div class="pn-progress-fill" style="width:{{ $dp }}%;background:{{ $dc }}"></div></div>
        </div>
        @endif
        @if($service->bw_limit)
        @php $bp = $service->bw_limit > 0 ? min(100, round(($service->bw_usage / $service->bw_limit) * 100)) : 0; $bc = $bp >= 90 ? "var(--danger)" : ($bp >= 75 ? "var(--warning)" : "var(--success)"); @endphp
        <div>
            <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:8px">
                <span style="font-weight:600">{{ __('client.services.bandwidth_usage') }}</span>
                <span class="text-muted">{{ number_format($service->bw_usage) }} / {{ number_format($service->bw_limit) }} MB — <strong>{{ $bp }}%</strong></span>
            </div>
            <div class="pn-progress-wrap"><div class="pn-progress-fill" style="width:{{ $bp }}%;background:{{ $bc }}"></div></div>
        </div>
        @endif
    </div>
</div>
@endif

@if(in_array(strtolower($service->status), ["active"]))
<div class="pn-actions mb-24">
    <a href="{{ route("client.services.upgrade", $service) }}" class="btn btn-primary">{{ __('client.services.upgrade_downgrade') }}</a>
    <a href="{{ route("client.services.cancel", $service) }}" class="btn btn-danger">{{ __('client.services.request_cancellation') }}</a>
</div>
@endif

@if($service->addons && $service->addons->count())
<div class="pn-card">
    <div class="pn-card-header"><span class="pn-card-title">{{ __('client.services.addons') }}</span></div>
    <div class="pn-card-body-flush">
        <table class="pn-table">
            <thead><tr><th>{{ __('common.table.name') }}</th><th>{{ __('common.table.amount') }}</th><th>{{ __('common.table.billing_cycle') }}</th><th>{{ __('client.services.next_due_date') }}</th><th>{{ __('common.table.status') }}</th></tr></thead>
            <tbody>
                @foreach($service->addons as $addon)
                <tr>
                    <td>Addon #{{ $addon->addon_id ?? $addon->id }}</td>
                    <td>${{ number_format($addon->amount, 2) }}</td>
                    <td style="text-transform:capitalize">{{ $addon->billing_cycle }}</td>
                    <td class="text-muted text-sm">{{ $addon->next_due_date?->format("d M Y") ?? "-" }}</td>
                    <td><span class="badge badge-{{ strtolower($addon->status) }}">{{ ucfirst($addon->status) }}</span></td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

@endsection
