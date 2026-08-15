@extends('admin.layouts.app')
@section('title', $domain->domain)
@section('content')
<div class="page-header">
    <div>
        <h1 style="font-family:monospace;font-size:22px;">{{ $domain->domain }}</h1>
        <div style="font-size:13px;color:#777;margin-top:3px;">
            {{ ucfirst($domain->type) }} &mdash; Registrar: {{ ucfirst($domain->registrar ?? 'N/A') }}
            @if($domain->client) &mdash; <a href="{{ route('admin.clients.show', $domain->client_id) }}" style="color:#337ab7;">{{ $domain->client->full_name }}</a>@endif
        </div>
    </div>
    <div style="display:flex;align-items:center;gap:8px;">
        @php $badgeClass = match(strtolower($domain->status)) { 'active'=>'badge-active', 'pending'=>'badge-pending', 'expired'=>'badge-overdue', default=>'badge-cancelled' }; @endphp
        <span class="{{ $badgeClass }}">{{ ucfirst($domain->status) }}</span>
        <a href="{{ route('admin.domains.index') }}" class="btn btn-default btn-sm">&larr; {{ __('admin.domains.back') }}</a>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;margin-bottom:15px;">
    <div class="panel">
        <div class="panel-heading panel-primary">{{ __('admin.domains.registration') }}</div>
        <div class="panel-body">
            <table style="width:100%;font-size:13px;border-collapse:collapse;">
                <tr><td style="padding:5px 0;color:#777;width:45%;">{{ __('admin.domains.registration_date') }}</td><td style="padding:5px 0;">{{ $domain->registration_date?->format(date_fmt()) ?? '-' }}</td></tr>
                <tr><td style="padding:5px 0;color:#777;">{{ __('admin.domains.expiry_date') }}</td><td style="padding:5px 0;{{ $domain->expiry_date?->isPast() ? 'color:#d9534f;font-weight:600;' : '' }}">{{ $domain->expiry_date?->format(date_fmt()) ?? '-' }}</td></tr>
                <tr><td style="padding:5px 0;color:#777;">{{ __('admin.domains.next_due_date') }}</td><td style="padding:5px 0;">{{ $domain->next_due_date?->format(date_fmt()) ?? '-' }}</td></tr>
                <tr><td style="padding:5px 0;color:#777;">{{ __('admin.domains.period') }}</td><td style="padding:5px 0;">{{ $domain->registration_period }} year(s)</td></tr>
                <tr><td style="padding:5px 0;color:#777;">{{ __('admin.domains.type') }}</td><td style="padding:5px 0;">{{ $domain->type }}</td></tr>
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

<div class="panel" style="margin-bottom:15px;">
    <div class="panel-heading panel-primary">{{ __('admin.domains.nameservers') }}</div>
    <div class="panel-body">
        @php $ns = is_array($domain->nameservers) ? $domain->nameservers : (json_decode($domain->nameservers ?? '[]', true) ?? []); @endphp
        @if(count($ns) > 0)
        @foreach($ns as $i => $nameserver)
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:5px;">
            <span style="font-size:12px;color:#777;width:30px;">NS{{ $i+1 }}</span>
            <span style="font-family:monospace;font-size:13px;background:#f5f5f5;border:1px solid #e0e0e0;padding:3px 8px;border-radius:3px;">{{ $nameserver }}</span>
        </div>
        @endforeach
        @else
        <p style="font-size:13px;color:#999;">{{ __('admin.domains.no_nameservers') }}</p>
        @endif
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
