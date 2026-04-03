@extends("client.layouts.app")
@section("title", $service->product?->name ?? "Service")
@section("content")

<a href="{{ route("client.services.index") }}" class="pn-back">
    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
    Back to My Services
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
        <div class="pn-card-header"><span class="pn-card-title">Service Details</span></div>
        <div class="pn-card-body">
            <ul class="pn-detail-list">
                <li><span class="key">Product</span><span class="val">{{ $service->product?->name ?? "N/A" }}</span></li>
                <li><span class="key">Billing Cycle</span><span class="val" style="text-transform:capitalize">{{ $service->billing_cycle ?? "N/A" }}</span></li>
                <li><span class="key">Amount</span><span class="val">${{ number_format($service->amount, 2) }} / {{ $service->billing_cycle }}</span></li>
                <li><span class="key">Next Due Date</span><span class="val">{{ $service->next_due_date?->format("d M Y") ?? "N/A" }}</span></li>
                <li><span class="key">Registration Date</span><span class="val">{{ $service->registration_date?->format("d M Y") ?? "N/A" }}</span></li>
                <li><span class="key">Payment Method</span><span class="val" style="text-transform:capitalize">{{ $service->payment_method ?? "N/A" }}</span></li>
            </ul>
        </div>
    </div>

    <div class="pn-card">
        <div class="pn-card-header"><span class="pn-card-title">Server Information</span></div>
        <div class="pn-card-body">
            <ul class="pn-detail-list">
                <li><span class="key">Server</span><span class="val">{{ $service->server->name ?? "N/A" }}</span></li>
                <li><span class="key">Username</span><span class="val"><span class="pn-code">{{ $service->username ?? "-" }}</span></span></li>
                @if($service->server?->hostname)
                <li><span class="key">Hostname</span><span class="val">{{ $service->server->hostname }}</span></li>
                @endif
                @if($service->server?->ip)
                <li><span class="key">IP Address</span><span class="val"><span class="pn-code">{{ $service->server->ip }}</span></span></li>
                @endif
            </ul>
        </div>
    </div>
</div>

@if($service->disk_limit || $service->bw_limit)
<div class="pn-card mb-24">
    <div class="pn-card-header"><span class="pn-card-title">Resource Usage</span></div>
    <div class="pn-card-body">
        @if($service->disk_limit)
        @php $dp = $service->disk_limit > 0 ? min(100, round(($service->disk_usage / $service->disk_limit) * 100)) : 0; $dc = $dp >= 90 ? "var(--danger)" : ($dp >= 75 ? "var(--warning)" : "var(--primary)"); @endphp
        <div style="margin-bottom:20px">
            <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:8px">
                <span style="font-weight:600">Disk Usage</span>
                <span class="text-muted">{{ number_format($service->disk_usage) }} / {{ number_format($service->disk_limit) }} MB — <strong>{{ $dp }}%</strong></span>
            </div>
            <div class="pn-progress-wrap"><div class="pn-progress-fill" style="width:{{ $dp }}%;background:{{ $dc }}"></div></div>
        </div>
        @endif
        @if($service->bw_limit)
        @php $bp = $service->bw_limit > 0 ? min(100, round(($service->bw_usage / $service->bw_limit) * 100)) : 0; $bc = $bp >= 90 ? "var(--danger)" : ($bp >= 75 ? "var(--warning)" : "var(--success)"); @endphp
        <div>
            <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:8px">
                <span style="font-weight:600">Bandwidth Usage</span>
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
    <a href="{{ route("client.services.upgrade", $service) }}" class="btn btn-primary">Upgrade / Downgrade</a>
    <a href="{{ route("client.services.cancel", $service) }}" class="btn btn-danger">Request Cancellation</a>
</div>
@endif

@if($service->addons && $service->addons->count())
<div class="pn-card">
    <div class="pn-card-header"><span class="pn-card-title">Add-ons</span></div>
    <div class="pn-card-body-flush">
        <table class="pn-table">
            <thead><tr><th>Name</th><th>Amount</th><th>Billing Cycle</th><th>Next Due</th><th>Status</th></tr></thead>
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
