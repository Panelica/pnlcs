@extends('client.layouts.app')
@section('title', __('client.hosting.'.$runtime.'.title'))
@section('content')

@php
    $meta = [
        'laravel' => ['ic' => 'ri-fire-line', 'c1' => '#ef4444', 'c2' => '#b91c1c'],
        'nodejs'  => ['ic' => 'ri-nodejs-line', 'c1' => '#22c55e', 'c2' => '#15803d'],
        'python'  => ['ic' => 'ri-terminal-box-line', 'c1' => '#3b82f6', 'c2' => '#1d4ed8'],
    ][$runtime] ?? ['ic' => 'ri-apps-2-line', 'c1' => '#0ea5e9', 'c2' => '#0284c7'];
@endphp

<style>
    .ra-back{display:inline-flex;align-items:center;gap:6px;color:var(--muted);text-decoration:none;font-size:13px;font-weight:600;margin-bottom:14px}
    .ra-back:hover{color:var(--primary)}
    .ra-head{display:flex;align-items:center;gap:14px;margin-bottom:18px}
    .ra-head-ic{width:48px;height:48px;border-radius:14px;color:#fff;display:flex;align-items:center;justify-content:center;font-size:25px}
    .ra-head h1{font-size:22px;font-weight:800;margin:0;letter-spacing:-.5px;color:var(--text)}
    .ra-head .sub{font-size:13px;color:var(--muted)}
    .ra-cnt{margin-left:auto;font-size:12px;font-weight:700;color:var(--muted);background:var(--bg);border:1px solid var(--border);padding:6px 13px;border-radius:999px}
    .ra-card{background:var(--card);border:1px solid var(--border);border-radius:14px;box-shadow:var(--shadow);overflow:hidden}
    .ra-tbl{width:100%;border-collapse:collapse}
    .ra-tbl th{text-align:left;font-size:12px;font-weight:700;color:var(--muted);padding:12px 16px;border-bottom:1px solid var(--border);background:var(--bg)}
    .ra-tbl td{padding:13px 16px;border-bottom:1px solid var(--border);font-size:13.5px;color:var(--text);vertical-align:middle}
    .ra-tbl tr:last-child td{border-bottom:none}
    .ra-name{font-weight:700;color:var(--text)}
    .ra-dom{font-family:ui-monospace,Menlo,monospace;font-size:12.5px;color:var(--muted)}
    .ra-ver{display:inline-block;font-size:11.5px;font-weight:700;color:var(--muted);background:var(--bg);border:1px solid var(--border);padding:3px 9px;border-radius:999px}
    .ra-st{display:inline-flex;align-items:center;gap:6px;font-size:12px;font-weight:700}
    .ra-st.on{color:#16a34a}.ra-st.off{color:var(--muted)}
    .ra-st .dot{width:7px;height:7px;border-radius:50%;background:currentColor}
    .ra-open{color:var(--primary);text-decoration:none;font-weight:600;font-size:12.5px}
    .ra-empty{padding:44px 20px;text-align:center;color:var(--muted)}
    .ra-empty i{font-size:34px;display:block;margin-bottom:12px;opacity:.5}
    .ra-empty p{margin:0 0 4px;font-size:14px;font-weight:600;color:var(--text)}
    .ra-empty span{font-size:12.5px}
</style>

<a href="{{ route('client.services.show', $service) }}" class="ra-back"><i class="ri-arrow-left-line"></i>{{ __('client.services.back_to_services') }}</a>

<div class="ra-head">
    <div class="ra-head-ic" style="background:linear-gradient(135deg,{{ $meta['c1'] }},{{ $meta['c2'] }})"><i class="{{ $meta['ic'] }}"></i></div>
    <div>
        <h1>{{ __('client.hosting.'.$runtime.'.title') }}</h1>
        <div class="sub">{{ __('client.hosting.'.$runtime.'.subtitle') }}</div>
    </div>
    @if(count($apps))<div class="ra-cnt">{{ count($apps) }}</div>@endif
</div>

<div class="ra-card">
    @if(count($apps))
    <table class="ra-tbl">
        <thead><tr>
            <th>{{ __('common.table.name') }}</th>
            <th>{{ __('client.hosting.runtime.domain') }}</th>
            <th>{{ __('client.hosting.runtime.version') }}</th>
            <th>{{ __('common.table.status') }}</th>
            <th></th>
        </tr></thead>
        <tbody>
        @foreach($apps as $app)
            <tr>
                <td><span class="ra-name">{{ $app['name'] ?: '—' }}</span></td>
                <td>@if($app['domain'])<span class="ra-dom">{{ $app['domain'] }}</span>@else<span class="ra-dom">—</span>@endif</td>
                <td>@if($app['version'])<span class="ra-ver">{{ $runtime === 'python' && $app['framework'] ? $app['version'].' · '.$app['framework'] : $app['version'] }}</span>@else—@endif</td>
                <td>
                    @php $on = in_array($app['status'], ['running', 'active', 'deployed', 'online'], true); @endphp
                    <span class="ra-st {{ $on ? 'on' : 'off' }}"><span class="dot"></span>{{ $app['status'] ? ucfirst($app['status']) : '—' }}</span>
                </td>
                <td style="text-align:right">
                    @if($app['url'])<a href="{{ $app['url'] }}" target="_blank" rel="noopener" class="ra-open">{{ __('client.hosting.runtime.open') }} <i class="ri-external-link-line"></i></a>@endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
    @else
    <div class="ra-empty">
        <i class="{{ $meta['ic'] }}"></i>
        <p>{{ __('client.hosting.runtime.empty_title') }}</p>
        <span>{{ __('client.hosting.runtime.empty_hint') }}</span>
    </div>
    @endif
</div>

@endsection
