{{-- Virtual server controls (Proxmox). Everything live comes from the
     vps.* JSON routes; the page itself only knows what the order knows. --}}
@php
    $canChange = strtolower((string) $service->status) === 'active';
    $confirmWord = \App\Http\Controllers\Client\VpsController::confirmationWord($service);
@endphp
<style>
    .vps{margin-bottom:24px}
    .vps-top{display:flex;justify-content:space-between;align-items:center;gap:14px;flex-wrap:wrap;padding:16px 20px;border-bottom:1px solid var(--border)}
    .vps-state{display:inline-flex;align-items:center;gap:8px;font-weight:800;font-size:14px;text-transform:capitalize}
    .vps-dot{width:10px;height:10px;border-radius:50%;background:var(--muted)}
    .vps-dot.on{background:#22c55e;box-shadow:0 0 0 4px rgba(34,197,94,.2)}
    .vps-dot.off{background:#ef4444;box-shadow:0 0 0 4px rgba(239,68,68,.15)}
    .vps-btns{display:flex;gap:8px;flex-wrap:wrap}
    .vps-btn{display:inline-flex;align-items:center;gap:6px;padding:8px 14px;border-radius:10px;font-size:13px;font-weight:700;border:1px solid var(--border);background:var(--bg);color:var(--text);cursor:pointer}
    .vps-btn:hover:not([disabled]){border-color:var(--primary);color:var(--primary)}
    .vps-btn[disabled]{opacity:.45;cursor:not-allowed}
    .vps-btn.danger{color:#dc2626}
    .vps-btn.primary{background:var(--primary);border-color:var(--primary);color:#fff}
    .vps-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr))}
    .vps-m{padding:14px 20px;border-right:1px solid var(--border);border-bottom:1px solid var(--border)}
    .vps-m .l{font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.5px}
    .vps-m .v{font-size:18px;font-weight:800;margin-top:6px}
    .vps-m .v small{font-size:12px;color:var(--muted);font-weight:600}
    .vps-bar{height:5px;border-radius:99px;background:var(--border);overflow:hidden;margin-top:8px}
    .vps-bar i{display:block;height:100%;width:0;background:var(--primary);transition:width .5s}
    .vps-body{padding:16px 20px}
    .vps-msg{display:none;margin:12px 20px 0;padding:10px 14px;border-radius:10px;font-size:13px;font-weight:600}
    .vps-msg.ok{display:block;background:rgba(34,197,94,.1);color:#15803d}
    .vps-msg.err{display:block;background:rgba(239,68,68,.1);color:#b91c1c}
    .vps-tabs{display:flex;gap:6px;margin-bottom:10px}
    .vps-tab{padding:5px 11px;border-radius:8px;font-size:12px;font-weight:700;border:1px solid var(--border);background:var(--bg);color:var(--muted);cursor:pointer}
    .vps-tab.on{background:var(--primary);border-color:var(--primary);color:#fff}
    .vps-chart{width:100%;height:150px;display:block}
    .vps-charts{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(320px,100%),1fr));gap:16px}
    .vps-chart-t{font-size:12px;font-weight:700;color:var(--muted);margin-bottom:4px;display:flex;justify-content:space-between}
    .vps-forms{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(320px,100%),1fr));gap:16px}
    .vps-forms label{display:block;font-size:12px;font-weight:700;color:var(--muted);margin:10px 0 4px}
    .vps-forms input,.vps-forms select{width:100%;padding:9px 11px;border:1px solid var(--border);border-radius:9px;background:var(--bg);color:var(--text);font-size:13px}
    .vps-pw{font-family:ui-monospace,Menlo,monospace}
    .vps-job{display:none;margin:12px 20px 0;padding:10px 14px;border-radius:10px;font-size:13px;font-weight:700;background:rgba(59,130,246,.1);color:#1d4ed8}
    .vps-list{list-style:none;margin:8px 0 0;padding:0}
    .vps-list li{display:flex;justify-content:space-between;align-items:center;gap:10px;padding:9px 0;border-bottom:1px solid var(--border);font-size:13px;flex-wrap:wrap}
    .vps-list li:last-child{border-bottom:none}
    .vps-list .meta{color:var(--muted);font-size:12px}
    .vps-row{display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end}
    .vps-row input{flex:1;min-width:140px}
    .vps-empty{color:var(--muted);font-size:13px;padding:8px 0}
</style>

<div class="sv-sec"><i class="ri-server-line"></i>{{ __('proxmox.client.title') }}</div>
<div class="sv-panel vps" id="vps" data-can-change="{{ $canChange ? 1 : 0 }}">
    <div class="vps-top">
        <div>
            <span class="vps-state"><span class="vps-dot" id="vps-dot"></span><span id="vps-state">{{ __('proxmox.client.loading') }}</span></span>
            <div style="font-size:12px;color:var(--muted);margin-top:4px;" id="vps-sub"></div>
        </div>
        @if(in_array('power', $vpsFeatures, true))
        <div class="vps-btns">
            <button type="button" class="vps-btn" data-power="start" disabled><i class="ri-play-fill"></i>{{ __('proxmox.client.start') }}</button>
            <button type="button" class="vps-btn" data-power="reboot" disabled><i class="ri-restart-line"></i>{{ __('proxmox.client.reboot') }}</button>
            <button type="button" class="vps-btn" data-power="shutdown" disabled><i class="ri-shut-down-line"></i>{{ __('proxmox.client.shutdown') }}</button>
            <button type="button" class="vps-btn danger" data-power="stop" disabled><i class="ri-stop-fill"></i>{{ __('proxmox.client.stop') }}</button>
        </div>
        @endif
    </div>
    <div class="vps-msg" id="vps-msg"></div>
    <div class="vps-job" id="vps-job"></div>
    @unless($canChange)
    <div class="vps-msg err" style="display:block">{{ __('proxmox.client.suspended_note') }}</div>
    @endunless

    <div class="vps-grid">
        <div class="vps-m"><div class="l">{{ __('proxmox.client.cpu') }}</div><div class="v" id="vps-cpu">&hellip;</div><div class="vps-bar"><i id="vps-cpu-bar"></i></div></div>
        <div class="vps-m"><div class="l">{{ __('proxmox.client.memory') }}</div><div class="v" id="vps-mem">&hellip;</div><div class="vps-bar"><i id="vps-mem-bar"></i></div></div>
        <div class="vps-m"><div class="l">{{ __('proxmox.client.disk') }}</div><div class="v" id="vps-disk">&hellip;</div></div>
        <div class="vps-m"><div class="l">{{ __('proxmox.client.uptime') }}</div><div class="v" id="vps-uptime">&hellip;</div></div>
    </div>

    <div class="vps-body">
        <ul class="sv-dl" style="padding:0">
            <li><span class="k">{{ __('proxmox.client.addresses') }}</span><span class="v" id="vps-ips">&hellip;</span></li>
            <li><span class="k">{{ __('proxmox.client.os') }}</span><span class="v" id="vps-os">&hellip;</span></li>
            <li><span class="k">{{ __('proxmox.client.login') }}</span><span class="v"><span class="sv-code">{{ $service->username ?: 'root' }}</span></span></li>
            @if($service->password)
            <li><span class="k">{{ __('proxmox.client.password') }}</span><span class="v">
                <span class="sv-code vps-pw" id="vps-pw" data-pw="{{ $service->password }}">&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;</span>
                <button type="button" class="vps-tab" id="vps-pw-show">{{ __('proxmox.client.show') }}</button>
            </span></li>
            @endif
        </ul>
    </div>

    @if(in_array('graphs', $vpsFeatures, true))
    <div class="vps-body" style="border-top:1px solid var(--border)">
        <div class="vps-tabs" id="vps-tf">
            @foreach(['hour', 'day', 'week', 'month'] as $tf)
            <button type="button" class="vps-tab{{ $tf === 'hour' ? ' on' : '' }}" data-tf="{{ $tf }}">{{ __('proxmox.client.tf_'.$tf) }}</button>
            @endforeach
        </div>
        <div class="vps-charts">
            <div><div class="vps-chart-t"><span>{{ __('proxmox.client.cpu_percent') }}</span><span id="vps-g-cpu-last"></span></div><svg class="vps-chart" id="vps-g-cpu" preserveAspectRatio="none"></svg></div>
            <div><div class="vps-chart-t"><span>{{ __('proxmox.client.memory_mb') }}</span><span id="vps-g-mem-last"></span></div><svg class="vps-chart" id="vps-g-mem" preserveAspectRatio="none"></svg></div>
            <div><div class="vps-chart-t"><span>{{ __('proxmox.client.network') }}</span><span id="vps-g-net-last"></span></div><svg class="vps-chart" id="vps-g-net" preserveAspectRatio="none"></svg></div>
            <div><div class="vps-chart-t"><span>{{ __('proxmox.client.disk_io') }}</span><span id="vps-g-io-last"></span></div><svg class="vps-chart" id="vps-g-io" preserveAspectRatio="none"></svg></div>
        </div>
    </div>
    @endif

    @if(in_array('snapshots', $vpsFeatures, true) || in_array('backups', $vpsFeatures, true))
    <div class="vps-body" style="border-top:1px solid var(--border)">
        <div class="vps-forms">
            @if(in_array('snapshots', $vpsFeatures, true))
            <div id="vps-snapshots">
                <strong style="font-size:14px">{{ __('proxmox.client.snapshots_title') }}</strong> <span class="meta" id="vps-snap-count" style="color:var(--muted);font-size:12px"></span>
                <div style="font-size:12px;color:var(--muted);margin-top:4px">{{ __('proxmox.client.snapshots_hint') }}</div>
                <ul class="vps-list" id="vps-snap-list"><li class="vps-empty">{{ __('proxmox.client.loading') }}</li></ul>
                @if($canChange)
                <form id="vps-snap-form" class="vps-row" autocomplete="off" style="margin-top:10px">
                    <input type="text" name="name" required pattern="[A-Za-z][A-Za-z0-9_-]{1,39}" maxlength="40" placeholder="{{ __('proxmox.client.snapshot_name') }}">
                    <button type="submit" class="vps-btn primary"><i class="ri-camera-line"></i>{{ __('proxmox.client.snapshot_take') }}</button>
                </form>
                @endif
            </div>
            @endif
            @if(in_array('backups', $vpsFeatures, true))
            <div id="vps-backups">
                <strong style="font-size:14px">{{ __('proxmox.client.backups_title') }}</strong> <span id="vps-backup-count" style="color:var(--muted);font-size:12px"></span>
                <div style="font-size:12px;color:var(--muted);margin-top:4px">{{ __('proxmox.client.backups_hint') }}</div>
                <ul class="vps-list" id="vps-backup-list"><li class="vps-empty">{{ __('proxmox.client.loading') }}</li></ul>
                @if($canChange)
                <button type="button" class="vps-btn primary" id="vps-backup-now" style="margin-top:10px"><i class="ri-save-3-line"></i>{{ __('proxmox.client.backup_now') }}</button>
                @endif
            </div>
            @endif
        </div>
    </div>
    @endif

    @if($canChange && (in_array('password', $vpsFeatures, true) || in_array('reinstall', $vpsFeatures, true)))
    <div class="vps-body" style="border-top:1px solid var(--border)">
        <div class="vps-forms">
            @if(in_array('password', $vpsFeatures, true))
            <form id="vps-password-form" autocomplete="off">
                <strong style="font-size:14px">{{ __('proxmox.client.password_title') }}</strong>
                <div style="font-size:12px;color:var(--muted);margin-top:4px">{{ __('proxmox.client.password_hint') }}</div>
                <label>{{ __('proxmox.client.new_password') }}</label>
                <input type="password" name="password" minlength="10" required autocomplete="new-password">
                <label>{{ __('proxmox.client.confirm_password') }}</label>
                <input type="password" name="password_confirmation" minlength="10" required autocomplete="new-password">
                <button type="submit" class="vps-btn primary" style="margin-top:12px">{{ __('proxmox.client.set_password') }}</button>
            </form>
            @endif
            @if(in_array('reinstall', $vpsFeatures, true))
            <form id="vps-reinstall-form" autocomplete="off">
                <strong style="font-size:14px;color:#dc2626">{{ __('proxmox.client.reinstall_title') }}</strong>
                <div style="font-size:12px;color:var(--muted);margin-top:4px">{{ __('proxmox.client.reinstall_hint') }}</div>
                <label>{{ __('proxmox.client.os') }}</label>
                <select name="image" required>
                    @foreach($reinstallChoices as $choice)
                    <option value="{{ $choice['id'] }}">{{ $choice['name'] }}</option>
                    @endforeach
                </select>
                <label>{{ __('proxmox.client.new_password_optional') }}</label>
                <input type="password" name="password" minlength="10" autocomplete="new-password">
                <label>{{ trans_markup('proxmox.client.type_to_confirm', ['word' => $confirmWord]) }}</label>
                <input type="text" name="confirm" required autocomplete="off">
                <button type="submit" class="vps-btn danger" style="margin-top:12px"><i class="ri-refresh-line"></i>{{ __('proxmox.client.reinstall') }}</button>
            </form>
            @endif
        </div>
    </div>
    @endif
</div>

<script>
(function () {
    var root = document.getElementById('vps');
    if (!root) { return; }
    var canChange = root.getAttribute('data-can-change') === '1';
    var T = @json(__('proxmox.client'));
    var urls = {
        status: @json(route('client.services.vps.status', $service)),
        graphs: @json(route('client.services.vps.graphs', $service)),
        power: @json(route('client.services.vps.power', $service)),
        password: @json(route('client.services.vps.password', $service)),
        reinstall: @json(route('client.services.vps.reinstall', $service)),
        snapshots: @json(route('client.services.vps.snapshots', $service)),
        snapshotAction: @json(route('client.services.vps.snapshots.action', $service)),
        backups: @json(route('client.services.vps.backups', $service)),
        backupAction: @json(route('client.services.vps.backups.action', $service)),
    };
    var confirmWord = @json($confirmWord);
    var csrf = @json(csrf_token());
    var $ = function (id) { return document.getElementById(id); };
    var busy = false;

    function msg(text, ok) { var m = $('vps-msg'); m.textContent = text || ''; m.className = 'vps-msg ' + (ok ? 'ok' : 'err'); }
    // Units come from the browser in the page's language: no unit strings to translate.
    var lang = document.documentElement.lang || 'en';
    function unit(n, u) {
        try { return new Intl.NumberFormat(lang, { style: 'unit', unit: u, maximumFractionDigits: 1 }).format(n); }
        catch (e) { return n + ' ' + u; }
    }
    function human(sec) {
        if (!sec) { return '-'; }
        var d = Math.floor(sec / 86400), h = Math.floor(sec % 86400 / 3600), m = Math.floor(sec % 3600 / 60);
        var t = d ? T.uptime_dhm : (h ? T.uptime_hm : T.uptime_m);
        return t.replace(':d', d).replace(':h', h).replace(':m', m);
    }
    function bytes(n) {
        var u = ['byte-per-second', 'kilobyte-per-second', 'megabyte-per-second', 'gigabyte-per-second'], i = 0;
        while (n >= 1024 && i < u.length - 1) { n /= 1024; i++; }
        return unit(n, u[i]);
    }
    function bar(id, pct) { var b = $(id); if (b) { b.style.width = Math.max(0, Math.min(100, pct)) + '%'; b.style.background = pct >= 90 ? '#ef4444' : (pct >= 75 ? '#f59e0b' : ''); } }

    function setButtons(state, lock) {
        root.querySelectorAll('[data-power]').forEach(function (b) {
            var a = b.getAttribute('data-power');
            var enabled = canChange && !busy && !lock && (a === 'start' ? state === 'stopped' : state === 'running');
            b.disabled = !enabled;
        });
    }

    function refresh() {
        fetch(urls.status, { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (s) {
                var job = $('vps-job');
                if (s.busy) {
                    job.style.display = 'block'; job.textContent = s.message || T.working;
                    $('vps-state').textContent = T.working; $('vps-dot').className = 'vps-dot'; setButtons('', true);
                    root.setAttribute('data-busy', '1');
                    return;
                }
                job.style.display = 'none';
                if (root.getAttribute('data-busy') === '1') { root.removeAttribute('data-busy'); lists(); }
                if (!s.available) { $('vps-state').textContent = T.unavailable; $('vps-sub').textContent = s.error || ''; setButtons(''); return; }
                if (s.last_error) { msg(s.last_error, false); }
                var running = s.status === 'running';
                $('vps-dot').className = 'vps-dot ' + (running ? 'on' : 'off');
                $('vps-state').textContent = (T['state_' + s.status] || s.status) + (s.lock ? ' (' + s.lock + ')' : '');
                $('vps-sub').textContent = s.name + ' · ' + (s.type === 'lxc' ? T.container : T.vm) + ' #' + s.vmid;
                $('vps-cpu').innerHTML = s.cpu + '<small> % · ' + s.cpus + ' ' + T.cores + '</small>';
                bar('vps-cpu-bar', s.cpu);
                $('vps-mem').innerHTML = s.memory.used.toLocaleString(lang) + '<small> / ' + unit(s.memory.max, 'megabyte') + '</small>';
                bar('vps-mem-bar', s.memory.max ? s.memory.used / s.memory.max * 100 : 0);
                $('vps-disk').innerHTML = (s.disk.used > 0 ? s.disk.used.toLocaleString(lang) + ' / ' : '') + unit(s.disk.max, 'gigabyte');
                $('vps-uptime').textContent = running ? human(s.uptime) : '-';
                $('vps-ips').innerHTML = (s.addresses && s.addresses.length)
                    ? s.addresses.map(function (ip) { return '<span class="sv-code" style="margin-left:6px">' + ip.replace(/[<>&"]/g, '') + '</span>'; }).join('')
                    : '<span style="color:var(--muted)">' + T.no_address + '</span>';
                $('vps-os').textContent = s.image || '-';
                setButtons(s.status, s.lock);
            })
            .catch(function () { $('vps-state').textContent = T.unavailable; });
    }

    function post(url, body) {
        busy = true; setButtons('');
        return fetch(url, { method: 'POST', headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf }, body: JSON.stringify(body) })
            .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
            .then(function (res) {
                var j = res.j || {};
                var text = j.message || (j.errors ? Object.values(j.errors).flat().join(' ') : T.failed);
                msg(text, res.ok && j.success);
                return res.ok && j.success ? j : null;
            })
            .catch(function () { msg(T.failed, false); return null; })
            .finally(function () { busy = false; refresh(); });
    }

    root.querySelectorAll('[data-power]').forEach(function (b) {
        b.addEventListener('click', function () {
            var a = b.getAttribute('data-power');
            if ((a === 'stop' || a === 'reboot' || a === 'shutdown') && !window.confirm(T['confirm_' + a])) { return; }
            msg(T.working, true);
            post(urls.power, { action: a });
        });
    });

    var pwShow = $('vps-pw-show');
    if (pwShow) {
        pwShow.addEventListener('click', function () {
            var pw = $('vps-pw');
            var hidden = pw.textContent.indexOf('•') === 0;
            pw.textContent = hidden ? pw.getAttribute('data-pw') : '••••••••';
            pwShow.textContent = hidden ? T.hide : T.show;
        });
    }

    var pwForm = $('vps-password-form');
    if (pwForm) {
        pwForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var f = new FormData(pwForm);
            msg(T.working, true);
            post(urls.password, { password: f.get('password'), password_confirmation: f.get('password_confirmation') }).then(function (j) { if (j) { pwForm.reset(); } });
        });
    }

    var reForm = $('vps-reinstall-form');
    if (reForm) {
        reForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var f = new FormData(reForm);
            msg(T.reinstalling, true);
            post(urls.reinstall, { image: f.get('image'), password: f.get('password'), confirm: f.get('confirm') }).then(function (j) {
                if (j && j.data && j.data.password) {
                    msg(j.message + ' ' + T.new_password_is + ' ' + j.data.password, true);
                    var pw = $('vps-pw'); if (pw) { pw.setAttribute('data-pw', j.data.password); }
                    reForm.reset();
                }
            });
        });
    }

    // Graphs: plain SVG, no library.
    function draw(svgId, series, colors, fmt) {
        var svg = $(svgId); if (!svg) { return; }
        var w = 600, h = 150, pad = 4;
        svg.setAttribute('viewBox', '0 0 ' + w + ' ' + h);
        var all = [].concat.apply([], series).filter(function (v) { return v !== null; });
        var max = Math.max.apply(null, all.concat([1])) * 1.1;
        var html = '';
        for (var g = 1; g < 4; g++) { html += '<line x1="0" x2="' + w + '" y1="' + (h / 4 * g) + '" y2="' + (h / 4 * g) + '" stroke="currentColor" stroke-opacity=".08"/>'; }
        series.forEach(function (s, k) {
            var n = s.length; if (n < 2) { return; }
            var pts = s.map(function (v, i) { return (i / (n - 1) * w).toFixed(1) + ',' + (h - pad - (v === null ? 0 : v) / max * (h - pad * 2)).toFixed(1); });
            html += '<polyline fill="' + colors[k] + '" fill-opacity=".12" stroke="none" points="0,' + h + ' ' + pts.join(' ') + ' ' + w + ',' + h + '"/>';
            html += '<polyline fill="none" stroke="' + colors[k] + '" stroke-width="2" vector-effect="non-scaling-stroke" points="' + pts.join(' ') + '"/>';
        });
        svg.innerHTML = html;
        var last = series.map(function (s) { for (var i = s.length - 1; i >= 0; i--) { if (s[i] !== null) { return s[i]; } } return null; });
        var lbl = $(svgId + '-last'); if (lbl) { lbl.textContent = last.map(function (v) { return v === null ? '-' : fmt(v); }).join(' / '); }
    }

    function graphs(tf) {
        fetch(urls.graphs + '?timeframe=' + tf, { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (g) {
                if (!g.available) { return; }
                var p = g.points;
                var col = function (k) { return p.map(function (x) { return x[k]; }); };
                draw('vps-g-cpu', [col('cpu')], ['#6366f1'], function (v) { return unit(v, 'percent'); });
                draw('vps-g-mem', [col('mem')], ['#ec4899'], function (v) { return unit(v, 'megabyte'); });
                draw('vps-g-net', [col('netin'), col('netout')], ['#10b981', '#3b82f6'], bytes);
                draw('vps-g-io', [col('diskread'), col('diskwrite')], ['#f59e0b', '#64748b'], bytes);
            });
    }
    var tfBox = $('vps-tf');
    if (tfBox) {
        tfBox.querySelectorAll('[data-tf]').forEach(function (b) {
            b.addEventListener('click', function () {
                tfBox.querySelectorAll('[data-tf]').forEach(function (x) { x.classList.remove('on'); });
                b.classList.add('on'); graphs(b.getAttribute('data-tf'));
            });
        });
        graphs('hour');
    }

    function esc(t) { return String(t).replace(/[&<>"']/g, function (c) { return '&#' + c.charCodeAt(0) + ';'; }); }
    function when(ts) { try { return new Date(ts * 1000).toLocaleString(lang); } catch (e) { return ts; } }
    function size(b) { return unit(b / 1073741824, 'gigabyte'); }

    function snapshots() {
        var box = $('vps-snap-list'); if (!box) { return; }
        fetch(urls.snapshots, { headers: { 'Accept': 'application/json' } }).then(function (r) { return r.json(); }).then(function (d) {
            if (!d.available) { box.innerHTML = '<li class="vps-empty">' + esc(d.error || T.unavailable) + '</li>'; return; }
            $('vps-snap-count').textContent = T.used_of.replace(':used', d.snapshots.length).replace(':limit', d.limit);
            box.innerHTML = d.snapshots.length ? d.snapshots.map(function (x) {
                return '<li><span><strong>' + esc(x.name) + '</strong><br><span class="meta">' + esc(when(x.time)) + (x.description ? ' · ' + esc(x.description) : '') + '</span></span>'
                    + (canChange ? '<span class="vps-btns"><button type="button" class="vps-btn" data-snap-rollback="' + esc(x.name) + '">' + esc(T.snapshot_rollback) + '</button>'
                    + '<button type="button" class="vps-btn danger" data-snap-delete="' + esc(x.name) + '">' + esc(T.delete) + '</button></span>' : '') + '</li>';
            }).join('') : '<li class="vps-empty">' + esc(T.snapshots_none) + '</li>';
            var form = $('vps-snap-form'); if (form) { form.querySelector('button').disabled = d.snapshots.length >= d.limit; }
        });
    }

    function backups() {
        var box = $('vps-backup-list'); if (!box) { return; }
        fetch(urls.backups, { headers: { 'Accept': 'application/json' } }).then(function (r) { return r.json(); }).then(function (d) {
            if (!d.available) { box.innerHTML = '<li class="vps-empty">' + esc(d.error || T.unavailable) + '</li>'; return; }
            $('vps-backup-count').textContent = T.used_of.replace(':used', d.backups.length).replace(':limit', d.limit);
            box.innerHTML = d.backups.length ? d.backups.map(function (x) {
                return '<li><span><strong>' + esc(when(x.time)) + '</strong><br><span class="meta">' + esc(size(x.size)) + '</span></span>'
                    + (canChange ? '<span class="vps-btns"><button type="button" class="vps-btn" data-backup-restore="' + esc(x.volid) + '">' + esc(T.backup_restore) + '</button>'
                    + (x.protected ? '' : '<button type="button" class="vps-btn danger" data-backup-delete="' + esc(x.volid) + '">' + esc(T.delete) + '</button>') + '</span>' : '') + '</li>';
            }).join('') : '<li class="vps-empty">' + esc(T.backups_none) + '</li>';
            var now = $('vps-backup-now'); if (now) { now.disabled = d.backups.length >= d.limit; }
        });
    }

    function lists() { snapshots(); backups(); }

    root.addEventListener('click', function (e) {
        var b = e.target.closest('button'); if (!b) { return; }
        if (b.hasAttribute('data-snap-rollback')) {
            var n = b.getAttribute('data-snap-rollback');
            if (!window.confirm(T.confirm_rollback.replace(':name', n))) { return; }
            msg(T.working, true); post(urls.snapshotAction, { action: 'rollback', name: n }).then(lists);
        } else if (b.hasAttribute('data-snap-delete')) {
            var n2 = b.getAttribute('data-snap-delete');
            if (!window.confirm(T.confirm_delete_snapshot.replace(':name', n2))) { return; }
            msg(T.working, true); post(urls.snapshotAction, { action: 'delete', name: n2 }).then(lists);
        } else if (b.hasAttribute('data-backup-restore')) {
            var typed = window.prompt(T.confirm_restore.replace(':word', confirmWord));
            if (typed === null) { return; }
            msg(T.working, true); post(urls.backupAction, { action: 'restore', volid: b.getAttribute('data-backup-restore'), confirm: typed }).then(lists);
        } else if (b.hasAttribute('data-backup-delete')) {
            if (!window.confirm(T.confirm_delete_backup)) { return; }
            msg(T.working, true); post(urls.backupAction, { action: 'delete', volid: b.getAttribute('data-backup-delete') }).then(lists);
        } else if (b.id === 'vps-backup-now') {
            msg(T.working, true); post(urls.backupAction, { action: 'create' }).then(function () { setTimeout(backups, 5000); });
        }
    });
    var snapForm = $('vps-snap-form');
    if (snapForm) {
        snapForm.addEventListener('submit', function (e) {
            e.preventDefault();
            msg(T.working, true);
            post(urls.snapshotAction, { action: 'create', name: new FormData(snapForm).get('name') }).then(function (j) { if (j) { snapForm.reset(); } lists(); });
        });
    }

    refresh();
    lists();
    setInterval(function () { if (!busy && !document.hidden) { refresh(); } }, 15000);
})();
</script>
