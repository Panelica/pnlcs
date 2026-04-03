@extends('admin.layouts.app')
@section('title', ($service->product?->name ?? 'Service') . ($service->domain ? ' - ' . $service->domain : ''))
@section('content')
<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;">
    <h1>
        {{ $service->product?->name ?? 'Service #'.$service->id }}
        @if($service->domain) &mdash; <span style="font-family:monospace;font-size:18px;">{{ $service->domain }}</span>@endif
        <span class="badge-{{ strtolower($service->status) }}" style="font-size:13px;vertical-align:middle;margin-left:8px;">{{ ucfirst($service->status) }}</span>
    </h1>
    <a href="{{ route('admin.services.index') }}" class="btn btn-default btn-sm">&larr; Services</a>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:15px;margin-bottom:15px;">

    <div class="panel">
        <div class="panel-heading panel-primary">Service Info</div>
        <div class="panel-body">
            <table style="width:100%;font-size:13px;border-collapse:collapse;">
                <tr><td style="padding:5px 0;color:#777;width:40%;">Product</td><td style="padding:5px 0;font-weight:600;">{{ $service->product?->name ?? 'N/A' }}</td></tr>
                <tr><td style="padding:5px 0;color:#777;">Domain</td><td style="padding:5px 0;font-family:monospace;font-size:12px;">{{ $service->domain ?? '-' }}</td></tr>
                <tr><td style="padding:5px 0;color:#777;">Username</td><td style="padding:5px 0;font-family:monospace;">{{ $service->username ?? '-' }}</td></tr>
                <tr><td style="padding:5px 0;color:#777;">Client</td><td style="padding:5px 0;"><a href="{{ route('admin.clients.show', $service->client_id) }}" style="color:#337ab7;">{{ $service->client->full_name ?? 'N/A' }}</a></td></tr>
                @if($service->order_id)
                <tr><td style="padding:5px 0;color:#777;">Order</td><td style="padding:5px 0;"><a href="{{ route('admin.orders.show', $service->order_id) }}" style="color:#337ab7;">#{{ $service->order_id }}</a></td></tr>
                @endif
            </table>
        </div>
    </div>

    <div class="panel">
        <div class="panel-heading panel-primary">Server &amp; Module</div>
        <div class="panel-body">
            <table style="width:100%;font-size:13px;border-collapse:collapse;">
                <tr><td style="padding:5px 0;color:#777;width:40%;">Server</td><td style="padding:5px 0;font-weight:600;">{{ $service->server->name ?? 'None assigned' }}</td></tr>
                <tr><td style="padding:5px 0;color:#777;">Module</td><td style="padding:5px 0;">{{ $service->product?->server_type ?? 'None' }}</td></tr>
                @if($service->suspension_date)
                <tr><td style="padding:5px 0;color:#777;">Suspended</td><td style="padding:5px 0;color:#d9534f;">{{ $service->suspension_date->format('d M Y') }}</td></tr>
                @endif
                @if($service->suspension_reason)
                <tr><td style="padding:5px 0;color:#777;">Reason</td><td style="padding:5px 0;color:#777;">{{ $service->suspension_reason }}</td></tr>
                @endif
                @if($service->termination_date)
                <tr><td style="padding:5px 0;color:#777;">Terminated</td><td style="padding:5px 0;color:#d9534f;">{{ $service->termination_date->format('d M Y') }}</td></tr>
                @endif
            </table>
        </div>
    </div>

    <div class="panel">
        <div class="panel-heading panel-primary">Billing</div>
        <div class="panel-body">
            <table style="width:100%;font-size:13px;border-collapse:collapse;">
                <tr><td style="padding:5px 0;color:#777;width:45%;">Amount</td><td style="padding:5px 0;font-weight:700;font-size:15px;">${{ number_format($service->amount, 2) }}<span style="font-size:11px;font-weight:400;color:#999;">/{{ $service->billing_cycle }}</span></td></tr>
                <tr><td style="padding:5px 0;color:#777;">First Payment</td><td style="padding:5px 0;">${{ number_format($service->first_payment_amount, 2) }}</td></tr>
                <tr><td style="padding:5px 0;color:#777;">Registered</td><td style="padding:5px 0;">{{ $service->registration_date?->format('d M Y') ?? '-' }}</td></tr>
                <tr><td style="padding:5px 0;color:#777;">Next Due</td><td style="padding:5px 0;{{ $service->next_due_date?->isPast() ? 'color:#d9534f;font-weight:600;' : '' }}">{{ $service->next_due_date?->format('d M Y') ?? '-' }}</td></tr>
                <tr><td style="padding:5px 0;color:#777;">Payment</td><td style="padding:5px 0;">{{ $service->payment_method ?? '-' }}</td></tr>
            </table>
        </div>
    </div>
</div>

@if($service->disk_limit > 0 || $service->bw_limit > 0)
<div class="card" style="margin-bottom:15px;">
    <div class="card-header"><strong>Resource Usage</strong></div>
    <div class="card-body" style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
        @if($service->disk_limit > 0)
        @php $diskPct = min(100, ($service->disk_limit > 0 ? ($service->disk_usage / $service->disk_limit) * 100 : 0)); @endphp
        <div>
            <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:5px;">
                <span style="color:#777;">Disk Usage</span>
                <span style="font-weight:600;">{{ number_format($service->disk_usage / 1024 / 1024, 1) }} MB / {{ number_format($service->disk_limit / 1024 / 1024, 1) }} MB</span>
            </div>
            <div style="background:#e9e9e9;border-radius:3px;height:12px;">
                <div style="height:12px;border-radius:3px;background:{{ $diskPct > 85 ? '#d9534f' : '#337ab7' }};width:{{ $diskPct }}%;"></div>
            </div>
        </div>
        @endif
        @if($service->bw_limit > 0)
        @php $bwPct = min(100, ($service->bw_limit > 0 ? ($service->bw_usage / $service->bw_limit) * 100 : 0)); @endphp
        <div>
            <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:5px;">
                <span style="color:#777;">Bandwidth</span>
                <span style="font-weight:600;">{{ number_format($service->bw_usage / 1024 / 1024, 1) }} MB / {{ number_format($service->bw_limit / 1024 / 1024, 1) }} MB</span>
            </div>
            <div style="background:#e9e9e9;border-radius:3px;height:12px;">
                <div style="height:12px;border-radius:3px;background:{{ $bwPct > 85 ? '#d9534f' : '#5cb85c' }};width:{{ $bwPct }}%;"></div>
            </div>
        </div>
        @endif
    </div>
</div>
@endif

<div class="card" style="margin-bottom:15px;">
    <div class="card-header"><strong>Module Actions</strong></div>
    <div class="card-body">
        @if(!$service->product?->server_type)
        <p style="font-size:13px;color:#999;">No server module configured for this product.</p>
        @else
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px;">
            <form method="POST" action="{{ route('admin.services.module-action', [$service, 'create']) }}" onsubmit="return confirm('Create account on server?')">
                @csrf <button type="submit" class="btn btn-success btn-sm">Create Account</button>
            </form>
            <form method="POST" action="{{ route('admin.services.module-action', [$service, 'suspend']) }}" onsubmit="return confirm('Suspend this service?')">
                @csrf <button type="submit" class="btn btn-warning btn-sm">Suspend</button>
            </form>
            <form method="POST" action="{{ route('admin.services.module-action', [$service, 'unsuspend']) }}" onsubmit="return confirm('Unsuspend this service?')">
                @csrf <button type="submit" class="btn btn-primary btn-sm">Unsuspend</button>
            </form>
            <form method="POST" action="{{ route('admin.services.module-action', [$service, 'terminate']) }}" onsubmit="return confirm('TERMINATE this service? This cannot be undone.')">
                @csrf <button type="submit" class="btn btn-danger btn-sm">Terminate</button>
            </form>
            <form method="POST" action="{{ route('admin.services.module-action', [$service, 'changepassword']) }}" style="display:flex;gap:4px;">
                @csrf
                <input type="password" name="password" placeholder="New password" required minlength="6" class="form-control" style="width:150px;font-size:13px;">
                <button type="submit" class="btn btn-default btn-sm">Change Password</button>
            </form>
        </div>
        <p style="font-size:11px;color:#999;">Module: <span style="font-family:monospace;">{{ $service->product?->server_type }}</span></p>
        @endif
    </div>
</div>

@if($service->notes)
<div class="card">
    <div class="card-header"><strong>Notes</strong></div>
    <div class="card-body" style="font-size:13px;white-space:pre-wrap;color:#555;">{{ $service->notes }}</div>
</div>
@endif

@endsection
