{{-- The virtual server panel (Proxmox). The customer's service page and the
     admin's service page draw this same component, so both see the same
     numbers, charts and controls; staff get a few extra rows at the end.
     Everything live comes from the JSON routes in $urls. --}}
@props([
    'service',
    'urls',
    'admin' => false,
    'features' => [],
    'choices' => [],
    'canChange' => false,
])
@php
    $has = fn (string $f) => in_array($f, $features, true);
    $confirmWord = \App\Http\Controllers\Client\VpsController::confirmationWord($service);
    $pveData = $service->module_data ?? [];
    $proxmoxUrl = null;
    if ($admin && ! empty($pveData['proxmox_vmid']) && $service->server?->hostname) {
        $proxmoxUrl = 'https://'.$service->server->hostname.':'.($service->server->port ?: 8006)
            .'/#v1:0:='.rawurlencode(($pveData['proxmox_type'] ?? 'qemu').'/'.$pveData['proxmox_vmid']);
    }
    $icon = [
        'start' => '<polygon points="7 4 20 12 7 20 7 4"/>',
        'reboot' => '<path d="M21 12a9 9 0 1 1-2.64-6.36L21 8"/><path d="M21 3v5h-5"/>',
        'shutdown' => '<path d="M12 2v9"/><path d="M18.36 6.64a9 9 0 1 1-12.73 0"/>',
        'stop' => '<rect x="6" y="6" width="12" height="12" rx="1.5"/>',
        'reset' => '<path d="M13 2 4 14h8l-1 8 9-12h-8l1-8z"/>',
        'camera' => '<path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3z"/><circle cx="12" cy="13" r="3"/>',
        'backup' => '<path d="M21 8v12a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V8"/><rect x="1.5" y="3" width="21" height="5" rx="1"/><path d="M10 12h4"/>',
        'reinstall' => '<path d="M3 12a9 9 0 0 1 15.36-6.36L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-15.36 6.36L3 16"/><path d="M8 16H3v5"/>',
        'key' => '<circle cx="7.5" cy="15.5" r="4.5"/><path d="m10.7 12.3 9.8-9.8"/><path d="m16 7 3 3"/><path d="m19 4 2 2"/>',
        'external' => '<path d="M15 3h6v6"/><path d="M10 14 21 3"/><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>',
        'copy' => '<rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>',
    ];
    $svg = fn (string $name) => '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$icon[$name].'</svg>';
    $powerButtons = [
        'start' => '',
        'reboot' => '',
        'shutdown' => '',
        'stop' => 'danger',
        'reset' => 'danger',
    ];
@endphp
<style>
    .vps{--vps-line:var(--border,#e2e8f0);--vps-text:var(--text,#1e293b);--vps-muted:var(--muted,#64748b);--vps-card:var(--card,#fff);--vps-soft:var(--bg,#f8fafc);--vps-accent:var(--primary,var(--theme-primary,#1a4d80));--vps-accent-soft:var(--primary-light,#e8f0fb);--vps-ok:#16a34a;--vps-bad:#dc2626;--vps-r:12px;
        color:var(--vps-text);background:var(--vps-card);border:1px solid var(--vps-line);border-radius:var(--vps-r);overflow:hidden;margin-bottom:24px;font-size:13.5px;line-height:1.45}
    .vps *,.vps *::before,.vps *::after{box-sizing:border-box}
    .vps-sec{padding:18px 20px;border-top:1px solid var(--vps-line)}
    .vps-head{display:flex;justify-content:space-between;align-items:center;gap:14px;flex-wrap:wrap;padding:16px 20px}
    .vps-state{display:inline-flex;align-items:center;gap:9px;font-weight:800;font-size:15px}
    .vps-dot{width:10px;height:10px;border-radius:50%;background:var(--vps-muted);flex:none}
    .vps-dot.on{background:#22c55e;box-shadow:0 0 0 4px rgba(34,197,94,.18)}
    .vps-dot.off{background:#ef4444;box-shadow:0 0 0 4px rgba(239,68,68,.15)}
    .vps-dot.busy{background:#3b82f6;box-shadow:0 0 0 4px rgba(59,130,246,.18);animation:vps-pulse 1.2s infinite}
    @keyframes vps-pulse{50%{opacity:.35}}
    .vps-sub{font-size:12px;color:var(--vps-muted);margin-top:3px}
    .vps-btns{display:flex;gap:8px;flex-wrap:wrap}
    .vps-btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;padding:8px 13px;border-radius:9px;font:inherit;font-size:13px;font-weight:700;line-height:1.2;border:1px solid var(--vps-line);background:var(--vps-card);color:var(--vps-text);cursor:pointer;white-space:nowrap;text-decoration:none}
    .vps-btn:hover:not([disabled]){border-color:var(--vps-accent);color:var(--vps-accent)}
    .vps-btn[disabled]{opacity:.42;cursor:not-allowed}
    .vps-btn.danger{color:var(--vps-bad)}
    .vps-btn.danger:hover:not([disabled]){border-color:var(--vps-bad);color:var(--vps-bad)}
    .vps-btn.primary{background:var(--vps-accent);border-color:var(--vps-accent);color:#fff}
    .vps-btn.primary:hover:not([disabled]){filter:brightness(1.08);color:#fff}
    .vps-btn.solid-danger{background:var(--vps-bad);border-color:var(--vps-bad);color:#fff}
    .vps-btn.solid-danger:hover:not([disabled]){filter:brightness(1.08);color:#fff}
    .vps-btn.sm{padding:5px 10px;font-size:12px;border-radius:7px}
    .vps-note{margin:0 20px 14px;padding:10px 14px;border-radius:10px;font-size:13px;font-weight:600;display:none}
    .vps-note.ok{display:block;background:rgba(34,197,94,.1);color:#15803d}
    .vps-note.err{display:block;background:rgba(239,68,68,.1);color:#b91c1c}
    .vps-note.info{display:block;background:rgba(59,130,246,.1);color:#1d4ed8}
    .vps-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));border-top:1px solid var(--vps-line)}
    .vps-kpi{padding:16px 20px;border-inline-start:1px solid var(--vps-line);min-width:0}
    .vps-kpi:first-child{border-inline-start:none}
    .vps-kpi .l{font-size:11px;font-weight:700;color:var(--vps-muted);text-transform:uppercase;letter-spacing:.5px}
    .vps-kpi .v{font-size:20px;font-weight:800;margin-top:6px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-variant-numeric:tabular-nums}
    .vps-kpi .v small{font-size:12px;color:var(--vps-muted);font-weight:600}
    .vps-kpi .s{font-size:12px;color:var(--vps-muted);margin-top:6px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .vps-bar{height:6px;border-radius:99px;background:var(--vps-line);overflow:hidden;margin-top:9px}
    .vps-bar i{display:block;height:100%;width:0;background:var(--vps-accent);border-radius:99px;transition:width .5s,background .3s}
    .vps-bar.ghost{visibility:hidden}
    .vps-info{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));column-gap:32px}
    .vps-row{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid var(--vps-line);min-width:0}
    .vps-row .k{color:var(--vps-muted);font-weight:600;flex:none}
    .vps-row .v{font-weight:700;text-align:end;display:flex;gap:6px;flex-wrap:wrap;justify-content:flex-end;min-width:0}
    .vps-chip{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:12.5px;background:var(--vps-accent-soft);color:var(--vps-accent);padding:2px 9px;border-radius:6px;border:0;cursor:default;font-weight:600;max-width:100%;overflow:hidden;text-overflow:ellipsis}
    button.vps-chip{cursor:copy}
    :root[data-theme="dark"] .vps{--vps-accent-soft:rgba(59,130,246,.18)}
    :root[data-theme="dark"] .vps-chip{color:#93c5fd}
    .vps-h{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:14px}
    .vps-h h3{margin:0;font-size:15px;font-weight:800}
    .vps-h p{margin:3px 0 0;font-size:12px;color:var(--vps-muted)}
    .vps-seg{display:inline-flex;border:1px solid var(--vps-line);border-radius:9px;overflow:hidden;background:var(--vps-soft)}
    .vps-seg button{padding:6px 12px;font:inherit;font-size:12px;font-weight:700;border:0;background:transparent;color:var(--vps-muted);cursor:pointer}
    .vps-seg button+button{border-inline-start:1px solid var(--vps-line)}
    .vps-seg button.on{background:var(--vps-accent);color:#fff}
    .vps-charts{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}
    .vps-cc{border:1px solid var(--vps-line);border-radius:10px;padding:14px 14px 8px;min-width:0;background:var(--vps-card)}
    .vps-cc-h{display:flex;justify-content:space-between;align-items:baseline;gap:8px}
    .vps-cc-h .t{font-weight:800;font-size:13.5px}
    .vps-cc-h .pk{font-size:11.5px;color:var(--vps-muted);font-weight:600;font-variant-numeric:tabular-nums}
    .vps-lg{display:flex;gap:14px;flex-wrap:wrap;margin:6px 0 4px;font-size:12px;color:var(--vps-muted);min-height:18px}
    .vps-lg span{display:inline-flex;align-items:center;gap:6px;font-variant-numeric:tabular-nums}
    .vps-lg b{color:var(--vps-text);font-weight:700}
    .vps-lg i{width:9px;height:9px;border-radius:3px;display:inline-block}
    .vps-plot{position:relative;height:180px;direction:ltr;user-select:none;touch-action:pan-y}
    .vps-plot svg{display:block;width:100%;height:100%;overflow:visible}
    .vps-plot text{font-size:10.5px;fill:var(--vps-muted);font-variant-numeric:tabular-nums}
    .vps-plot .empty{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;color:var(--vps-muted);font-size:12.5px}
    .vps-tip{position:absolute;top:6px;pointer-events:none;background:var(--vps-text);color:var(--vps-card);border-radius:8px;padding:7px 10px;font-size:11.5px;line-height:1.5;white-space:nowrap;box-shadow:0 6px 18px rgba(0,0,0,.18);display:none;z-index:2;font-variant-numeric:tabular-nums}
    .vps-tip .d{opacity:.7;margin-bottom:2px}
    .vps-tip i{display:inline-block;width:8px;height:8px;border-radius:2px;margin-inline-end:6px}
    .vps-pair{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}
    .vps-card{border:1px solid var(--vps-line);border-radius:10px;padding:16px;min-width:0;display:flex;flex-direction:column}
    .vps-card h4{margin:0;font-size:14px;font-weight:800;display:flex;align-items:center;gap:8px}
    .vps-card h4 .n{font-size:12px;color:var(--vps-muted);font-weight:600}
    .vps-card .hint{font-size:12px;color:var(--vps-muted);margin:4px 0 0}
    .vps-card.danger h4{color:var(--vps-bad)}
    .vps-card label{display:block;font-size:12px;font-weight:700;color:var(--vps-muted);margin:12px 0 5px}
    .vps input,.vps select{width:100%;padding:9px 11px;border:1px solid var(--vps-line);border-radius:9px;background:var(--vps-card);color:var(--vps-text);font:inherit;font-size:13px;height:auto;box-shadow:none}
    .vps input:focus,.vps select:focus{outline:2px solid var(--vps-accent-soft);border-color:var(--vps-accent)}
    .vps-card .go{margin-top:auto;padding-top:14px;display:flex;gap:8px;flex-wrap:wrap}
    .vps-inline{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
    .vps-inline input{flex:1;min-width:150px;width:auto}
    .vps-list{list-style:none;margin:10px 0 0;padding:0;flex:1}
    .vps-list li{display:flex;justify-content:space-between;align-items:center;gap:10px;padding:9px 0;border-bottom:1px solid var(--vps-line);flex-wrap:wrap}
    .vps-list li:last-child{border-bottom:none}
    .vps-list .meta{color:var(--vps-muted);font-size:12px;font-weight:500}
    .vps-empty{color:var(--vps-muted);font-size:13px;padding:10px 0}
    .vps-code{font-family:ui-monospace,SFMono-Regular,Menlo,monospace}
    .vps-dlg{border:1px solid var(--vps-line);border-radius:14px;padding:0;width:min(440px,calc(100vw - 32px));background:var(--vps-card);color:var(--vps-text);box-shadow:0 24px 60px rgba(0,0,0,.25)}
    .vps-dlg::backdrop{background:rgba(15,23,42,.45)}
    .vps-dlg form{padding:20px}
    .vps-dlg h4{margin:0 0 8px;font-size:16px;font-weight:800}
    .vps-dlg p{margin:0;font-size:13.5px;color:var(--vps-muted)}
    .vps-dlg label{display:block;font-size:12px;font-weight:700;margin:14px 0 6px}
    .vps-dlg .a{display:flex;justify-content:flex-end;gap:8px;margin-top:18px}
    @media (max-width:860px){
        .vps-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}
        .vps-kpi:nth-child(3){border-inline-start:none}
        .vps-kpi:nth-child(n+3){border-top:1px solid var(--vps-line)}
        .vps-charts,.vps-pair,.vps-info{grid-template-columns:minmax(0,1fr)}
    }
    @media (max-width:520px){
        .vps-head,.vps-sec{padding-inline:16px}
        .vps-kpi{padding:14px 16px}
        .vps-kpi .v{font-size:17px}
        .vps-btns{width:100%}
        .vps-btns .vps-btn{flex:1 1 calc(33% - 8px)}
    }
</style>

<div class="vps{{ $admin ? ' vps-admin' : '' }}" id="vps" data-can-change="{{ $canChange ? 1 : 0 }}">
    <div class="vps-head">
        <div style="min-width:0">
            <div class="vps-state"><span class="vps-dot" id="vps-dot"></span><span id="vps-state">{{ __('proxmox.client.loading') }}</span></div>
            <div class="vps-sub" id="vps-sub">&nbsp;</div>
        </div>
        @if($has('power'))
        <div class="vps-btns">
            @foreach($powerButtons as $action => $style)
            <button type="button" class="vps-btn {{ $style }}" data-power="{{ $action }}" disabled>{!! $svg($action) !!}{{ __('proxmox.client.'.$action) }}</button>
            @endforeach
        </div>
        @endif
    </div>
    <div class="vps-note" id="vps-msg" role="status" aria-live="polite"></div>
    <div class="vps-note" id="vps-job"></div>
    @unless($canChange)
    <div class="vps-note err">{{ $admin ? __('proxmox.client.not_active') : __('proxmox.client.suspended_note') }}</div>
    @endunless

    <div class="vps-kpis">
        <div class="vps-kpi"><div class="l">{{ __('proxmox.client.cpu') }}</div><div class="v" id="vps-cpu">&hellip;</div><div class="vps-bar"><i id="vps-cpu-bar"></i></div><div class="s" id="vps-cpu-s">&nbsp;</div></div>
        <div class="vps-kpi"><div class="l">{{ __('proxmox.client.memory') }}</div><div class="v" id="vps-mem">&hellip;</div><div class="vps-bar"><i id="vps-mem-bar"></i></div><div class="s" id="vps-mem-s">&nbsp;</div></div>
        <div class="vps-kpi"><div class="l">{{ __('proxmox.client.disk') }}</div><div class="v" id="vps-disk">&hellip;</div><div class="vps-bar" id="vps-disk-track"><i id="vps-disk-bar"></i></div><div class="s" id="vps-disk-s">&nbsp;</div></div>
        <div class="vps-kpi"><div class="l">{{ __('proxmox.client.uptime') }}</div><div class="v" id="vps-uptime">&hellip;</div><div class="vps-bar ghost"><i></i></div><div class="s" id="vps-uptime-s">&nbsp;</div></div>
    </div>

    <div class="vps-sec" style="padding-top:6px;padding-bottom:6px">
        <div class="vps-info">
            <div class="vps-row"><span class="k">{{ __('proxmox.client.addresses') }}</span><span class="v" id="vps-ips">&hellip;</span></div>
            <div class="vps-row"><span class="k">{{ __('proxmox.client.os') }}</span><span class="v" id="vps-os">&hellip;</span></div>
            <div class="vps-row"><span class="k">{{ __('proxmox.client.login') }}</span><span class="v"><span class="vps-chip">{{ $service->username ?: 'root' }}</span></span></div>
            <div class="vps-row"><span class="k">{{ __('proxmox.client.password') }}</span><span class="v">
                @if($service->password)
                <span class="vps-chip" id="vps-pw" data-pw="{{ $service->password }}">&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;</span>
                <button type="button" class="vps-btn sm" id="vps-pw-show">{{ __('proxmox.client.show') }}</button>
                <button type="button" class="vps-btn sm" id="vps-pw-copy" title="{{ __('proxmox.client.copy') }}" aria-label="{{ __('proxmox.client.copy') }}">{!! $svg('copy') !!}</button>
                @else
                <span style="color:var(--vps-muted);font-weight:600">-</span>
                @endif
            </span></div>
            @if($admin)
            <div class="vps-row"><span class="k">{{ __('proxmox.admin.vm_where') }}</span><span class="v vps-code" id="vps-where">
                {{ !empty($pveData['proxmox_vmid']) ? __('proxmox.admin.vm_where_value', ['vmid' => $pveData['proxmox_vmid'], 'node' => $pveData['proxmox_node'] ?? '-', 'type' => $pveData['proxmox_type'] ?? 'qemu']) : '-' }}
            </span></div>
            <div class="vps-row"><span class="k">{{ __('proxmox.admin.pool_address') }}</span><span class="v vps-code">
                {{ !empty($pveData['pve_ipv4']) ? __('proxmox.admin.pool_address_value', ['address' => $pveData['pve_ipv4'].'/'.($pveData['pve_ipv4_prefix'] ?? ''), 'gateway' => $pveData['pve_ipv4_gateway'] ?? '']) : __('proxmox.admin.pool_address_none') }}
            </span></div>
            @endif
        </div>
        @if($proxmoxUrl)
        <div style="padding:12px 0 8px"><a class="vps-btn sm" href="{{ $proxmoxUrl }}" target="_blank" rel="noopener">{!! $svg('external') !!}{{ __('proxmox.admin.open_in_proxmox') }}</a></div>
        @endif
    </div>

    @if($has('graphs'))
    <div class="vps-sec">
        <div class="vps-h">
            <div><h3>{{ __('proxmox.client.usage_title') }}</h3></div>
            <div class="vps-seg" id="vps-tf" role="tablist">
                @foreach(['hour', 'day', 'week', 'month', 'year'] as $tf)
                <button type="button" role="tab" class="{{ $tf === 'hour' ? 'on' : '' }}" data-tf="{{ $tf }}" aria-selected="{{ $tf === 'hour' ? 'true' : 'false' }}">{{ __('proxmox.client.tf_'.$tf) }}</button>
                @endforeach
            </div>
        </div>
        <div class="vps-charts" id="vps-charts">
            @foreach(['cpu' => 'cpu', 'mem' => 'memory', 'net' => 'chart_network', 'io' => 'chart_disk'] as $chart => $title)
            <div class="vps-cc" data-chart="{{ $chart }}">
                <div class="vps-cc-h"><span class="t">{{ __('proxmox.client.'.$title) }}</span><span class="pk"></span></div>
                <div class="vps-lg"></div>
                <div class="vps-plot"></div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    @if($has('snapshots') || $has('backups'))
    <div class="vps-sec">
        <div class="vps-pair">
            @if($has('snapshots'))
            <div class="vps-card" id="vps-snapshots">
                <h4>{!! $svg('camera') !!}{{ __('proxmox.client.snapshots_title') }} <span class="n" id="vps-snap-count"></span></h4>
                <p class="hint">{{ __('proxmox.client.snapshots_hint') }}</p>
                <ul class="vps-list" id="vps-snap-list"><li class="vps-empty">{{ __('proxmox.client.loading') }}</li></ul>
                @if($canChange)
                <form id="vps-snap-form" class="go vps-inline" autocomplete="off">
                    <input type="text" name="name" required pattern="[A-Za-z][A-Za-z0-9_\-]{1,39}" maxlength="40" placeholder="{{ __('proxmox.client.snapshot_name') }}" aria-label="{{ __('proxmox.client.snapshot_name') }}">
                    <button type="submit" class="vps-btn primary">{{ __('proxmox.client.snapshot_take') }}</button>
                </form>
                @endif
            </div>
            @endif
            @if($has('backups'))
            <div class="vps-card" id="vps-backups">
                <h4>{!! $svg('backup') !!}{{ __('proxmox.client.backups_title') }} <span class="n" id="vps-backup-count"></span></h4>
                <p class="hint">{{ __('proxmox.client.backups_hint') }}</p>
                <ul class="vps-list" id="vps-backup-list"><li class="vps-empty">{{ __('proxmox.client.loading') }}</li></ul>
                @if($canChange)
                <div class="go"><button type="button" class="vps-btn primary" id="vps-backup-now">{{ __('proxmox.client.backup_now') }}</button></div>
                @endif
            </div>
            @endif
        </div>
    </div>
    @endif

    @if($canChange && ($has('password') || $has('reinstall')))
    <div class="vps-sec">
        <div class="vps-pair">
            @if($has('password'))
            <form class="vps-card" id="vps-password-form" autocomplete="off">
                <h4>{!! $svg('key') !!}{{ __('proxmox.client.password_title') }}</h4>
                <p class="hint">{{ __('proxmox.client.password_hint') }}</p>
                <label for="vps-pw1">{{ __('proxmox.client.new_password') }}</label>
                <input id="vps-pw1" type="password" name="password" minlength="10" required autocomplete="new-password">
                <label for="vps-pw2">{{ __('proxmox.client.confirm_password') }}</label>
                <input id="vps-pw2" type="password" name="password_confirmation" minlength="10" required autocomplete="new-password">
                <div class="go"><button type="submit" class="vps-btn primary">{{ __('proxmox.client.set_password') }}</button></div>
            </form>
            @endif
            @if($has('reinstall'))
            <form class="vps-card danger" id="vps-reinstall-form" autocomplete="off">
                <h4>{!! $svg('reinstall') !!}{{ __('proxmox.client.reinstall_title') }}</h4>
                <p class="hint">{{ __('proxmox.client.reinstall_hint') }}</p>
                <label for="vps-image">{{ __('proxmox.client.os') }}</label>
                <select id="vps-image" name="image" required>
                    @foreach($choices as $choice)
                    <option value="{{ $choice['id'] }}">{{ $choice['name'] }}</option>
                    @endforeach
                </select>
                <label for="vps-repw">{{ __('proxmox.client.new_password_optional') }}</label>
                <input id="vps-repw" type="password" name="password" minlength="10" autocomplete="new-password">
                <div class="go"><button type="submit" class="vps-btn solid-danger">{{ __('proxmox.client.reinstall') }}</button></div>
            </form>
            @endif
        </div>
    </div>
    @endif

    {{ $slot }}

    <dialog class="vps-dlg" id="vps-dlg">
        <form method="dialog">
            <h4 id="vps-dlg-t"></h4>
            <p id="vps-dlg-p"></p>
            <div id="vps-dlg-w" hidden>
                <label for="vps-dlg-i" id="vps-dlg-l"></label>
                <input id="vps-dlg-i" type="text" autocomplete="off" spellcheck="false">
            </div>
            <div class="a">
                <button type="submit" value="cancel" class="vps-btn">{{ __('proxmox.client.cancel') }}</button>
                <button type="submit" value="ok" class="vps-btn solid-danger" id="vps-dlg-ok">{{ __('proxmox.client.confirm') }}</button>
            </div>
        </form>
    </dialog>
</div>

<script>
(function () {
    var root = document.getElementById('vps');
    if (!root) { return; }
    var canChange = root.getAttribute('data-can-change') === '1';
    var T = @json(__('proxmox.client'));
    var urls = @json($urls);
    var confirmWord = @json($confirmWord);
    var csrf = @json(csrf_token());
    var $ = function (id) { return document.getElementById(id); };
    var lang = document.documentElement.lang || 'en';
    var busy = false, lastState = '', lastLock = '';

    function esc(t) { return String(t === null || t === undefined ? '' : t).replace(/[&<>"']/g, function (c) { return '&#' + c.charCodeAt(0) + ';'; }); }
    function fill(s, map) { Object.keys(map).forEach(function (k) { s = String(s).split(':' + k).join(map[k]); }); return s; }
    // Units come from the browser in the page's language: nothing to translate.
    function unit(n, u, digits) {
        try { return new Intl.NumberFormat(lang, { style: 'unit', unit: u, maximumFractionDigits: digits === undefined ? 1 : digits }).format(n); }
        catch (e) { return n + ' ' + u; }
    }
    function num(n, digits) { try { return new Intl.NumberFormat(lang, { maximumFractionDigits: digits === undefined ? 1 : digits }).format(n); } catch (e) { return String(n); } }
    function pct(n) { return unit(n, 'percent', n < 10 ? 1 : 0); }
    function mb(n) { return n >= 1024 ? unit(n / 1024, 'gigabyte', 1) : unit(n, 'megabyte', 0); }
    function rate(n) {
        var u = ['byte-per-second', 'kilobyte-per-second', 'megabyte-per-second', 'gigabyte-per-second'], i = 0;
        while (n >= 1024 && i < u.length - 1) { n /= 1024; i++; }
        return unit(n, u[i], n < 10 && i ? 1 : 0);
    }
    function human(sec) {
        var d = Math.floor(sec / 86400), h = Math.floor(sec % 86400 / 3600), m = Math.floor(sec % 3600 / 60);
        return fill(d ? T.uptime_dhm : (h ? T.uptime_hm : T.uptime_m), { d: d, h: h, m: m });
    }
    function when(ts, opts) { try { return new Date(ts * 1000).toLocaleString(lang, opts || { dateStyle: 'medium', timeStyle: 'short' }); } catch (e) { return String(ts); } }

    function msg(text, kind) { var m = $('vps-msg'); m.textContent = text || ''; m.className = 'vps-note ' + (text ? (kind || 'ok') : ''); }

    // ---- confirmation dialog (typed word for anything that wipes data) ----
    var dlg = $('vps-dlg');
    function ask(o) {
        return new Promise(function (resolve) {
            $('vps-dlg-t').textContent = o.title || '';
            $('vps-dlg-p').textContent = o.text || '';
            var needWord = !!o.word, input = $('vps-dlg-i'), ok = $('vps-dlg-ok');
            $('vps-dlg-w').hidden = !needWord;
            $('vps-dlg-l').textContent = needWord ? fill(T.type_word, { word: o.word }) : '';
            input.value = '';
            ok.className = 'vps-btn ' + (o.danger === false ? 'primary' : 'solid-danger');
            ok.textContent = o.ok || T.confirm;
            ok.disabled = needWord;
            input.oninput = function () { ok.disabled = needWord && input.value.trim().toLowerCase() !== String(o.word).toLowerCase(); };
            if (typeof dlg.showModal !== 'function') { resolve(window.confirm(o.text) ? (needWord ? o.word : true) : false); return; }
            dlg.onclose = function () { resolve(dlg.returnValue === 'ok' ? (needWord ? input.value.trim() : true) : false); };
            dlg.returnValue = '';
            dlg.showModal();
            (needWord ? input : ok).focus();
        });
    }

    function post(url, body) {
        busy = true; setButtons('', true);
        return fetch(url, { method: 'POST', headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf }, body: JSON.stringify(body) })
            .then(function (r) { return r.json().catch(function () { return {}; }).then(function (j) { return { ok: r.ok, j: j }; }); })
            .then(function (res) {
                var j = res.j || {};
                var text = j.message || (j.errors ? Object.values(j.errors).flat().join(' ') : T.failed);
                msg(text, res.ok && j.success ? 'ok' : 'err');
                return res.ok && j.success ? j : null;
            })
            .catch(function () { msg(T.failed, 'err'); return null; })
            .finally(function () { busy = false; refresh(); });
    }

    // ---- status ----
    function bar(id, value, of) {
        var b = $(id); if (!b) { return; }
        var p = of > 0 ? Math.max(0, Math.min(100, value / of * 100)) : 0;
        b.style.width = p + '%';
        b.style.background = p >= 90 ? '#ef4444' : (p >= 75 ? '#f59e0b' : '');
    }
    function setButtons(state, lock) {
        root.querySelectorAll('[data-power]').forEach(function (b) {
            var a = b.getAttribute('data-power');
            b.disabled = !(canChange && !busy && !lock && (a === 'start' ? state === 'stopped' : state === 'running'));
        });
    }
    function refresh() {
        return fetch(urls.status, { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (s) {
                var job = $('vps-job');
                if (s.busy) {
                    job.textContent = s.message || T.working; job.className = 'vps-note info';
                    $('vps-state').textContent = T.working; $('vps-dot').className = 'vps-dot busy'; setButtons('', true);
                    root.setAttribute('data-busy', '1');
                    return;
                }
                job.className = 'vps-note';
                if (root.getAttribute('data-busy') === '1') { root.removeAttribute('data-busy'); lists(); }
                if (!s.available) {
                    $('vps-state').textContent = T.unavailable; $('vps-dot').className = 'vps-dot off';
                    $('vps-sub').textContent = s.error || ''; setButtons('', true); return;
                }
                if (s.last_error) { msg(s.last_error, 'err'); }
                var running = s.status === 'running';
                lastState = s.status; lastLock = s.lock;
                $('vps-dot').className = 'vps-dot ' + (running ? 'on' : 'off');
                $('vps-state').textContent = (T['state_' + s.status] || s.status) + (s.lock ? ' (' + s.lock + ')' : '');
                $('vps-sub').textContent = (s.name ? s.name + ' · ' : '') + (s.type === 'lxc' ? T.container : T.vm) + ' #' + s.vmid;

                $('vps-cpu').textContent = running ? pct(s.cpu) : '-';
                bar('vps-cpu-bar', running ? s.cpu : 0, 100);
                $('vps-cpu-s').textContent = fill(T.cores_line, { n: s.cpus });

                $('vps-mem').innerHTML = running
                    ? esc(mb(s.memory.used)) + '<small> / ' + esc(mb(s.memory.max)) + '</small>'
                    : esc(mb(s.memory.max));
                bar('vps-mem-bar', running ? s.memory.used : 0, s.memory.max);
                $('vps-mem-s').textContent = running && s.memory.max ? fill(T.share_used, { value: pct(s.memory.used / s.memory.max * 100) }) : fill(T.memory_size, { size: mb(s.memory.max) });

                var d = s.disk || {};
                if (d.used !== null && d.used !== undefined && d.fs_size) {
                    $('vps-disk').innerHTML = esc(unit(d.used, 'gigabyte')) + '<small> / ' + esc(unit(d.fs_size, 'gigabyte')) + '</small>';
                    $('vps-disk-track').classList.remove('ghost'); bar('vps-disk-bar', d.used, d.fs_size);
                    $('vps-disk-s').textContent = fill(T.share_used, { value: pct(d.used / d.fs_size * 100) });
                } else {
                    $('vps-disk').textContent = unit(d.max || 0, 'gigabyte');
                    $('vps-disk-track').classList.add('ghost');
                    $('vps-disk-s').textContent = running ? T.disk_needs_agent : T.disk_size_only;
                }

                $('vps-uptime').textContent = running ? human(s.uptime) : '-';
                $('vps-uptime-s').textContent = running && s.uptime ? fill(T.up_since, { date: when(Math.floor(Date.now() / 1000) - s.uptime) }) : (T['state_' + s.status] || s.status);

                $('vps-ips').innerHTML = (s.addresses && s.addresses.length)
                    ? s.addresses.map(function (ip) { return '<button type="button" class="vps-chip" data-copy="' + esc(ip) + '" title="' + esc(T.copy) + '">' + esc(ip) + '</button>'; }).join('')
                    : '<span style="color:var(--vps-muted);font-weight:600">' + esc(T.no_address) + '</span>';
                $('vps-os').textContent = s.image || '-';
                setButtons(s.status, s.lock);
            })
            .catch(function () { $('vps-state').textContent = T.unavailable; $('vps-dot').className = 'vps-dot off'; });
    }

    function copy(text, btn) {
        var done = function () { var old = btn.getAttribute('title'); btn.setAttribute('title', T.copied); msg(T.copied, 'ok'); setTimeout(function () { btn.setAttribute('title', old || T.copy); msg(''); }, 1500); };
        if (navigator.clipboard && navigator.clipboard.writeText) { navigator.clipboard.writeText(text).then(done, function () {}); }
    }

    // ---- power ----
    root.querySelectorAll('[data-power]').forEach(function (b) {
        b.addEventListener('click', function () {
            var a = b.getAttribute('data-power');
            var go = a === 'start' ? Promise.resolve(true) : ask({ title: T[a], text: T['confirm_' + a], ok: T[a], danger: a === 'stop' || a === 'reset' });
            go.then(function (yes) { if (!yes) { return; } msg(T.working, 'info'); post(urls.power, { action: a }); });
        });
    });

    var pwShow = $('vps-pw-show');
    if (pwShow) {
        pwShow.addEventListener('click', function () {
            var pw = $('vps-pw'), hidden = pw.getAttribute('data-shown') !== '1';
            pw.textContent = hidden ? pw.getAttribute('data-pw') : '••••••••';
            pw.setAttribute('data-shown', hidden ? '1' : '0');
            pwShow.textContent = hidden ? T.hide : T.show;
        });
        $('vps-pw-copy').addEventListener('click', function () { copy($('vps-pw').getAttribute('data-pw'), this); });
    }

    var pwForm = $('vps-password-form');
    if (pwForm) {
        pwForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var f = new FormData(pwForm);
            if (f.get('password') !== f.get('password_confirmation')) { msg(T.password_mismatch, 'err'); return; }
            msg(T.working, 'info');
            post(urls.password, { password: f.get('password'), password_confirmation: f.get('password_confirmation') }).then(function (j) {
                if (j) { var pw = $('vps-pw'); if (pw) { pw.setAttribute('data-pw', f.get('password')); } pwForm.reset(); }
            });
        });
    }

    var reForm = $('vps-reinstall-form');
    if (reForm) {
        reForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var f = new FormData(reForm), sel = reForm.querySelector('select');
            ask({ title: T.reinstall_title, text: fill(T.reinstall_warning, { os: sel.options[sel.selectedIndex].text }), word: confirmWord, ok: T.reinstall }).then(function (typed) {
                if (!typed) { return; }
                msg(T.reinstalling, 'info');
                post(urls.reinstall, { image: f.get('image'), password: f.get('password'), confirm: typed }).then(function (j) {
                    if (j && j.data && j.data.password) {
                        msg(j.message + ' ' + T.new_password_is + ' ' + j.data.password, 'ok');
                        var pw = $('vps-pw'); if (pw) { pw.setAttribute('data-pw', j.data.password); }
                        reForm.reset();
                    }
                });
            });
        });
    }

    // ---- charts: plain SVG, sized to the box so nothing is stretched ----
    var CHARTS = {
        cpu: { series: [{ key: 'cpu', label: T.cpu, color: '#6366f1' }], fmt: pct, scale: 'pct' },
        mem: { series: [{ key: 'mem', label: T.memory, color: '#0ea5e9' }], fmt: mb, scale: 'mem' },
        net: { series: [{ key: 'netin', label: T.net_in, color: '#10b981' }, { key: 'netout', label: T.net_out, color: '#f59e0b' }], fmt: rate, scale: 'bytes' },
        io: { series: [{ key: 'diskread', label: T.disk_read, color: '#8b5cf6' }, { key: 'diskwrite', label: T.disk_write, color: '#ef4444' }], fmt: rate, scale: 'bytes' }
    };
    var graphData = null, graphTf = 'hour', uid = 0;

    // Four equal steps of a round size, so every tick label is a round number.
    function niceTop(max, scale, points) {
        if (scale === 'mem') {
            for (var j = points.length - 1; j >= 0; j--) { if (points[j].maxmem) { return Math.max(points[j].maxmem, max); } }
        }
        var base = scale === 'bytes' ? 1024 : 10;
        var need = Math.max(max, scale === 'pct' ? 1 : (scale === 'bytes' ? 1024 : 1)) / 4;
        var k = Math.floor(Math.log(need) / Math.log(base)), f = Math.pow(base, k), m = need / f;
        var nice = scale === 'bytes' ? [1, 2, 2.5, 5, 10, 20, 25, 50, 100, 200, 250, 500, 1024] : [1, 2, 2.5, 5, 10];
        var step = base * f;
        for (var n = 0; n < nice.length; n++) { if (m <= nice[n] + 1e-9) { step = nice[n] * f; break; } }
        if (scale === 'pct') { step = Math.min(step, 25); }
        return step * 4;
    }
    function timeFmt(tf) {
        return tf === 'hour' || tf === 'day' ? { hour: '2-digit', minute: '2-digit' }
            : tf === 'week' ? { weekday: 'short', day: 'numeric' }
            : tf === 'month' ? { day: 'numeric', month: 'short' }
            : { month: 'short', year: '2-digit' };
    }

    function drawChart(card) {
        var def = CHARTS[card.getAttribute('data-chart')], box = card.querySelector('.vps-plot');
        var pts = (graphData || []).filter(function (p) { return def.series.some(function (s) { return p[s.key] !== null && p[s.key] !== undefined; }); });
        var legend = card.querySelector('.vps-lg'), peakEl = card.querySelector('.pk');
        var last = def.series.map(function (s) { for (var i = pts.length - 1; i >= 0; i--) { if (pts[i][s.key] !== null) { return pts[i][s.key]; } } return null; });
        legend.innerHTML = def.series.map(function (s, k) {
            return '<span><i style="background:' + s.color + '"></i>' + esc(def.series.length === 1 ? T.now : s.label) + ' <b>' + esc(last[k] === null ? '-' : def.fmt(last[k])) + '</b></span>';
        }).join('');

        var W = Math.max(200, Math.round(box.clientWidth)), H = Math.round(box.clientHeight) || 180;
        if (pts.length < 2 || !graphData) {
            peakEl.textContent = '';
            box.innerHTML = '<div class="empty">' + esc(graphData ? T.no_data : T.loading) + '</div>';
            return;
        }
        var peak = 0;
        pts.forEach(function (p) { def.series.forEach(function (s) { if (p[s.key] > peak) { peak = p[s.key]; } }); });
        peakEl.textContent = fill(T.peak, { value: def.fmt(peak) });

        var top = niceTop(peak, def.scale, pts);
        var ticksY = [0, top / 4, top / 2, top * 3 / 4, top];
        var labelW = 0;
        ticksY.forEach(function (v) { if (v) { labelW = Math.max(labelW, def.fmt(v).length); } });
        var padL = Math.min(76, Math.max(34, labelW * 6.2 + 10)), padR = 8, padT = 8, padB = 22;
        var pw = W - padL - padR, ph = H - padT - padB;
        var t0 = pts[0].t, t1 = pts[pts.length - 1].t, span = Math.max(1, t1 - t0);
        var X = function (t) { return padL + (t - t0) / span * pw; };
        var Y = function (v) { return padT + ph - Math.min(v, top) / top * ph; };
        var id = 'vpsg' + (++uid);

        var h = '<svg viewBox="0 0 ' + W + ' ' + H + '" width="' + W + '" height="' + H + '"><defs>';
        def.series.forEach(function (s, k) {
            h += '<linearGradient id="' + id + k + '" x1="0" x2="0" y1="0" y2="1"><stop offset="0" stop-color="' + s.color + '" stop-opacity=".28"/><stop offset="1" stop-color="' + s.color + '" stop-opacity="0"/></linearGradient>';
        });
        h += '</defs>';
        ticksY.forEach(function (v, i) {
            var y = Y(v).toFixed(1);
            h += '<line x1="' + padL + '" x2="' + (W - padR) + '" y1="' + y + '" y2="' + y + '" stroke="currentColor" stroke-opacity="' + (i === 0 ? '.22' : '.08') + '"/>';
            h += '<text x="' + (padL - 6) + '" y="' + y + '" text-anchor="end" dominant-baseline="middle">' + esc(v === 0 ? num(0) : def.fmt(v)) + '</text>';
        });
        var fmtT = timeFmt(graphTf), cols = pw < 260 ? 2 : (pw < 420 ? 3 : 4);
        for (var i = 0; i <= cols; i++) {
            var tt = t0 + span * i / cols, x = X(tt);
            h += '<line x1="' + x.toFixed(1) + '" x2="' + x.toFixed(1) + '" y1="' + (padT + ph) + '" y2="' + (padT + ph + 4) + '" stroke="currentColor" stroke-opacity=".22"/>';
            h += '<text x="' + x.toFixed(1) + '" y="' + (H - 5) + '" text-anchor="' + (i === 0 ? 'start' : (i === cols ? 'end' : 'middle')) + '">' + esc(when(tt, fmtT)) + '</text>';
        }
        // A gap in the data stays a gap instead of dropping to zero.
        var gap = span / Math.max(1, pts.length - 1) * 2.5;
        def.series.forEach(function (s, k) {
            var segs = [], cur = [], prevT = null;
            pts.forEach(function (p) {
                var v = p[s.key];
                if (v === null || v === undefined || (prevT !== null && p.t - prevT > gap)) { if (cur.length) { segs.push(cur); } cur = []; }
                if (v !== null && v !== undefined) { cur.push([X(p.t), Y(v)]); }
                prevT = p.t;
            });
            if (cur.length) { segs.push(cur); }
            segs.forEach(function (seg) {
                var line = seg.map(function (q, n) { return (n ? 'L' : 'M') + q[0].toFixed(1) + ' ' + q[1].toFixed(1); }).join('');
                if (seg.length > 1) {
                    h += '<path d="' + line + 'L' + seg[seg.length - 1][0].toFixed(1) + ' ' + (padT + ph) + 'L' + seg[0][0].toFixed(1) + ' ' + (padT + ph) + 'Z" fill="url(#' + id + k + ')"/>';
                }
                h += '<path d="' + line + '" fill="none" stroke="' + s.color + '" stroke-width="1.8" stroke-linejoin="round" stroke-linecap="round"/>';
            });
        });
        h += '<line class="cx" x1="0" x2="0" y1="' + padT + '" y2="' + (padT + ph) + '" stroke="currentColor" stroke-opacity=".35" stroke-dasharray="3 3" visibility="hidden"/>';
        def.series.forEach(function (s, k) { h += '<circle class="cd' + k + '" r="3.5" fill="' + s.color + '" stroke="#fff" stroke-width="1.5" visibility="hidden"/>'; });
        h += '<rect class="hit" x="' + padL + '" y="' + padT + '" width="' + pw + '" height="' + ph + '" fill="transparent"/></svg><div class="vps-tip"></div>';
        box.innerHTML = h;

        var svg = box.querySelector('svg'), tip = box.querySelector('.vps-tip'), cx = svg.querySelector('.cx');
        function hide() { cx.setAttribute('visibility', 'hidden'); tip.style.display = 'none'; def.series.forEach(function (s, k) { svg.querySelector('.cd' + k).setAttribute('visibility', 'hidden'); }); }
        svg.querySelector('.hit').addEventListener('pointermove', function (ev) {
            var r = svg.getBoundingClientRect(), mx = (ev.clientX - r.left) * (W / r.width);
            var t = t0 + (mx - padL) / pw * span, best = 0;
            for (var n = 1; n < pts.length; n++) { if (Math.abs(pts[n].t - t) < Math.abs(pts[best].t - t)) { best = n; } }
            var p = pts[best], x = X(p.t);
            cx.setAttribute('x1', x); cx.setAttribute('x2', x); cx.setAttribute('visibility', 'visible');
            var rows = '<div class="d">' + esc(when(p.t)) + '</div>';
            def.series.forEach(function (s, k) {
                var c = svg.querySelector('.cd' + k), v = p[s.key];
                if (v === null || v === undefined) { c.setAttribute('visibility', 'hidden'); } else { c.setAttribute('cx', x); c.setAttribute('cy', Y(v)); c.setAttribute('visibility', 'visible'); }
                rows += '<div><i style="background:' + s.color + '"></i>' + esc(s.label) + ': <b>' + esc(v === null || v === undefined ? '-' : def.fmt(v)) + '</b></div>';
            });
            tip.innerHTML = rows; tip.style.display = 'block';
            var px = x / W * r.width, tw = tip.offsetWidth;
            tip.style.left = Math.max(0, Math.min(r.width - tw, px + (px > r.width / 2 ? -tw - 12 : 12))) + 'px';
        });
        svg.querySelector('.hit').addEventListener('pointerleave', hide);
    }
    function drawAll() { root.querySelectorAll('.vps-cc').forEach(drawChart); }

    function graphs(tf) {
        graphTf = tf;
        return fetch(urls.graphs + '?timeframe=' + encodeURIComponent(tf), { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (g) { graphData = g.available ? (g.points || []) : []; drawAll(); })
            .catch(function () { graphData = []; drawAll(); });
    }
    var tfBox = $('vps-tf');
    if (tfBox) {
        tfBox.querySelectorAll('[data-tf]').forEach(function (b) {
            b.addEventListener('click', function () {
                tfBox.querySelectorAll('[data-tf]').forEach(function (x) { x.classList.remove('on'); x.setAttribute('aria-selected', 'false'); });
                b.classList.add('on'); b.setAttribute('aria-selected', 'true'); graphs(b.getAttribute('data-tf'));
            });
        });
        drawAll();
        graphs('hour');
        var rz = null;
        if (window.ResizeObserver) { new ResizeObserver(function () { cancelAnimationFrame(rz); rz = requestAnimationFrame(drawAll); }).observe($('vps-charts')); }
        setInterval(function () { if (!document.hidden && graphTf === 'hour') { graphs('hour'); } }, 60000);
    }

    // ---- snapshots and backups ----
    function snapshots() {
        var box = $('vps-snap-list'); if (!box) { return; }
        fetch(urls.snapshots, { headers: { 'Accept': 'application/json' } }).then(function (r) { return r.json(); }).then(function (d) {
            if (!d.available) { box.innerHTML = '<li class="vps-empty">' + esc(d.error || T.unavailable) + '</li>'; return; }
            $('vps-snap-count').textContent = fill(T.used_of, { used: d.snapshots.length, limit: d.limit });
            box.innerHTML = d.snapshots.length ? d.snapshots.map(function (x) {
                return '<li><span><strong>' + esc(x.name) + '</strong><br><span class="meta">' + esc(when(x.time)) + (x.description ? ' · ' + esc(x.description) : '') + '</span></span>'
                    + (canChange ? '<span class="vps-btns"><button type="button" class="vps-btn sm" data-snap-rollback="' + esc(x.name) + '">' + esc(T.snapshot_rollback) + '</button>'
                    + '<button type="button" class="vps-btn sm danger" data-snap-delete="' + esc(x.name) + '">' + esc(T.delete) + '</button></span>' : '') + '</li>';
            }).join('') : '<li class="vps-empty">' + esc(T.snapshots_none) + '</li>';
            var form = $('vps-snap-form'); if (form) { form.querySelector('button').disabled = d.snapshots.length >= d.limit; }
        });
    }
    function backups() {
        var box = $('vps-backup-list'); if (!box) { return; }
        fetch(urls.backups, { headers: { 'Accept': 'application/json' } }).then(function (r) { return r.json(); }).then(function (d) {
            if (!d.available) { box.innerHTML = '<li class="vps-empty">' + esc(d.error || T.unavailable) + '</li>'; return; }
            $('vps-backup-count').textContent = fill(T.used_of, { used: d.backups.length, limit: d.limit });
            box.innerHTML = d.backups.length ? d.backups.map(function (x) {
                return '<li><span><strong>' + esc(when(x.time)) + '</strong><br><span class="meta">' + esc(unit(x.size / 1073741824, 'gigabyte')) + '</span></span>'
                    + (canChange ? '<span class="vps-btns"><button type="button" class="vps-btn sm" data-backup-restore="' + esc(x.volid) + '">' + esc(T.backup_restore) + '</button>'
                    + (x.protected ? '' : '<button type="button" class="vps-btn sm danger" data-backup-delete="' + esc(x.volid) + '">' + esc(T.delete) + '</button>') + '</span>' : '') + '</li>';
            }).join('') : '<li class="vps-empty">' + esc(T.backups_none) + '</li>';
            var now = $('vps-backup-now'); if (now) { now.disabled = d.backups.length >= d.limit; }
        });
    }
    function lists() { snapshots(); backups(); }

    root.addEventListener('click', function (e) {
        var b = e.target.closest('button'); if (!b || !root.contains(b)) { return; }
        if (b.hasAttribute('data-copy')) { copy(b.getAttribute('data-copy'), b); return; }
        if (b.hasAttribute('data-snap-rollback')) {
            var n = b.getAttribute('data-snap-rollback');
            ask({ title: T.snapshot_rollback, text: fill(T.confirm_rollback, { name: n }), ok: T.snapshot_rollback }).then(function (yes) {
                if (yes) { msg(T.working, 'info'); post(urls.snapshotAction, { action: 'rollback', name: n }).then(lists); }
            });
        } else if (b.hasAttribute('data-snap-delete')) {
            var n2 = b.getAttribute('data-snap-delete');
            ask({ title: T.delete, text: fill(T.confirm_delete_snapshot, { name: n2 }), ok: T.delete }).then(function (yes) {
                if (yes) { msg(T.working, 'info'); post(urls.snapshotAction, { action: 'delete', name: n2 }).then(lists); }
            });
        } else if (b.hasAttribute('data-backup-restore')) {
            var v = b.getAttribute('data-backup-restore');
            ask({ title: T.backup_restore, text: T.restore_warning, word: confirmWord, ok: T.backup_restore }).then(function (typed) {
                if (typed) { msg(T.working, 'info'); post(urls.backupAction, { action: 'restore', volid: v, confirm: typed }).then(lists); }
            });
        } else if (b.hasAttribute('data-backup-delete')) {
            var v2 = b.getAttribute('data-backup-delete');
            ask({ title: T.delete, text: T.confirm_delete_backup, ok: T.delete }).then(function (yes) {
                if (yes) { msg(T.working, 'info'); post(urls.backupAction, { action: 'delete', volid: v2 }).then(lists); }
            });
        } else if (b.id === 'vps-backup-now') {
            msg(T.working, 'info'); post(urls.backupAction, { action: 'create' }).then(function () { setTimeout(backups, 5000); });
        }
    });
    var snapForm = $('vps-snap-form');
    if (snapForm) {
        snapForm.addEventListener('submit', function (e) {
            e.preventDefault();
            msg(T.working, 'info');
            post(urls.snapshotAction, { action: 'create', name: new FormData(snapForm).get('name') }).then(function (j) { if (j) { snapForm.reset(); } lists(); });
        });
    }

    refresh();
    lists();
    setInterval(function () { if (!busy && !document.hidden) { refresh(); } }, 15000);
})();
</script>
