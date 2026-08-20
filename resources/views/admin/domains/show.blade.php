@extends('admin.layouts.app')
@section('title', $domain->domain)
@section('content')
<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;">
    <div>
        <h1 style="font-family:monospace;font-size:22px;">{{ $domain->domain }}</h1>
        <div style="font-size:13px;color:#777;margin-top:3px;">
            {{ ucfirst($domain->type) }} &mdash; {{ __('admin.domains.registrar') }}: {{ ucfirst($domain->registrar ?? 'N/A') }}
            @if($domain->client) &mdash; <a href="{{ route('admin.clients.show', $domain->client_id) }}" style="color:#337ab7;">{{ $domain->client->full_name }}</a>@endif
        </div>
    </div>
    <div style="display:flex;align-items:center;gap:8px;">
        @php $badgeClass = match(strtolower($domain->status)) { 'active'=>'badge-active', 'pending'=>'badge-pending', 'expired'=>'badge-overdue', 'grace'=>'badge-pending', 'redemption'=>'badge-overdue', default=>'badge-cancelled' }; @endphp
        <span class="{{ $badgeClass }}">{{ ucfirst($domain->status) }}</span>
        <a href="{{ route('admin.domains.index') }}" class="btn btn-default btn-sm">&larr; {{ __('admin.domains.back') }}</a>
    </div>
</div>

@if(session('epp_code'))
<div class="card" style="margin-bottom:15px;border-left:4px solid #f0ad4e;">
    <div class="card-header"><strong>{{ __('admin.domains.epp_code') }}</strong></div>
    <div class="card-body">
        <div style="font-family:monospace;font-size:16px;font-weight:700;background:#f5f5f5;padding:8px 12px;border:1px solid #e0e0e0;border-radius:4px;display:inline-block;">{{ session('epp_code') }}</div>
    </div>
</div>
@endif

<div class="card" style="margin-bottom:15px;">
    <div class="card-header"><strong>{{ __('admin.domains.actions') }}</strong></div>
    <div class="card-body" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
        <form method="POST" action="{{ route('admin.domains.sync', $domain) }}" style="display:inline;">
            @csrf
            <button type="submit" class="btn btn-primary btn-sm">{{ __('admin.domains.sync') }}</button>
        </form>
        <form method="POST" action="{{ route('admin.domains.renew', $domain) }}" style="display:flex;gap:4px;align-items:center;" onsubmit="return confirm('{{ __('admin.domains.confirm_renew') }}')">
            @csrf
            <input type="number" name="years" value="1" min="1" max="10" class="form-control" style="width:70px;font-size:13px;padding:2px 6px;">
            <button type="submit" class="btn btn-success btn-sm">{{ __('admin.domains.renew') }}</button>
        </form>
        <form method="POST" action="{{ route('admin.domains.lock', $domain) }}" style="display:inline;" onsubmit="return confirm('{{ __('admin.domains.confirm_lock') }}')">
            @csrf
            <button type="submit" class="btn btn-{{ $locked ? 'warning' : 'default' }} btn-sm">
                {{ $locked ? __('admin.domains.unlock') : __('admin.domains.lock') }}
            </button>
        </form>
        <form method="POST" action="{{ route('admin.domains.autorenew', $domain) }}" style="display:inline;">
            @csrf
            <button type="submit" class="btn btn-default btn-sm">{{ $domain->auto_renew ? __('admin.domains.autorenew_disable') : __('admin.domains.autorenew_enable') }}</button>
        </form>
        <a href="{{ route('admin.domains.epp', $domain) }}" class="btn btn-default btn-sm">{{ __('admin.domains.get_epp_code') }}</a>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;margin-bottom:15px;">
    <div class="panel">
        <div class="panel-heading panel-primary">{{ __('admin.domains.registration') }}</div>
        <div class="panel-body">
            <table style="width:100%;font-size:13px;border-collapse:collapse;">
                <tr><td style="padding:5px 0;color:#777;width:45%;">{{ __('admin.domains.registrar') }}</td><td style="padding:5px 0;">
                    <form method="POST" action="{{ route('admin.domains.registrar', $domain) }}" style="display:flex;gap:6px;align-items:center;">
                        @csrf
                        <select name="registrar" class="form-control" style="max-width:220px;font-size:13px;padding:3px 6px;">
                            @foreach($registrarOptions as $name)
                            <option value="{{ $name }}" {{ strtolower((string) $domain->registrar) === strtolower($name) ? 'selected' : '' }}>{{ $name }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn btn-primary btn-xs">{{ __('admin.domains.save') }}</button>
                    </form>
                </td></tr>
                <tr><td style="padding:5px 0;color:#777;">{{ __('admin.domains.registration_date') }}</td><td style="padding:5px 0;">{{ $domain->registration_date?->format(date_fmt()) ?? '-' }}</td></tr>
                <tr><td style="padding:5px 0;color:#777;">{{ __('admin.domains.expiry_date') }}</td><td style="padding:5px 0;{{ $domain->expiry_date?->isPast() ? 'color:#d9534f;font-weight:600;' : '' }}">{{ $domain->expiry_date?->format(date_fmt()) ?? '-' }}</td></tr>
                <tr><td style="padding:5px 0;color:#777;">{{ __('admin.domains.next_due_date') }}</td><td style="padding:5px 0;">{{ $domain->next_due_date?->format(date_fmt()) ?? '-' }}</td></tr>
                <tr><td style="padding:5px 0;color:#777;">{{ __('admin.domains.last_sync') }}</td><td style="padding:5px 0;">
                    @if($domain->last_sync_at)
                        {{ $domain->last_sync_at->format(datetime_fmt()) }}
                        @if($domain->last_sync_status === 'ok')
                        <span style="color:#2e7d32;font-weight:600;margin-left:6px;">{{ __('admin.domains.last_sync_ok') }}</span>
                        @else
                        <span style="color:#c62828;font-weight:600;margin-left:6px;">{{ __('admin.domains.last_sync_error') }}</span>
                        @endif
                    @else
                        -
                    @endif
                </td></tr>
                <tr><td style="padding:5px 0;color:#777;">{{ __('admin.domains.period') }}</td><td style="padding:5px 0;">{{ $domain->registration_period }} year(s)</td></tr>
                <tr><td style="padding:5px 0;color:#777;">{{ __('admin.domains.type') }}</td><td style="padding:5px 0;">{{ $domain->type }}</td></tr>
                <tr><td style="padding:5px 0;color:#777;">{{ __('admin.domains.registrar_lock') }}</td><td style="padding:5px 0;">{{ $locked === null ? '-' : ($locked ? __('admin.domains.enabled') : __('admin.domains.disabled')) }}</td></tr>
                @if($domain->order_id)
                <tr><td style="padding:5px 0;color:#777;">{{ __('admin.domains.order') }}</td><td style="padding:5px 0;"><a href="{{ route('admin.orders.show', $domain->order_id) }}" style="color:#337ab7;">#{{ $domain->order_id }}</a></td></tr>
                @endif
            </table>
        </div>
    </div>
    <div class="panel">
        <div class="panel-heading panel-primary">{{ __('admin.domains.billing') }}</div>
        <div class="panel-body">
            <table style="width:100%;font-size:13px;border-collapse:collapse;">
                <tr><td style="padding:5px 0;color:#777;width:45%;">{{ __('admin.domains.first_payment') }}</td><td style="padding:5px 0;font-weight:700;">{{ money_fmt($domain->first_payment_amount) }}</td></tr>
                <tr><td style="padding:5px 0;color:#777;">{{ __('admin.domains.recurring_amount') }}</td><td style="padding:5px 0;font-weight:700;">{{ money_fmt($domain->recurring_amount) }}/year</td></tr>
                <tr><td style="padding:5px 0;color:#777;">{{ __('admin.domains.payment_method') }}</td><td style="padding:5px 0;">{{ $domain->payment_method ? payment_method_label((string) $domain->payment_method) : '-' }}</td></tr>
                <tr><td style="padding:5px 0;color:#777;">{{ __('admin.domains.premium') }}</td><td style="padding:5px 0;">{{ $domain->is_premium ? 'Yes' : 'No' }}</td></tr>
            </table>
        </div>
    </div>
</div>

<div class="card" style="margin-bottom:15px;">
    <div class="card-header"><strong>{{ __('admin.domains.nameservers') }}</strong></div>
    <div class="card-body">
        @php $ns = is_array($domain->nameservers) ? $domain->nameservers : (json_decode($domain->nameservers ?? '[]', true) ?? []); @endphp
        <form method="POST" action="{{ route('admin.domains.nameservers', $domain) }}">
            @csrf
            @for($i = 1; $i <= 5; $i++)
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:5px;">
                <span style="font-size:12px;color:#777;width:34px;">NS{{ $i }}</span>
                <input type="text" name="ns[]" value="{{ $ns[$i-1] ?? '' }}" class="form-control" style="max-width:340px;font-family:monospace;font-size:13px;" placeholder="ns{{ $i }}.{{ $domain->domain }}">
            </div>
            @endfor
            <button type="submit" class="btn btn-primary btn-sm" style="margin-top:8px;">{{ __('admin.domains.update_nameservers') }}</button>
        </form>
    </div>
</div>

<div class="panel" style="margin-bottom:15px;">
    <div class="panel-heading panel-primary">{{ __('admin.domains.features') }}</div>
    <div class="panel-body" style="display:flex;gap:15px;flex-wrap:wrap;">
        @foreach(['dns_management'=>__('admin.domains.dns_management'),'email_forwarding'=>__('admin.domains.email_forwarding'),'id_protection'=>__('admin.domains.id_protection')] as $field=>$label)
        <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 12px;background:#f9f9f9;border:1px solid #e0e0e0;border-radius:4px;min-width:180px;">
            <span style="font-size:13px;font-weight:600;">{{ $label }}</span>
            <span class="{{ $domain->$field ? 'badge-active' : 'badge-cancelled' }}" style="margin-left:10px;">{{ $domain->$field ? 'Enabled' : 'Disabled' }}</span>
        </div>
        @endforeach
    </div>
</div>

@if($domain->notes)
<div class="panel">
    <div class="panel-heading panel-primary">{{ __('admin.domains.notes') }}</div>
    <div class="panel-body" style="font-size:13px;white-space:pre-wrap;color:#555;">{{ $domain->notes }}</div>
</div>
@endif

@endsection
