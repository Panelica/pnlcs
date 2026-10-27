@extends('admin.layouts.app')
@section('title', __('proxmox.capacity.title', ['name' => $server->name]))
@section('content')
@php
    $gib = fn (int $bytes) => number_format($bytes / 1073741824, $bytes >= 107374182400 ? 0 : 1).' GB';
    $line = (int) $overview['threshold'];
@endphp
<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;">
    <h1>{{ __('proxmox.capacity.title', ['name' => $server->name]) }}</h1>
    <a href="{{ route('admin.config.servers') }}" class="btn btn-default btn-sm">&larr; {{ __('proxmox.images.back') }}</a>
</div>

<style>
    .pcap-note{font-size:13px;color:#555;margin:0 0 14px;line-height:1.6}
    .pcap-bar{height:8px;border-radius:99px;background:#e5e7eb;overflow:hidden;min-width:120px}
    .pcap-bar span{display:block;height:100%;background:#2563eb}
    .pcap-bar.warn span{background:#d97706}
    .pcap-bar.over span{background:#dc2626}
    .pcap-share{font-weight:600;white-space:nowrap}
    .pcap-share.over{color:#b91c1c}
    .pcap-muted{color:#888;font-size:12px}
</style>

<p class="pcap-note">{{ __('proxmox.capacity.intro') }}
    @if($line > 0) {{ __('proxmox.capacity.line', ['line' => $line]) }} @else {{ __('proxmox.capacity.line_off') }} @endif
</p>

@if(! $overview['ok'])
    <div class="alert alert-danger">{{ __('proxmox.capacity.error', ['error' => $overview['error'] ?? '']) }}</div>
@elseif(empty($overview['nodes']))
    <div class="alert">{{ __('proxmox.capacity.none') }}</div>
@endif

@foreach($overview['nodes'] as $node)
<div class="card" style="margin-bottom:15px;">
    <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">
        <div><strong>{{ $node['node'] }}</strong>
            @unless($node['online'])<span class="badge badge-danger">{{ __('proxmox.capacity.offline') }}</span>@endunless
        </div>
        <span class="pcap-muted">{{ __('proxmox.capacity.guests', ['running' => $node['guests']['running'], 'stopped' => $node['guests']['stopped'], 'ours' => $node['guests']['ours']]) }}</span>
    </div>
    <div class="card-body" style="padding:0;">
        <table class="table" style="margin:0;">
            <thead><tr>
                <th>{{ __('proxmox.capacity.resource') }}</th>
                <th>{{ __('proxmox.capacity.total') }}</th>
                <th>{{ __('proxmox.capacity.given') }}</th>
                <th>{{ __('proxmox.capacity.ours') }}</th>
                <th>{{ __('proxmox.capacity.free') }}</th>
                <th style="min-width:160px;">{{ __('proxmox.capacity.share') }}</th>
                <th>{{ __('proxmox.capacity.now') }}</th>
            </tr></thead>
            <tbody>
            @foreach(['cpu', 'memory', 'disk'] as $r)
                @php
                    $v = $node[$r];
                    $share = $node['share'][$r];
                    $alerted = in_array($r, \Modules\Servers\Proxmox\ProxmoxCapacity::ALERTED, true);
                    $state = $share === null || ! $alerted || $line <= 0 ? '' : ($share >= $line ? 'over' : ($share >= $line * 0.85 ? 'warn' : ''));
                    $fmt = $r === 'cpu' ? fn ($n) => __('proxmox.capacity.threads', ['n' => $n]) : $gib;
                @endphp
                <tr>
                    <td><strong>{{ __('proxmox.capacity.'.$r) }}</strong></td>
                    <td>{{ $v['total'] > 0 ? $fmt($v['total']) : '—' }}</td>
                    <td>{{ $fmt($v['given']) }}</td>
                    <td>{{ $fmt($v['ours']) }}</td>
                    <td>{{ $v['total'] > 0 ? $fmt(max(0, $v['total'] - $v['given'])) : '—' }}</td>
                    <td>
                        @if($share === null) — @else
                        <div style="display:flex;align-items:center;gap:8px;">
                            <div class="pcap-bar {{ $state }}"><span style="width:{{ min(100, $share) }}%"></span></div>
                            <span class="pcap-share {{ $state === 'over' ? 'over' : '' }}">{{ $share }}%</span>
                        </div>
                        @endif
                    </td>
                    <td class="pcap-muted">{{ $r === 'cpu' ? __('proxmox.capacity.load', ['n' => $v['load']]) : $gib($v['used']) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        @if(count($node['storage']) > 1)
        <div class="pcap-muted" style="padding:8px 12px;">{{ __('proxmox.capacity.storage') }}:
            @foreach($node['storage'] as $s){{ $s['storage'] }} {{ $gib($s['used']) }} / {{ $gib($s['total']) }}@if(! $loop->last) · @endif @endforeach
        </div>
        @endif
    </div>
</div>
@endforeach

@if($ipv4['pool'])
<div class="card" style="margin-bottom:15px;">
    <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">
        <strong>{{ __('proxmox.capacity.ipv4_title') }}</strong>
        <span class="pcap-muted">{{ __('proxmox.capacity.ipv4_summary', ['used' => count($ipv4['holders']), 'free' => $ipv4['free']]) }}</span>
    </div>
    <div class="card-body" style="padding:0;">
        @if(empty($ipv4['holders']))
        <p class="pcap-muted" style="padding:10px 12px;margin:0;">{{ __('proxmox.capacity.ipv4_none') }}</p>
        @else
        <table class="table" style="margin:0;">
            <thead><tr>
                <th>{{ __('proxmox.capacity.ipv4_address') }}</th>
                <th>{{ __('proxmox.capacity.ipv4_kind') }}</th>
                <th>{{ __('proxmox.capacity.ipv4_service') }}</th>
                <th>{{ __('proxmox.capacity.ipv4_client') }}</th>
            </tr></thead>
            <tbody>
            @foreach($ipv4['holders'] as $held)
                <tr>
                    <td><code>{{ $held['address'] }}</code></td>
                    <td>{{ __('proxmox.capacity.ipv4_kind_'.$held['kind']) }}</td>
                    <td><a href="{{ route('admin.services.show', $held['service']->id) }}">#{{ $held['service']->id }}</a> {{ $held['service']->domain }} <span class="pcap-muted">{{ $held['service']->status }}</span></td>
                    <td>@if($held['service']->client)<a href="{{ route('admin.clients.show', $held['service']->client_id) }}">{{ $held['service']->client->display_name }}</a>@endif</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        @endif
    </div>
</div>
@endif

@if(! empty($overview['services']))
<p class="pcap-muted">{{ __('proxmox.capacity.services') }}:
    @foreach($overview['services'] as $status => $n){{ $status }} {{ $n }}@if(! $loop->last) · @endif @endforeach
</p>
@endif
@endsection
