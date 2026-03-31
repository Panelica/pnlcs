@extends('client.layouts.app')
@section('title', $domain->domain ?? 'Domain')
@section('styles')
<style>
    .detail-row { display: flex; justify-content: space-between; align-items: center; padding: 7px 0; border-bottom: 1px solid #f0f0f0; font-size: 13px; }
    .detail-row:last-child { border-bottom: none; }
    .detail-row dt { color: #777; }
    .detail-row dd { font-weight: 500; margin: 0; }
    .detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
    @media (max-width: 640px) { .detail-grid { grid-template-columns: 1fr; } }
</style>
@endsection
@section('content')

<div class="page-header">
    <div>
        <h1>{{ $domain->domain }}</h1>
    </div>
    <div style="display:flex; align-items:center; gap:8px;">
        <span class="badge badge-{{ strtolower($domain->status ?? 'active') }}">{{ ucfirst($domain->status ?? 'Active') }}</span>
        <a href="{{ route('client.domains.index') }}" class="btn btn-outline btn-sm">&larr; My Domains</a>
    </div>
</div>

<div class="detail-grid">
    <div class="pn-card">
        <div class="pn-card-header">Domain Information</div>
        <div class="pn-card-body">
            <dl>
                <div class="detail-row"><dt>Domain Name</dt><dd>{{ $domain->domain }}</dd></div>
                <div class="detail-row"><dt>Registration Date</dt><dd>{{ $domain->registration_date?->format('d M Y') ?? 'N/A' }}</dd></div>
                <div class="detail-row"><dt>Expiry Date</dt><dd>{{ $domain->expiry_date?->format('d M Y') ?? 'N/A' }}</dd></div>
                <div class="detail-row"><dt>Auto-Renew</dt><dd>{{ ($domain->auto_renew ?? false) ? 'Enabled' : 'Disabled' }}</dd></div>
                <div class="detail-row"><dt>ID Protection</dt><dd>{{ ($domain->id_protection ?? false) ? 'Enabled' : 'Disabled' }}</dd></div>
                <div class="detail-row"><dt>Registrar Lock</dt><dd>{{ $domain->status === 'Locked' ? 'Locked' : 'Unlocked' }}</dd></div>
            </dl>
        </div>
    </div>
    <div class="pn-card">
        <div class="pn-card-header">Nameservers</div>
        <div class="pn-card-body">
            @if(isset($domain->ns1))
            <dl>
                @foreach(['ns1', 'ns2', 'ns3', 'ns4', 'ns5'] as $ns)
                @if(!empty($domain->{$ns}))
                <div class="detail-row"><dt>{{ strtoupper($ns) }}</dt><dd style="font-family:monospace; font-size:12px;">{{ $domain->{$ns} }}</dd></div>
                @endif
                @endforeach
            </dl>
            @else
            <p style="font-size:13px; color:#999; margin:0;">Nameserver information not available.</p>
            @endif
        </div>
    </div>
</div>

@if($domain->dns_management ?? false)
<div class="pn-card" style="margin-bottom:20px;">
    <div class="pn-card-header">DNS Management</div>
    <div class="pn-card-body">
        <p style="font-size:13px; color:#555; margin-bottom:12px;">DNS management is enabled for this domain.</p>
        <a href="#" class="btn btn-primary btn-sm">Manage DNS Records &rarr;</a>
    </div>
</div>
@endif

{{-- EPP Code --}}
<div class="pn-card" style="margin-bottom:20px;">
    <div class="pn-card-header">Transfer Domain</div>
    <div class="pn-card-body">
        <p style="font-size:13px; color:#555; margin-bottom:12px;">Request your EPP/Auth code to transfer this domain to another registrar.</p>
        <form method="POST" action="{{ route('client.domains.epp', $domain) }}" style="display:inline;">
            @csrf
            <button type="submit" class="btn btn-outline btn-sm">Get EPP / Auth Code</button>
        </form>
        @if(session('epp_code'))
        <div style="margin-top:12px; padding:10px 14px; background:#f5f5f5; border:1px solid #e0e0e0; border-radius:4px; font-size:13px; font-family:monospace;">
            {{ session('epp_code') }}
        </div>
        @endif
    </div>
</div>

@endsection
