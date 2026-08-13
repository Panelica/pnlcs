@extends("client.layouts.app")
@section("title", $service->product?->name ?? __("client.services.title"))
@section("content")

<style>
    .svc-wrap{--r:16px}
    .svc-back{display:inline-flex;align-items:center;gap:6px;color:var(--muted);text-decoration:none;font-size:13px;font-weight:600;margin-bottom:16px;transition:color .15s}
    .svc-back:hover{color:var(--primary)}
    .svc-hero{position:relative;overflow:hidden;border:1px solid var(--border);border-radius:var(--r);background:
        linear-gradient(135deg,var(--primary-light) 0%,var(--card) 55%);padding:26px 28px;margin-bottom:22px}
    .svc-hero-row{display:flex;justify-content:space-between;align-items:flex-start;gap:20px;flex-wrap:wrap}
    .svc-hero-icon{width:52px;height:52px;border-radius:14px;background:var(--primary);color:#fff;display:flex;align-items:center;justify-content:center;font-size:26px;flex-shrink:0;box-shadow:0 6px 16px rgba(26,77,128,.35)}
    .svc-hero h1{font-size:24px;font-weight:800;letter-spacing:-.5px;margin:0;color:var(--text)}
    .svc-hero .sub{color:var(--muted);font-size:14px;margin-top:2px}
    .svc-pill{display:inline-flex;align-items:center;gap:6px;padding:5px 13px;border-radius:999px;font-size:12px;font-weight:700;text-transform:capitalize}
    .svc-pill .dot{width:7px;height:7px;border-radius:50%}
    .svc-pill.ok{background:rgba(16,185,129,.12);color:#059669}.svc-pill.ok .dot{background:#10b981}
    .svc-pill.warn{background:rgba(245,158,11,.14);color:#b45309}.svc-pill.warn .dot{background:#f59e0b}
    .svc-pill.bad{background:rgba(239,68,68,.12);color:#dc2626}.svc-pill.bad .dot{background:#ef4444}
    .svc-hero-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:20px}
    .svc-btn{display:inline-flex;align-items:center;gap:7px;padding:10px 18px;border-radius:10px;font-size:13.5px;font-weight:700;text-decoration:none;border:1px solid transparent;cursor:pointer;transition:all .15s}
    .svc-btn-primary{background:var(--primary);color:#fff;box-shadow:0 4px 12px rgba(26,77,128,.28)}
    .svc-btn-primary:hover{background:var(--primary-dark);transform:translateY(-1px)}
    .svc-btn-ghost{background:var(--card);color:var(--text);border-color:var(--border)}
    .svc-btn-ghost:hover{border-color:var(--primary);color:var(--primary)}
    .svc-btn-danger{background:transparent;color:#dc2626;border-color:rgba(239,68,68,.4)}
    .svc-btn-danger:hover{background:rgba(239,68,68,.08)}

    .svc-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;margin-bottom:22px}
    .svc-stat{border:1px solid var(--border);border-radius:14px;background:var(--card);padding:16px 18px;box-shadow:var(--shadow)}
    .svc-stat-top{display:flex;align-items:center;gap:10px;margin-bottom:12px}
    .svc-stat-ic{width:34px;height:34px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px}
    .svc-stat-label{font-size:12px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.4px}
    .svc-stat-val{font-size:22px;font-weight:800;color:var(--text);line-height:1}
    .svc-stat-val small{font-size:12px;font-weight:600;color:var(--muted)}
    .svc-track{height:7px;border-radius:999px;background:var(--border);overflow:hidden;margin-top:12px}
    .svc-fill{height:100%;border-radius:999px;transition:width .5s ease}

    .svc-section-title{font-size:13px;font-weight:800;color:var(--muted);text-transform:uppercase;letter-spacing:.6px;margin:0 2px 12px}
    .svc-tools{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:14px;margin-bottom:24px}
    .svc-tool{display:flex;flex-direction:column;gap:12px;padding:20px;border:1px solid var(--border);border-radius:var(--r);background:var(--card);text-decoration:none;box-shadow:var(--shadow);transition:transform .15s,box-shadow .15s,border-color .15s}
    .svc-tool:hover{transform:translateY(-3px);box-shadow:var(--shadow-md);border-color:var(--primary)}
    .svc-tool-ic{width:46px;height:46px;border-radius:13px;display:flex;align-items:center;justify-content:center;font-size:24px}
    .svc-tool-name{font-size:14.5px;font-weight:700;color:var(--text)}
    .svc-tool-desc{font-size:12px;color:var(--muted);line-height:1.4}

    .svc-grid2{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:16px;margin-bottom:22px}
    .svc-panel{border:1px solid var(--border);border-radius:var(--r);background:var(--card);box-shadow:var(--shadow);overflow:hidden}
    .svc-panel-h{padding:14px 18px;border-bottom:1px solid var(--border);font-size:13px;font-weight:800;color:var(--text);letter-spacing:.2px}
    .svc-dl{list-style:none;margin:0;padding:6px 18px}
    .svc-dl li{display:flex;justify-content:space-between;gap:12px;padding:10px 0;border-bottom:1px solid var(--border);font-size:13.5px}
    .svc-dl li:last-child{border-bottom:none}
    .svc-dl .k{color:var(--muted);font-weight:600}
    .svc-dl .v{color:var(--text);font-weight:600;text-align:right}
    .svc-code{font-family:ui-monospace,Menlo,Consolas,monospace;font-size:12.5px;background:var(--primary-light);color:var(--primary);padding:2px 8px;border-radius:6px}
    .svc-chip{display:inline-block;font-family:ui-monospace,Menlo,Consolas,monospace;font-size:12px;background:var(--bg);border:1px solid var(--border);color:var(--text);padding:3px 10px;border-radius:7px;margin:3px 3px 0 0}
    .svc-toggle{display:inline-flex;align-items:center;gap:7px;padding:5px 14px;font-size:12px;font-weight:700;border-radius:8px;cursor:pointer;transition:all .15s}
</style>

<div class="svc-wrap">
@php
    $st = strtolower((string) $service->status);
    $pill = in_array($st, ['active']) ? 'ok' : (in_array($st, ['terminated','cancelled','fraud','suspended']) ? 'bad' : 'warn');
    $isPanelica = (($service->server?->type ?? $service->product?->server_type ?? '') === 'panelica');
@endphp

<a href="{{ route('client.services.index') }}" class="svc-back"><i class="ri-arrow-left-line"></i>{{ __('client.services.back_to_services') }}</a>

{{-- Hero --}}
<div class="svc-hero">
    <div class="svc-hero-row">
        <div style="display:flex;gap:16px;align-items:center">
            <div class="svc-hero-icon"><i class="ri-server-line"></i></div>
            <div>
                <h1>{{ $service->domain ?: ($service->product?->name ?? __('client.services.title')) }}</h1>
                <div class="sub">{{ $service->product?->name }} @if($service->domain)&middot; {{ $service->billing_cycle }}@endif</div>
            </div>
        </div>
        <span class="svc-pill {{ $pill }}"><span class="dot"></span>{{ __('client.status.' . $st) }}</span>
    </div>
    @if($st === 'active')
    <div class="svc-hero-actions">
        @if($isPanelica)
        <a href="{{ route('client.services.login', $service) }}" class="svc-btn svc-btn-primary"><i class="ri-external-link-line"></i>{{ __('client.services.login_to_panel') }}</a>
        @endif
        <a href="{{ route('client.services.upgrade', $service) }}" class="svc-btn svc-btn-ghost"><i class="ri-arrow-up-down-line"></i>{{ __('client.services.upgrade_downgrade') }}</a>
        <a href="{{ route('client.services.cancel', $service) }}" class="svc-btn svc-btn-danger"><i class="ri-close-circle-line"></i>{{ __('client.services.request_cancellation') }}</a>
    </div>
    @endif
</div>

{{-- Live resource stats --}}
@if($isPanelica && $st === 'active')
<div class="svc-stats" id="svc-stats">
    <div class="svc-stat" id="st-cpu" style="display:none">
        <div class="svc-stat-top"><div class="svc-stat-ic" style="background:rgba(99,102,241,.14);color:#6366f1"><i class="ri-cpu-line"></i></div><span class="svc-stat-label">{{ __('client.hosting.dashboard.cpu') }}</span></div>
        <div class="svc-stat-val"><span data-v>0</span><small>%</small></div>
        <div class="svc-track"><div class="svc-fill" data-bar style="width:0;background:#6366f1"></div></div>
    </div>
    <div class="svc-stat" id="st-ram" style="display:none">
        <div class="svc-stat-top"><div class="svc-stat-ic" style="background:rgba(236,72,153,.14);color:#ec4899"><i class="ri-ram-line"></i></div><span class="svc-stat-label">{{ __('client.hosting.dashboard.ram') }}</span></div>
        <div class="svc-stat-val"><span data-v>0</span> <small data-sub></small></div>
        <div class="svc-track"><div class="svc-fill" data-bar style="width:0;background:#ec4899"></div></div>
    </div>
    <div class="svc-stat" id="st-disk">
        <div class="svc-stat-top"><div class="svc-stat-ic" style="background:rgba(59,130,246,.14);color:#3b82f6"><i class="ri-hard-drive-2-line"></i></div><span class="svc-stat-label">{{ __('client.services.disk_usage') }}</span></div>
        <div class="svc-stat-val"><span data-v>&hellip;</span></div>
        <div class="svc-track"><div class="svc-fill" data-bar style="width:0;background:#3b82f6"></div></div>
    </div>
    <div class="svc-stat" id="st-bw">
        <div class="svc-stat-top"><div class="svc-stat-ic" style="background:rgba(16,185,129,.14);color:#10b981"><i class="ri-exchange-line"></i></div><span class="svc-stat-label">{{ __('client.services.bandwidth_usage') }}</span></div>
        <div class="svc-stat-val"><span data-v>&hellip;</span> <small>MB</small></div>
    </div>
</div>
@endif

{{-- Hosting tools (app launcher) --}}
@if(!empty($hostingFeatures) && $st === 'active')
<div class="svc-section-title">{{ __('client.hosting.title') }}</div>
<div class="svc-tools">
    @if(in_array('files', $hostingFeatures, true))
    <a href="{{ route('client.services.files', $service) }}" class="svc-tool">
        <div class="svc-tool-ic" style="background:rgba(59,130,246,.13);color:#3b82f6"><i class="ri-folder-open-line"></i></div>
        <div><div class="svc-tool-name">{{ __('client.hosting.files.title') }}</div><div class="svc-tool-desc">{{ __('client.hosting.files.subtitle') }}</div></div>
    </a>
    @endif
    @if(in_array('emails', $hostingFeatures, true))
    <a href="{{ route('client.services.emails', $service) }}" class="svc-tool">
        <div class="svc-tool-ic" style="background:rgba(139,92,246,.13);color:#8b5cf6"><i class="ri-mail-line"></i></div>
        <div><div class="svc-tool-name">{{ __('client.hosting.email.title') }}</div><div class="svc-tool-desc">{{ __('client.hosting.email.subtitle') }}</div></div>
    </a>
    @endif
</div>
@endif

{{-- Websites (domains) --}}
@if($isPanelica && $st === 'active')
<div id="svc-sites-wrap" style="display:none">
    <div class="svc-section-title"><i class="ri-global-line" style="margin-right:6px"></i>{{ __('client.hosting.dashboard.domains') }}</div>
    <div class="svc-panel" style="margin-bottom:22px"><div style="padding:16px 18px" id="svc-sites"></div></div>
</div>
@endif

{{-- Details --}}
<div class="svc-grid2">
    <div class="svc-panel">
        <div class="svc-panel-h">{{ __('client.services.service_details') }}</div>
        <ul class="svc-dl">
            <li><span class="k">{{ __('client.cart.product') }}</span><span class="v">{{ $service->product?->name ?? '—' }}</span></li>
            <li><span class="k">{{ __('client.cart.billing_cycle') }}</span><span class="v" style="text-transform:capitalize">{{ $service->billing_cycle ?? '—' }}</span></li>
            <li><span class="k">{{ __('client.services.amount') }}</span><span class="v">{{ money_fmt($service->amount) }} / {{ $service->billing_cycle }}</span></li>
            <li><span class="k">{{ __('client.services.next_due_date') }}</span><span class="v">{{ $service->next_due_date?->format(date_fmt()) ?? '—' }}</span></li>
            <li><span class="k">{{ __('client.services.registration_date') }}</span><span class="v">{{ $service->registration_date?->format(date_fmt()) ?? '—' }}</span></li>
            <li>
                <span class="k">{{ __('client.services.auto_renew') }}</span>
                <span class="v">
                    <form method="POST" action="{{ route('client.services.autorenew', $service) }}" style="display:inline;">
                        @csrf
                        <button type="submit" class="svc-toggle" style="border:1px solid {{ $service->auto_renew ? '#10b981' : 'var(--border)' }};background:{{ $service->auto_renew ? 'rgba(16,185,129,.1)' : 'var(--bg)' }};color:{{ $service->auto_renew ? '#059669' : 'var(--muted)' }}">
                            <span style="width:8px;height:8px;border-radius:50%;background:{{ $service->auto_renew ? '#10b981' : 'var(--muted)' }}"></span>
                            {{ $service->auto_renew ? __('client.status.enabled') : __('client.status.disabled') }}
                        </button>
                    </form>
                </span>
            </li>
        </ul>
    </div>
    <div class="svc-panel">
        <div class="svc-panel-h">{{ __('client.services.server_info') }}</div>
        <ul class="svc-dl">
            <li><span class="k">{{ __('client.services.server') }}</span><span class="v">{{ $service->server->name ?? '—' }}</span></li>
            <li><span class="k">{{ __('client.services.username') }}</span><span class="v"><span class="svc-code">{{ $service->username ?? '—' }}</span></span></li>
            @if($service->server?->hostname)
            <li><span class="k">{{ __('client.services.hostname') }}</span><span class="v">{{ $service->server->hostname }}</span></li>
            @endif
            @if($service->server?->ip)
            <li><span class="k">{{ __('client.services.ip_address') }}</span><span class="v"><span class="svc-code">{{ $service->server->ip }}</span></span></li>
            @endif
        </ul>
    </div>
</div>

{{-- Addons --}}
@if($service->addons && $service->addons->count())
<div class="svc-panel" style="margin-bottom:22px">
    <div class="svc-panel-h">{{ __('client.services.addons') }}</div>
    <div style="overflow-x:auto">
        <table class="pn-table">
            <thead><tr><th>{{ __('common.table.name') }}</th><th>{{ __('common.table.amount') }}</th><th>{{ __('common.table.billing_cycle') }}</th><th>{{ __('client.services.next_due_date') }}</th><th>{{ __('common.table.status') }}</th><th style="text-align:right;">{{ __('common.table.actions') }}</th></tr></thead>
            <tbody>
                @foreach($service->addons as $addon)
                <tr>
                    <td>{{ $addon->label() }}</td>
                    <td>{{ money_fmt($addon->amount) }}</td>
                    <td style="text-transform:capitalize">{{ $addon->billing_cycle }}</td>
                    <td class="text-muted text-sm">{{ $addon->next_due_date?->format(date_fmt()) ?? '-' }}</td>
                    <td><span class="badge badge-{{ strtolower($addon->status) }}">{{ __('client.status.' . strtolower($addon->status)) }}</span></td>
                    <td style="text-align:right;">
                        @if(in_array(strtolower($addon->status), ['active', 'pending'], true))
                        <form method="POST" action="{{ route('client.services.addons.cancel', [$service, $addon]) }}" onsubmit="return confirm('{{ __('client.services.addon_cancel_confirm') }}')">
                            @csrf
                            <button type="submit" class="pn-btn pn-btn-sm pn-btn-danger">{{ __('client.services.addon_cancel') }}</button>
                        </form>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

@if(($availableAddons ?? collect())->isNotEmpty())
<div class="svc-panel" style="margin-bottom:22px">
    <div class="svc-panel-h">{{ __('client.services.addons_available') }}</div>
    <div style="padding:8px 18px">
        @foreach($availableAddons as $available)
        <form method="POST" action="{{ route('client.services.addons.store', $service) }}" style="display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 0;border-bottom:1px solid var(--border);">
            @csrf
            <input type="hidden" name="addon_id" value="{{ $available->id }}">
            <span><strong>{{ $available->name }}</strong>@if($available->description)<br><small class="text-muted">{{ $available->description }}</small>@endif</span>
            <span style="white-space:nowrap;">{{ money_fmt($available->priceFor($service->billing_cycle ?: 'Monthly')) }}
                <button type="submit" class="pn-btn pn-btn-sm">{{ __('client.services.addon_order') }}</button></span>
        </form>
        @endforeach
    </div>
</div>
@endif
</div>

@if($isPanelica && $st === 'active')
<script>
(function(){
    function set(id,val,sub){var c=document.getElementById(id);if(!c)return;c.style.display='';var v=c.querySelector('[data-v]');if(v)v.innerHTML=val;var s=c.querySelector('[data-sub]');if(s&&sub!=null)s.textContent=sub;}
    function bar(id,pct){var c=document.getElementById(id);if(!c)return;var b=c.querySelector('[data-bar]');if(!b)return;pct=Math.min(100,Math.max(0,pct));b.style.width=pct+'%';b.style.background=pct>=90?'var(--danger)':(pct>=75?'var(--warning)':b.style.background);}
    function esc(s){return String(s).replace(/[&<>"]/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];});}
    fetch("{{ route('client.services.usage', $service) }}",{headers:{'X-Requested-With':'XMLHttpRequest'}})
      .then(function(r){return r.json();}).then(function(u){
        if(!u||!u.available)return;
        if(u.cpu){set('st-cpu',(u.cpu.percent||0).toFixed(2));bar('st-cpu',u.cpu.percent||0);}
        if(u.ram){set('st-ram',(u.ram.used_mb||0).toLocaleString(),'/ '+(u.ram.limit_mb||0).toLocaleString()+' MB &mdash; '+(u.ram.percent||0)+'%');bar('st-ram',u.ram.percent||0);}
        if(u.disk){var q=u.disk.quota_mb||0,us=u.disk.used_mb||0;set('st-disk',us.toLocaleString()+' <small>/ '+(q>0?q.toLocaleString()+' MB':'&infin;')+'</small>');if(q>0)bar('st-disk',us/q*100);}
        if(u.bandwidth)set('st-bw',(u.bandwidth.used_mb||0).toLocaleString());
        if(u.domains&&u.domains.length){var w=document.getElementById('svc-sites-wrap'),h=document.getElementById('svc-sites');if(h){h.innerHTML=u.domains.map(function(d){return '<span class="svc-chip"><i class="ri-global-line" style="margin-right:5px;opacity:.6"></i>'+esc(d)+'</span>';}).join('');w.style.display='';}}
      }).catch(function(){});
})();
</script>
@endif

@endsection
