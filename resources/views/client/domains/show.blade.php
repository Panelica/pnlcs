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
        <a href="{{ route('client.domains.index') }}" class="btn btn-outline btn-sm">&larr; {{ __('client.nav.my_domains') }}</a>
    </div>
</div>

<div class="detail-grid">
    <div class="pn-card">
        <div class="pn-card-header">{{ __('client.domains.domain_info') }}</div>
        <div class="pn-card-body">
            <dl>
                <div class="detail-row"><dt>{{ __('client.domains.domain_name') }}</dt><dd>{{ $domain->domain }}</dd></div>
                <div class="detail-row"><dt>{{ __('client.services.registration_date') }}</dt><dd>{{ $domain->registration_date?->format(date_fmt()) ?? 'N/A' }}</dd></div>
                <div class="detail-row"><dt>{{ __('client.domains.expiry_date') }}</dt><dd>{{ $domain->expiry_date?->format(date_fmt()) ?? 'N/A' }}</dd></div>
                <div class="detail-row"><dt>{{ __('client.services.auto_renew') }}</dt><dd>
                    {{ $domain->auto_renew ? __("client.status.enabled") : __("client.status.disabled") }}
                    <form method="POST" action="{{ route('client.domains.autorenew', $domain) }}" style="display:inline;margin-left:8px;">
                        @csrf
                        <button type="submit" class="btn btn-default btn-xs">{{ $domain->auto_renew ? __('client.domains.turn_off') : __('client.domains.turn_on') }}</button>
                    </form>
                </dd></div>
                <div class="detail-row"><dt>{{ __('client.domains.id_protection') }}</dt><dd>{{ ($domain->id_protection ?? false) ? __("client.status.enabled") : __("client.status.disabled") }}</dd></div>
                <div class="detail-row"><dt>{{ __('client.domains.registrar_lock') }}</dt><dd>{{ $locked === null ? __('client.status.unknown') : ($locked ? __("client.status.locked") : __("client.status.unlocked")) }}</dd></div>
            </dl>
        </div>
    </div>
    <div class="pn-card">
        <div class="pn-card-header">{{ __('client.domains.nameservers') }}</div>
        <div class="pn-card-body">
            {{-- The route and DomainController::updateNameservers() existed, but
                 no screen posted to them: the customer could read the
                 nameservers and not change them. Reported by ENA Hosting. --}}
            @php $ns = $domain->nameserverList(); @endphp
            <form method="POST" action="{{ route('client.domains.nameservers', $domain) }}">
                @csrf
                @method('PUT')
                @for($i = 1; $i <= 5; $i++)
                <div class="form-group" style="display:flex;align-items:center;gap:10px;margin-bottom:8px;">
                    <label for="ns{{ $i }}" style="font-size:12px;color:var(--muted);width:34px;flex-shrink:0;margin:0;">NS{{ $i }}</label>
                    <input type="text" id="ns{{ $i }}" name="ns{{ $i }}" value="{{ old('ns'.$i, $ns[$i-1] ?? '') }}"
                           class="form-control" style="font-family:monospace;font-size:12.5px;" autocomplete="off" spellcheck="false"
                           @if($i <= 2) required @else placeholder="{{ __('client.form.optional') }}" @endif>
                </div>
                @error('ns'.$i)<div class="text-danger text-sm" style="margin:-4px 0 8px 44px;">{{ $message }}</div>@enderror
                @endfor
                <p style="font-size:12px;color:var(--muted);margin:10px 0 12px;">{{ __('client.domains.ns_change_hint') }}</p>
                <button type="submit" class="btn btn-primary btn-sm">{{ __('client.domains.update_nameservers') }}</button>
            </form>

            {{-- One step: put the domain on one of the customer's hosting
                 accounts and point it there. Running it again is harmless. --}}
            @if(($hostings ?? collect())->isNotEmpty())
            @php
                $setUp = $hostings->firstWhere('set_up', true);
                $first = $setUp ?? $hostings->first();
            @endphp
            <div style="margin-top:14px;padding-top:14px;border-top:1px solid var(--border);">
                @if($setUp)
                <p style="font-size:12.5px;color:var(--success);font-weight:600;margin:0 0 10px;">&#10003; {{ __('client.domains.attach_already') }}</p>
                @endif
                <form method="POST" action="{{ route('client.domains.attach-hosting', $domain) }}">
                    @csrf
                    @if($hostings->count() > 1)
                    <div class="form-group" style="margin-bottom:10px;">
                        <label class="form-label" for="service_id">{{ __('client.domains.attach_choose_service') }}</label>
                        <select id="service_id" name="service_id" class="form-control">
                            @foreach($hostings as $h)
                            <option value="{{ $h['service']->id }}" @selected($h['service']->id === $first['service']->id)>{{ $h['service']->product?->name }}{{ $h['service']->domain ? ' - '.$h['service']->domain : '' }}</option>
                            @endforeach
                        </select>
                    </div>
                    @else
                    <input type="hidden" name="service_id" value="{{ $first['service']->id }}">
                    @endif
                    <button type="submit" class="btn btn-success btn-sm">{{ $setUp ? __('client.domains.attach_to_hosting_again') : __('client.domains.attach_to_hosting') }}</button>
                    <p style="font-size:12px;color:var(--muted);margin:8px 0 0;">
                        @if($setUp)
                            {{ __('client.domains.attach_again_hint') }}
                        @elseif($first['nameservers'] !== [])
                            {{ __('client.domains.attach_to_hosting_hint', ['ns' => implode(', ', $first['nameservers'])]) }}
                        @else
                            {{ __('client.domains.attach_to_hosting_hint_no_ns') }}
                        @endif
                    </p>
                </form>
            </div>
            @endif
        </div>
    </div>
</div>

@if($domain->dns_management ?? false)
<div class="pn-card" style="margin-bottom:20px;">
    <div class="pn-card-header">{{ __('client.domains.dns_management') }}</div>
    <div class="pn-card-body">
        <p style="font-size:13px; color:#555; margin-bottom:12px;">{{ __('client.domains.dns_enabled') }}</p>
        <a href="#" class="btn btn-primary btn-sm">{{ __('client.domains.manage_dns') }} &rarr;</a>
    </div>
</div>
@endif

{{-- EPP Code --}}
<div class="pn-card" style="margin-bottom:20px;">
    <div class="pn-card-header">{{ __('client.domains.transfer_domain') }}</div>
    <div class="pn-card-body">
        <p style="font-size:13px; color:#555; margin-bottom:12px;">{{ __('client.domains.epp_desc') }}</p>
        <a href="{{ route('client.domains.epp', $domain) }}" class="btn btn-outline btn-sm">{{ __('client.domains.get_epp_code') }}</a>
        @if(session('epp_code'))
        <div style="margin-top:12px; padding:10px 14px; background:var(--bg); border:1px solid #e0e0e0; border-radius:4px; font-size:13px; font-family:monospace;">
            {{ session('epp_code') }}
        </div>
        @endif
    </div>
</div>

@endsection
