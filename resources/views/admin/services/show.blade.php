@extends('admin.layouts.app')
@section('title', ($service->product?->name ?? 'Service') . ($service->domain ? ' - ' . $service->domain : ''))
@section('content')
<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;">
    <h1>
        {{ $service->product?->name ?? 'Service #'.$service->id }}
        @if($service->domain) &mdash; <span style="font-family:monospace;font-size:18px;">{{ $service->domain }}</span>@endif
        <span class="badge-{{ strtolower($service->status) }}" style="font-size:13px;vertical-align:middle;margin-left:8px;">{{ ucfirst($service->status) }}</span>
    </h1>
    <a href="{{ route('admin.services.index') }}" class="btn btn-default btn-sm">&larr; {{ __('admin.services.back') }}</a>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:15px;margin-bottom:15px;">

    <div class="panel">
        <div class="panel-heading panel-primary">{{ __('admin.services.service_info') }}</div>
        <div class="panel-body">
            <table style="width:100%;font-size:13px;border-collapse:collapse;">
                <tr><td style="padding:5px 0;color:#777;width:40%;">{{ __('admin.services.product') }}</td><td style="padding:5px 0;font-weight:600;">{{ $service->product?->name ?? 'N/A' }}</td></tr>
                <tr><td style="padding:5px 0;color:#777;">{{ __('admin.services.domain') }}</td><td style="padding:5px 0;font-family:monospace;font-size:12px;">{{ $service->domain ?? '-' }}</td></tr>
                <tr><td style="padding:5px 0;color:#777;">{{ __('admin.services.username') }}</td><td style="padding:5px 0;font-family:monospace;">{{ $service->username ?? '-' }}</td></tr>
                <tr><td style="padding:5px 0;color:#777;">{{ __('admin.services.client') }}</td><td style="padding:5px 0;"><a href="{{ route('admin.clients.show', $service->client_id) }}" style="color:#337ab7;">{{ $service->client->full_name ?? 'N/A' }}</a></td></tr>
                @if($service->order_id)
                <tr><td style="padding:5px 0;color:#777;">{{ __('admin.services.order') }}</td><td style="padding:5px 0;"><a href="{{ route('admin.orders.show', $service->order_id) }}" style="color:#337ab7;">#{{ $service->order_id }}</a></td></tr>
                @endif
            </table>
        </div>
    </div>

    <div class="panel">
        <div class="panel-heading panel-primary">{{ __('admin.services.server_module') }}</div>
        <div class="panel-body">
            <table style="width:100%;font-size:13px;border-collapse:collapse;">
                <tr><td style="padding:5px 0;color:#777;width:40%;">{{ __('admin.services.server') }}</td><td style="padding:5px 0;font-weight:600;">{{ $service->server->name ?? __('admin.services.none_assigned') }}</td></tr>
                <tr><td style="padding:5px 0;color:#777;">{{ __('admin.services.module') }}</td><td style="padding:5px 0;">{{ $service->product?->server_type ?? __('admin.services.none_label') }}</td></tr>
                @if($service->suspension_date)
                <tr><td style="padding:5px 0;color:#777;">{{ __('admin.services.suspended') }}</td><td style="padding:5px 0;color:#d9534f;">{{ $service->suspension_date->format(date_fmt()) }}</td></tr>
                @endif
                @if($service->suspension_reason)
                <tr><td style="padding:5px 0;color:#777;">{{ __('admin.services.reason') }}</td><td style="padding:5px 0;color:#777;">{{ $service->suspension_reason }}</td></tr>
                @endif
                @if($service->termination_date)
                <tr><td style="padding:5px 0;color:#777;">{{ __('admin.services.terminated') }}</td><td style="padding:5px 0;color:#d9534f;">{{ $service->termination_date->format(date_fmt()) }}</td></tr>
                @endif
            </table>
        </div>
    </div>

    <div class="panel">
        <div class="panel-heading panel-primary">{{ __('admin.services.billing') }}</div>
        <div class="panel-body">
            <table style="width:100%;font-size:13px;border-collapse:collapse;">
                <tr><td style="padding:5px 0;color:#777;width:45%;">{{ __('admin.services.amount') }}</td><td style="padding:5px 0;font-weight:700;font-size:15px;">{{ money_fmt($service->amount) }}<span style="font-size:11px;font-weight:400;color:#999;">/{{ $service->billing_cycle }}</span></td></tr>
                <tr><td style="padding:5px 0;color:#777;">{{ __('admin.services.first_payment') }}</td><td style="padding:5px 0;">{{ money_fmt($service->first_payment_amount) }}</td></tr>
                <tr><td style="padding:5px 0;color:#777;">{{ __('admin.services.registered') }}</td><td style="padding:5px 0;">{{ $service->registration_date?->format(date_fmt()) ?? '-' }}</td></tr>
                <tr><td style="padding:5px 0;color:#777;">{{ __('admin.services.next_due') }}</td><td style="padding:5px 0;{{ $service->next_due_date?->isPast() ? 'color:#d9534f;font-weight:600;' : '' }}">{{ $service->next_due_date?->format(date_fmt()) ?? '-' }}</td></tr>
                <tr><td style="padding:5px 0;color:#777;">{{ __('admin.services.payment') }}</td><td style="padding:5px 0;">{{ $service->payment_method ?? '-' }}</td></tr>
            </table>
        </div>
    </div>
</div>

@if($service->disk_limit > 0 || $service->bw_limit > 0)
<div class="card" style="margin-bottom:15px;">
    <div class="card-header"><strong>{{ __('admin.services.resource_usage') }}</strong></div>
    <div class="card-body" style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
        @if($service->disk_limit > 0)
        @php $diskPct = min(100, ($service->disk_limit > 0 ? ($service->disk_usage / $service->disk_limit) * 100 : 0)); @endphp
        <div>
            <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:5px;">
                <span style="color:#777;">{{ __('admin.services.disk_usage') }}</span>
                <span style="font-weight:600;">{{ number_format($service->disk_usage) }} MB / {{ number_format($service->disk_limit) }} MB</span>
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
                <span style="color:#777;">{{ __('admin.services.bandwidth') }}</span>
                <span style="font-weight:600;">{{ number_format($service->bw_usage) }} MB / {{ number_format($service->bw_limit) }} MB</span>
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
    <div class="card-header"><strong>{{ __('admin.services.module_actions') }}</strong></div>
    <div class="card-body">
        @if(!$service->product?->server_type)
        <p style="font-size:13px;color:#999;">{{ __('admin.services.no_module') }}</p>
        @else
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px;">
            <form method="POST" action="{{ route('admin.services.module-action', [$service, 'create']) }}" onsubmit="return confirm('{{ __('admin.services.confirm_create') }}')">
                @csrf <button type="submit" class="btn btn-success btn-sm">{{ __('admin.services.create_account') }}</button>
            </form>
            <form method="POST" action="{{ route('admin.services.module-action', [$service, 'suspend']) }}" onsubmit="return confirm('{{ __('admin.services.confirm_suspend') }}')">
                @csrf <button type="submit" class="btn btn-warning btn-sm">{{ __('admin.services.suspend') }}</button>
            </form>
            <form method="POST" action="{{ route('admin.services.module-action', [$service, 'unsuspend']) }}" onsubmit="return confirm('{{ __('admin.services.confirm_unsuspend') }}')">
                @csrf <button type="submit" class="btn btn-primary btn-sm">{{ __('admin.services.unsuspend') }}</button>
            </form>
            <form method="POST" action="{{ route('admin.services.module-action', [$service, 'terminate']) }}" onsubmit="return confirm('{{ __('admin.services.confirm_terminate') }}')">
                @csrf <button type="submit" class="btn btn-danger btn-sm">{{ __('admin.services.terminate') }}</button>
            </form>
            <form method="POST" action="{{ route('admin.services.module-action', [$service, 'changepassword']) }}" style="display:flex;gap:4px;">
                @csrf
                <input type="password" name="password" placeholder="{{ __('admin.services.new_password') }}" required minlength="6" class="form-control" style="width:150px;font-size:13px;">
                <button type="submit" class="btn btn-default btn-sm">{{ __('admin.services.change_password') }}</button>
            </form>
        </div>
        <p style="font-size:11px;color:#999;">{{ __('admin.services.module_label') }}: <span style="font-family:monospace;">{{ $service->product?->server_type }}</span></p>
        @endif
    </div>
</div>

@if($service->notes)
<div class="card">
    <div class="card-header"><strong>{{ __('admin.services.notes') }}</strong></div>
    <div class="card-body" style="font-size:13px;white-space:pre-wrap;color:#555;">{{ $service->notes }}</div>
</div>
@endif

<div class="card">
    <div class="card-header"><strong>{{ __('admin.addons.title') }}</strong></div>
    <div class="card-body">
        @if($service->addons->isNotEmpty())
        <table class="data-table" style="margin-bottom:16px;">
            <thead><tr>
                <th>{{ __('common.table.name') }}</th>
                <th>{{ __('common.table.amount') }}</th>
                <th>{{ __('common.table.billing_cycle') }}</th>
                <th>{{ __('client.services.next_due_date') }}</th>
                <th>{{ __('common.table.status') }}</th>
                <th style="text-align:right;">{{ __('common.table.actions') }}</th>
            </tr></thead>
            <tbody>
            @foreach($service->addons as $addon)
            <tr>
                <td>{{ $addon->label() }}</td>
                <td>{{ number_format((float) $addon->amount, 2) }}</td>
                <td style="text-transform:capitalize;">{{ $addon->billing_cycle }}</td>
                <td>{{ $addon->next_due_date?->format(date_fmt()) ?? '-' }}</td>
                <td><span class="badge badge-{{ strtolower($addon->status) }}">{{ $addon->status }}</span></td>
                <td style="text-align:right;">
                    @if(in_array(strtolower($addon->status), ['active', 'pending'], true))
                    <form method="POST" action="{{ route('admin.services.addons.cancel', [$service, $addon]) }}" style="display:inline;">
                        @csrf
                        <button type="submit" class="btn btn-danger btn-xs">{{ __('client.services.addon_cancel') }}</button>
                    </form>
                    @endif
                </td>
            </tr>
            @endforeach
            </tbody>
        </table>
        @endif

        @if(($availableAddons ?? collect())->isNotEmpty())
        <form method="POST" action="{{ route('admin.services.addons.store', $service) }}" style="display:flex;gap:8px;align-items:center;">
            @csrf
            <select name="addon_id" class="form-control" style="max-width:320px;">
                @foreach($availableAddons as $available)
                    <option value="{{ $available->id }}">{{ $available->name }} ({{ number_format($available->priceFor($service->billing_cycle ?: 'Monthly'), 2) }})</option>
                @endforeach
            </select>
            <button type="submit" class="btn btn-primary btn-sm">{{ __('client.services.addon_order') }}</button>
        </form>
        @else
            <p style="color:#999;margin:0;">{{ __('admin.addons.no_addons') }}</p>
        @endif
    </div>
</div>

@endsection
