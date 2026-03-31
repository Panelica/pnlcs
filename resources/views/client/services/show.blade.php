@extends('client.layouts.app')
@section('title', $service->product->name ?? 'Service')
@section('styles')
<style>
    .detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
    @media (max-width: 640px) { .detail-grid { grid-template-columns: 1fr; } }
    .detail-row { display: flex; justify-content: space-between; align-items: center; padding: 7px 0; border-bottom: 1px solid #f0f0f0; font-size: 13px; }
    .detail-row:last-child { border-bottom: none; }
    .detail-row dt { color: #777; font-weight: 400; }
    .detail-row dd { font-weight: 500; color: #333; margin: 0; }
    .progress-bar-wrap { background: #e9ecef; border-radius: 4px; height: 8px; overflow: hidden; }
    .progress-bar-fill { height: 100%; border-radius: 4px; }
    .action-bar { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 20px; }
</style>
@endsection
@section('content')

<div class="page-header">
    <div>
        <h1>{{ $service->product->name ?? 'Service' }}</h1>
        @if($service->domain)<p style="margin:2px 0 0; color:#777; font-size:13px;">{{ $service->domain }}</p>@endif
    </div>
    <span class="badge badge-{{ strtolower($service->status) }}" style="font-size:12px; padding:4px 10px;">{{ ucfirst($service->status) }}</span>
</div>

<div class="detail-grid">
    <div class="card">
        <div class="card-header">Service Details</div>
        <div class="card-body">
            <dl>
                <div class="detail-row"><dt>Billing Cycle</dt><dd style="text-transform:capitalize;">{{ $service->billing_cycle ?? 'N/A' }}</dd></div>
                <div class="detail-row"><dt>Amount</dt><dd>${{ number_format($service->amount, 2) }}/{{ $service->billing_cycle }}</dd></div>
                <div class="detail-row"><dt>Next Due Date</dt><dd>{{ $service->next_due_date?->format('d M Y') ?? 'N/A' }}</dd></div>
                <div class="detail-row"><dt>Registration Date</dt><dd>{{ $service->registration_date?->format('d M Y') ?? 'N/A' }}</dd></div>
                <div class="detail-row"><dt>Payment Method</dt><dd style="text-transform:capitalize;">{{ $service->payment_method ?? 'N/A' }}</dd></div>
            </dl>
        </div>
    </div>
    <div class="card">
        <div class="card-header">Server Details</div>
        <div class="card-body">
            <dl>
                <div class="detail-row"><dt>Server</dt><dd>{{ $service->server->name ?? 'N/A' }}</dd></div>
                <div class="detail-row"><dt>Username</dt><dd><code style="background:#f5f5f5; padding:2px 6px; border-radius:3px; font-size:12px;">{{ $service->username ?? '-' }}</code></dd></div>
                @if($service->server?->hostname)
                <div class="detail-row"><dt>Hostname</dt><dd>{{ $service->server->hostname }}</dd></div>
                @endif
                @if($service->server?->ip)
                <div class="detail-row"><dt>IP Address</dt><dd>{{ $service->server->ip }}</dd></div>
                @endif
            </dl>
        </div>
    </div>
</div>

@if($service->disk_limit || $service->bw_limit)
<div class="card" style="margin-bottom:20px;">
    <div class="card-header">Resource Usage</div>
    <div class="card-body">
        @if($service->disk_limit)
        @php $diskPct = $service->disk_limit > 0 ? min(100, round(($service->disk_usage / $service->disk_limit) * 100)) : 0; $diskColor = $diskPct >= 90 ? '#c43c35' : ($diskPct >= 75 ? '#f89406' : '#337ab7'); @endphp
        <div style="margin-bottom:14px;">
            <div style="display:flex; justify-content:space-between; font-size:13px; margin-bottom:6px;">
                <span style="color:#555;">Disk Usage</span>
                <span style="font-weight:500;">{{ number_format($service->disk_usage) }} / {{ number_format($service->disk_limit) }} MB ({{ $diskPct }}%)</span>
            </div>
            <div class="progress-bar-wrap">
                <div class="progress-bar-fill" style="width:{{ $diskPct }}%; background:{{ $diskColor }};"></div>
            </div>
        </div>
        @endif
        @if($service->bw_limit)
        @php $bwPct = $service->bw_limit > 0 ? min(100, round(($service->bw_usage / $service->bw_limit) * 100)) : 0; $bwColor = $bwPct >= 90 ? '#c43c35' : ($bwPct >= 75 ? '#f89406' : '#46a546'); @endphp
        <div>
            <div style="display:flex; justify-content:space-between; font-size:13px; margin-bottom:6px;">
                <span style="color:#555;">Bandwidth Usage</span>
                <span style="font-weight:500;">{{ number_format($service->bw_usage) }} / {{ number_format($service->bw_limit) }} MB ({{ $bwPct }}%)</span>
            </div>
            <div class="progress-bar-wrap">
                <div class="progress-bar-fill" style="width:{{ $bwPct }}%; background:{{ $bwColor }};"></div>
            </div>
        </div>
        @endif
    </div>
</div>
@endif

@if(in_array(strtolower($service->status), ['active']))
<div class="action-bar">
    <a href="{{ route('client.services.upgrade', $service) }}" class="btn btn-primary">Upgrade / Downgrade</a>
    <a href="{{ route('client.services.cancel', $service) }}" class="btn btn-danger">Request Cancellation</a>
</div>
@endif

@if($service->addons && $service->addons->count())
<div class="card" style="margin-bottom:20px;">
    <div class="card-header">Add-ons</div>
    <div class="card-body" style="padding:0;">
        <table class="data-table">
            <thead>
                <tr><th>Name</th><th>Amount</th><th>Billing Cycle</th><th>Next Due</th><th>Status</th></tr>
            </thead>
            <tbody>
                @foreach($service->addons as $addon)
                <tr>
                    <td>Addon #{{ $addon->addon_id ?? $addon->id }}</td>
                    <td>${{ number_format($addon->amount, 2) }}</td>
                    <td style="text-transform:capitalize;">{{ $addon->billing_cycle }}</td>
                    <td style="color:#777;">{{ $addon->next_due_date?->format('d M Y') ?? '-' }}</td>
                    <td><span class="badge badge-{{ strtolower($addon->status) }}">{{ ucfirst($addon->status) }}</span></td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

<a href="{{ route('client.services.index') }}" style="color:#337ab7; font-size:13px;">&larr; Back to My Services</a>

@endsection
