@extends("client.layouts.app")
@section("title", $service->product?->name ?? __("client.services.title"))
@section("content")

<style>
    .sv{--rad:18px;max-width:1180px;margin:0 auto}
    .sv a{text-decoration:none}
    .sv-back{display:inline-flex;align-items:center;gap:6px;color:var(--muted);font-size:13px;font-weight:600;margin-bottom:16px}
    .sv-back:hover{color:var(--primary)}

    /* Hero */
    .sv-hero{position:relative;overflow:hidden;border-radius:var(--rad);padding:30px 32px;margin-bottom:20px;
        background:linear-gradient(135deg,var(--primary) 0%,var(--primary-dark) 100%);color:#fff;
        box-shadow:0 12px 30px -8px color-mix(in srgb,var(--primary) 55%,transparent)}
    .sv-hero::after{content:"";position:absolute;right:-60px;top:-60px;width:240px;height:240px;border-radius:50%;background:rgba(255,255,255,.08)}
    .sv-hero::before{content:"";position:absolute;right:60px;bottom:-90px;width:200px;height:200px;border-radius:50%;background:rgba(255,255,255,.06)}
    .sv-hero-in{position:relative;z-index:1}
    .sv-hrow{display:flex;justify-content:space-between;align-items:flex-start;gap:18px;flex-wrap:wrap}
    .sv-htitle{display:flex;align-items:center;gap:16px}
    .sv-hicon{width:56px;height:56px;border-radius:16px;background:rgba(255,255,255,.16);backdrop-filter:blur(6px);display:flex;align-items:center;justify-content:center;font-size:28px;flex-shrink:0}
    .sv-htitle h1{margin:0;font-size:25px;font-weight:800;letter-spacing:-.6px;line-height:1.1}
    .sv-htitle .s{opacity:.85;font-size:14px;margin-top:3px}
    .sv-status{display:inline-flex;align-items:center;gap:7px;background:rgba(255,255,255,.18);padding:6px 14px;border-radius:999px;font-size:12.5px;font-weight:700;text-transform:capitalize}
    .sv-status .d{width:8px;height:8px;border-radius:50%;background:#4ade80;box-shadow:0 0 0 3px rgba(74,222,128,.3)}
    .sv-hact{display:flex;gap:10px;flex-wrap:wrap;margin-top:24px}
    .sv-b{display:inline-flex;align-items:center;gap:8px;padding:11px 20px;border-radius:11px;font-size:13.5px;font-weight:700;cursor:pointer;transition:all .15s;border:1px solid transparent}
    .sv-b-solid{background:#fff;color:var(--primary)}
    .sv-b-solid:hover{transform:translateY(-2px);box-shadow:0 8px 18px rgba(0,0,0,.2)}
    .sv-b-glass{background:rgba(255,255,255,.14);color:#fff;border-color:rgba(255,255,255,.25)}
    .sv-b-glass:hover{background:rgba(255,255,255,.24)}

    /* Resource strip */
    .sv-res{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:0;background:var(--card);border:1px solid var(--border);border-radius:var(--rad);overflow:hidden;margin-bottom:24px;box-shadow:var(--shadow)}
    .sv-metric{padding:18px 20px;border-right:1px solid var(--border)}
    .sv-metric:last-child{border-right:none}
    .sv-mtop{display:flex;align-items:center;gap:9px;margin-bottom:12px}
    .sv-mic{width:32px;height:32px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:17px}
    .sv-mlabel{font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.5px}
    .sv-mval{font-size:20px;font-weight:800;color:var(--text);line-height:1}
    .sv-mval small{font-size:12px;font-weight:600;color:var(--muted)}
    .sv-mbar{height:6px;border-radius:999px;background:var(--border);overflow:hidden;margin-top:11px}
    .sv-mfill{height:100%;border-radius:999px;width:0;transition:width .6s ease}

    /* App launcher */
    .sv-sec{font-size:12px;font-weight:800;color:var(--muted);text-transform:uppercase;letter-spacing:.7px;margin:0 2px 14px;display:flex;align-items:center;gap:8px}
    .sv-apps{display:grid;grid-template-columns:repeat(auto-fill,minmax(158px,1fr));gap:14px;margin-bottom:26px}
    .sv-app{position:relative;display:flex;flex-direction:column;gap:13px;padding:20px 18px;border:1px solid var(--border);border-radius:16px;background:var(--card);box-shadow:var(--shadow);transition:transform .16s,box-shadow .16s,border-color .16s;color:var(--text)}
    .sv-app.live:hover{transform:translateY(-4px);box-shadow:var(--shadow-md);border-color:color-mix(in srgb,var(--primary) 40%,var(--border))}
    .sv-app.soon{opacity:.62;cursor:default}
    .sv-aic{width:48px;height:48px;border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:25px}
    .sv-aname{font-size:14.5px;font-weight:700;line-height:1.2}
    .sv-adesc{font-size:11.5px;color:var(--muted);line-height:1.4}
    .sv-soon{position:absolute;top:12px;right:12px;font-size:9.5px;font-weight:800;text-transform:uppercase;letter-spacing:.5px;background:var(--bg);border:1px solid var(--border);color:var(--muted);padding:3px 8px;border-radius:999px}

    /* Info panels */
    .sv-g2{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:16px;margin-bottom:22px}
    .sv-panel{border:1px solid var(--border);border-radius:var(--rad);background:var(--card);box-shadow:var(--shadow);overflow:hidden}
    .sv-ph{padding:15px 20px;border-bottom:1px solid var(--border);font-size:13px;font-weight:800}
    .sv-dl{list-style:none;margin:0;padding:4px 20px}
    .sv-dl li{display:flex;justify-content:space-between;gap:12px;padding:11px 0;border-bottom:1px solid var(--border);font-size:13.5px}
    .sv-dl li:last-child{border-bottom:none}
    .sv-dl .k{color:var(--muted);font-weight:600}.sv-dl .v{font-weight:700;text-align:right}
    .sv-code{font-family:ui-monospace,Menlo,monospace;font-size:12.5px;background:var(--primary-light);color:var(--primary);padding:2px 9px;border-radius:6px}
    .sv-chip{display:inline-flex;align-items:center;gap:6px;font-family:ui-monospace,Menlo,monospace;font-size:12.5px;background:var(--bg);border:1px solid var(--border);color:var(--text);padding:6px 12px;border-radius:9px;margin:0 8px 8px 0}
    .sv-toggle{display:inline-flex;align-items:center;gap:7px;padding:5px 14px;font-size:12px;font-weight:700;border-radius:8px;cursor:pointer}
</style>

@php
    $st = strtolower((string) $service->status);
    $isPanelica = (($service->server?->type ?? $service->product?->server_type ?? '') === 'panelica');
    $feat = $hostingFeatures ?? [];
    // Full control-panel tool set. `route` null = not built yet (shown as Soon).
    $tools = [
        ['k'=>'files','name'=>__('client.hosting.files.title'),'desc'=>__('client.hosting.files.subtitle'),'ic'=>'ri-folder-open-line','c'=>'#3b82f6','route'=>route('client.services.files',$service)],
        ['k'=>'emails','name'=>__('client.hosting.email.title'),'desc'=>__('client.hosting.email.subtitle'),'ic'=>'ri-mail-line','c'=>'#8b5cf6','route'=>route('client.services.emails',$service)],
        ['k'=>'databases','name'=>'Databases','desc'=>'MySQL databases & users','ic'=>'ri-database-2-line','c'=>'#0ea5e9','route'=>route('client.services.databases',$service)],
        ['k'=>'ftp','name'=>'FTP Accounts','desc'=>'Manage FTP access','ic'=>'ri-folder-transfer-line','c'=>'#f59e0b','route'=>route('client.services.ftp',$service)],
        ['k'=>'subdomains','name'=>'Subdomains','desc'=>'Create subdomains','ic'=>'ri-node-tree','c'=>'#10b981','route'=>route('client.services.subdomains',$service)],
        ['k'=>'dns','name'=>__('client.hosting.dns.title'),'desc'=>__('client.hosting.dns.subtitle'),'ic'=>'ri-global-line','c'=>'#6366f1','route'=>route('client.services.dns',$service)],
        ['k'=>'cron','name'=>__('client.hosting.cron.title'),'desc'=>__('client.hosting.cron.subtitle'),'ic'=>'ri-time-line','c'=>'#ec4899','route'=>route('client.services.cron',$service)],
        ['k'=>'backups','name'=>__('client.hosting.backups.title'),'desc'=>__('client.hosting.backups.subtitle'),'ic'=>'ri-archive-2-line','c'=>'#64748b','route'=>route('client.services.backups',$service)],
    ];
@endphp

<div class="sv">
<a href="{{ route('client.services.index') }}" class="sv-back"><i class="ri-arrow-left-line"></i>{{ __('client.services.back_to_services') }}</a>

{{-- Hero --}}
<div class="sv-hero"><div class="sv-hero-in">
    <div class="sv-hrow">
        <div class="sv-htitle">
            <div class="sv-hicon"><i class="ri-server-line"></i></div>
            <div>
                <h1>{{ $service->domain ?: ($service->product?->name ?? __('client.services.title')) }}</h1>
                <div class="s">{{ $service->product?->name }} @if($service->domain)&middot; {{ ucfirst($service->billing_cycle) }}@endif</div>
            </div>
        </div>
        <span class="sv-status"><span class="d" style="{{ $st==='active'?'':'background:#fbbf24;box-shadow:0 0 0 3px rgba(251,191,36,.3)' }}"></span>{{ __('client.status.' . $st) }}</span>
    </div>
    @if($st === 'active')
    <div class="sv-hact">
        @if($isPanelica)<a href="{{ route('client.services.login', $service) }}" class="sv-b sv-b-solid"><i class="ri-external-link-line"></i>{{ __('client.services.login_to_panel') }}</a>@endif
        <a href="{{ route('client.services.upgrade', $service) }}" class="sv-b sv-b-glass"><i class="ri-arrow-up-down-line"></i>{{ __('client.services.upgrade_downgrade') }}</a>
        <a href="{{ route('client.services.cancel', $service) }}" class="sv-b sv-b-glass"><i class="ri-close-circle-line"></i>{{ __('client.services.request_cancellation') }}</a>
    </div>
    @endif
</div></div>

{{-- Resource strip --}}
@if($isPanelica && $st === 'active')
<div class="sv-res">
    <div class="sv-metric" id="m-cpu" style="display:none">
        <div class="sv-mtop"><span class="sv-mic" style="background:rgba(99,102,241,.14);color:#6366f1"><i class="ri-cpu-line"></i></span><span class="sv-mlabel">{{ __('client.hosting.dashboard.cpu') }}</span></div>
        <div class="sv-mval"><span data-v>0</span><small>%</small></div>
        <div class="sv-mbar"><div class="sv-mfill" data-bar style="background:#6366f1"></div></div>
    </div>
    <div class="sv-metric" id="m-ram" style="display:none">
        <div class="sv-mtop"><span class="sv-mic" style="background:rgba(236,72,153,.14);color:#ec4899"><i class="ri-ram-line"></i></span><span class="sv-mlabel">{{ __('client.hosting.dashboard.ram') }}</span></div>
        <div class="sv-mval"><span data-v>0</span> <small data-sub></small></div>
        <div class="sv-mbar"><div class="sv-mfill" data-bar style="background:#ec4899"></div></div>
    </div>
    <div class="sv-metric" id="m-disk">
        <div class="sv-mtop"><span class="sv-mic" style="background:rgba(59,130,246,.14);color:#3b82f6"><i class="ri-hard-drive-2-line"></i></span><span class="sv-mlabel">{{ __('client.services.disk_usage') }}</span></div>
        <div class="sv-mval"><span data-v>&hellip;</span></div>
        <div class="sv-mbar"><div class="sv-mfill" data-bar style="background:#3b82f6"></div></div>
    </div>
    <div class="sv-metric" id="m-bw">
        <div class="sv-mtop"><span class="sv-mic" style="background:rgba(16,185,129,.14);color:#10b981"><i class="ri-exchange-line"></i></span><span class="sv-mlabel">{{ __('client.services.bandwidth_usage') }}</span></div>
        <div class="sv-mval"><span data-v>&hellip;</span> <small>MB</small></div>
        <div class="sv-mbar" style="visibility:hidden"><div class="sv-mfill"></div></div>
    </div>
    <div class="sv-metric" id="m-sites">
        <div class="sv-mtop"><span class="sv-mic" style="background:rgba(100,116,139,.14);color:#64748b"><i class="ri-global-line"></i></span><span class="sv-mlabel">{{ __('client.hosting.dashboard.domains') }}</span></div>
        <div class="sv-mval"><span data-v>&hellip;</span></div>
        <div class="sv-mbar" style="visibility:hidden"><div class="sv-mfill"></div></div>
    </div>
</div>
@endif

{{-- App launcher --}}
@if(!empty($feat) && $st === 'active')
<div class="sv-sec"><i class="ri-apps-2-line"></i>{{ __('client.hosting.title') }}</div>
<div class="sv-apps">
    @foreach($tools as $t)
        @php($live = $t['route'] && in_array($t['k'], $feat, true))
        @if($live)
        <a href="{{ $t['route'] }}" class="sv-app live">
            <span class="sv-aic" style="background:{{ $t['c'] }}1f;color:{{ $t['c'] }}"><i class="{{ $t['ic'] }}"></i></span>
            <div><div class="sv-aname">{{ $t['name'] }}</div><div class="sv-adesc">{{ $t['desc'] }}</div></div>
        </a>
        @else
        <div class="sv-app soon">
            <span class="sv-soon">Soon</span>
            <span class="sv-aic" style="background:{{ $t['c'] }}1a;color:{{ $t['c'] }}"><i class="{{ $t['ic'] }}"></i></span>
            <div><div class="sv-aname">{{ $t['name'] }}</div><div class="sv-adesc">{{ $t['desc'] }}</div></div>
        </div>
        @endif
    @endforeach
</div>
@endif

{{-- Details --}}
<div class="sv-g2">
    <div class="sv-panel">
        <div class="sv-ph">{{ __('client.services.service_details') }}</div>
        <ul class="sv-dl">
            <li><span class="k">{{ __('client.cart.product') }}</span><span class="v">{{ $service->product?->name ?? '—' }}</span></li>
            <li><span class="k">{{ __('client.cart.billing_cycle') }}</span><span class="v" style="text-transform:capitalize">{{ $service->billing_cycle ?? '—' }}</span></li>
            <li><span class="k">{{ __('client.services.amount') }}</span><span class="v">{{ money_fmt($service->amount) }} / {{ $service->billing_cycle }}</span></li>
            <li><span class="k">{{ __('client.services.next_due_date') }}</span><span class="v">{{ $service->next_due_date?->format(date_fmt()) ?? '—' }}</span></li>
            <li><span class="k">{{ __('client.services.registration_date') }}</span><span class="v">{{ $service->registration_date?->format(date_fmt()) ?? '—' }}</span></li>
            <li><span class="k">{{ __('client.services.auto_renew') }}</span><span class="v">
                <form method="POST" action="{{ route('client.services.autorenew', $service) }}" style="display:inline">@csrf
                    <button type="submit" class="sv-toggle" style="border:1px solid {{ $service->auto_renew?'#10b981':'var(--border)' }};background:{{ $service->auto_renew?'rgba(16,185,129,.1)':'var(--bg)' }};color:{{ $service->auto_renew?'#059669':'var(--muted)' }}">
                        <span style="width:8px;height:8px;border-radius:50%;background:{{ $service->auto_renew?'#10b981':'var(--muted)' }}"></span>{{ $service->auto_renew?__('client.status.enabled'):__('client.status.disabled') }}
                    </button>
                </form></span></li>
        </ul>
    </div>
    <div class="sv-panel">
        <div class="sv-ph">{{ __('client.services.server_info') }}</div>
        <ul class="sv-dl">
            <li><span class="k">{{ __('client.services.server') }}</span><span class="v">{{ $service->server->name ?? '—' }}</span></li>
            <li><span class="k">{{ __('client.services.username') }}</span><span class="v"><span class="sv-code">{{ $service->username ?? '—' }}</span></span></li>
            @if($service->server?->hostname)<li><span class="k">{{ __('client.services.hostname') }}</span><span class="v">{{ $service->server->hostname }}</span></li>@endif
            @if($service->server?->ip)<li><span class="k">{{ __('client.services.ip_address') }}</span><span class="v"><span class="sv-code">{{ $service->server->ip }}</span></span></li>@endif
        </ul>
    </div>
</div>

@if($service->addons && $service->addons->count())
<div class="sv-panel" style="margin-bottom:22px">
    <div class="sv-ph">{{ __('client.services.addons') }}</div>
    <div style="overflow-x:auto"><table class="pn-table">
        <thead><tr><th>{{ __('common.table.name') }}</th><th>{{ __('common.table.amount') }}</th><th>{{ __('common.table.billing_cycle') }}</th><th>{{ __('client.services.next_due_date') }}</th><th>{{ __('common.table.status') }}</th><th style="text-align:right">{{ __('common.table.actions') }}</th></tr></thead>
        <tbody>@foreach($service->addons as $addon)<tr>
            <td>{{ $addon->label() }}</td><td>{{ money_fmt($addon->amount) }}</td><td style="text-transform:capitalize">{{ $addon->billing_cycle }}</td>
            <td class="text-muted text-sm">{{ $addon->next_due_date?->format(date_fmt()) ?? '-' }}</td>
            <td><span class="badge badge-{{ strtolower($addon->status) }}">{{ __('client.status.' . strtolower($addon->status)) }}</span></td>
            <td style="text-align:right">@if(in_array(strtolower($addon->status),['active','pending'],true))<form method="POST" action="{{ route('client.services.addons.cancel',[$service,$addon]) }}" onsubmit="return confirm('{{ __('client.services.addon_cancel_confirm') }}')">@csrf<button type="submit" class="pn-btn pn-btn-sm pn-btn-danger">{{ __('client.services.addon_cancel') }}</button></form>@endif</td>
        </tr>@endforeach</tbody>
    </table></div>
</div>
@endif

@if(($availableAddons ?? collect())->isNotEmpty())
<div class="sv-panel" style="margin-bottom:22px">
    <div class="sv-ph">{{ __('client.services.addons_available') }}</div>
    <div style="padding:6px 20px">@foreach($availableAddons as $available)
        <form method="POST" action="{{ route('client.services.addons.store',$service) }}" style="display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 0;border-bottom:1px solid var(--border)">@csrf
            <input type="hidden" name="addon_id" value="{{ $available->id }}">
            <span><strong>{{ $available->name }}</strong>@if($available->description)<br><small class="text-muted">{{ $available->description }}</small>@endif</span>
            <span style="white-space:nowrap">{{ money_fmt($available->priceFor($service->billing_cycle ?: 'Monthly')) }} <button type="submit" class="pn-btn pn-btn-sm">{{ __('client.services.addon_order') }}</button></span>
        </form>@endforeach</div>
</div>
@endif
</div>

@if($isPanelica && $st === 'active')
<script>
(function(){
    function set(id,val,sub){var c=document.getElementById(id);if(!c)return;c.style.display='';var v=c.querySelector('[data-v]');if(v)v.innerHTML=val;var s=c.querySelector('[data-sub]');if(s&&sub!=null)s.textContent=sub;}
    function bar(id,pct){var c=document.getElementById(id);if(!c)return;var b=c.querySelector('[data-bar]');if(!b)return;pct=Math.min(100,Math.max(0,pct));b.style.width=pct+'%';if(pct>=90)b.style.background='var(--danger)';else if(pct>=75)b.style.background='var(--warning)';}
    fetch("{{ route('client.services.usage', $service) }}",{headers:{'X-Requested-With':'XMLHttpRequest'}})
      .then(function(r){return r.json();}).then(function(u){
        if(!u||!u.available)return;
        if(u.cpu){set('m-cpu',(u.cpu.percent||0).toFixed(2));bar('m-cpu',u.cpu.percent||0);}
        if(u.ram){set('m-ram',(u.ram.used_mb||0).toLocaleString(),'/ '+(u.ram.limit_mb||0).toLocaleString()+' MB');bar('m-ram',u.ram.percent||0);}
        if(u.disk){var q=u.disk.quota_mb||0,us=u.disk.used_mb||0;set('m-disk',us.toLocaleString()+' <small>/ '+(q>0?q.toLocaleString()+' MB':'&infin;')+'</small>');if(q>0)bar('m-disk',us/q*100);}
        if(u.bandwidth)set('m-bw',(u.bandwidth.used_mb||0).toLocaleString());
        set('m-sites',(u.domains?u.domains.length:0));
      }).catch(function(){});
})();
</script>
@endif

@endsection
