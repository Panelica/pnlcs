@extends('admin.layouts.app')
@section('title', __('proxmox.images.title', ['name' => $server->name]))
@section('content')
<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;">
    <h1>{{ __('proxmox.images.title', ['name' => $server->name]) }}</h1>
    <a href="{{ route('admin.config.servers') }}" class="btn btn-default btn-sm">&larr; {{ __('proxmox.images.back') }}</a>
</div>

<style>
    .pim-note{font-size:13px;color:#555;margin:0 0 14px;line-height:1.6}
    .pim-bar{display:flex;gap:14px;flex-wrap:wrap;align-items:center;font-size:12.5px;color:#555;margin-bottom:14px}
    .pim-bar b{color:#1f2937}
    .pim-state{display:inline-flex;align-items:center;gap:6px;font-size:12px;font-weight:600;padding:3px 9px;border-radius:99px;white-space:nowrap}
    .pim-state.ok{background:#ecfdf5;color:#047857}
    .pim-state.run{background:#eff6ff;color:#1d4ed8}
    .pim-state.bad{background:#fef2f2;color:#b91c1c;white-space:normal}
    .pim-state.none{background:#f1f5f9;color:#475569}
    .pim-warn{border:1px solid #fcd34d;background:#fffbeb;border-radius:6px;padding:12px 14px;margin-bottom:14px;font-size:13px}
    .pim-warn pre{background:#0f172a;color:#e2e8f0;padding:10px;border-radius:6px;font-size:12px;white-space:pre-wrap;word-break:break-all;margin:8px 0 0}
    .pim-filter{max-width:280px;font-size:13px}
    #pim-lxc-body tr[hidden]{display:none}
</style>

<p class="pim-note">{{ __('proxmox.images.intro') }}</p>
<div id="pim-error" class="alert alert-danger" style="display:none;"></div>
<div id="pim-msg" class="alert" style="display:none;"></div>

<div class="pim-bar" id="pim-bar">{{ __('proxmox.client.loading') }}</div>
<div class="pim-warn" id="pim-rights" style="display:none;">
    <strong>{{ __('proxmox.images.rights_title') }}</strong>
    <div id="pim-rights-list" style="margin-top:4px;"></div>
    <div style="margin-top:6px;">{{ __('proxmox.images.rights_hint') }}</div>
    <pre id="pim-rights-cmd">{{ \Modules\Servers\Proxmox\ProxmoxImages::rightsCommands() }}</pre>
</div>

<div class="card" style="margin-bottom:15px;">
    <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">
        <div><strong>{{ __('proxmox.images.kvm_title') }}</strong> <span style="font-size:12px;color:#888;">&mdash; {{ __('proxmox.images.kvm_hint') }}</span></div>
        <label style="font-size:12px;color:#555;display:flex;gap:6px;align-items:center;margin:0;">{{ __('proxmox.images.disk_storage') }}
            <select id="pim-disk" class="form-control" style="width:auto;font-size:12px;"></select>
        </label>
    </div>
    <div class="card-body" style="padding:0;">
        <table class="data-table">
            <thead><tr><th>{{ __('proxmox.images.col_system') }}</th><th>{{ __('proxmox.images.col_source') }}</th><th>{{ __('proxmox.images.col_state') }}</th><th style="text-align:right;">{{ __('common.table.actions') }}</th></tr></thead>
            <tbody id="pim-kvm-body"><tr><td colspan="4" style="color:#888;">{{ __('proxmox.client.loading') }}</td></tr></tbody>
        </table>
    </div>
</div>

<div class="card" style="margin-bottom:15px;">
    <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">
        <div><strong>{{ __('proxmox.images.lxc_title') }}</strong> <span style="font-size:12px;color:#888;">&mdash; {{ __('proxmox.images.lxc_hint') }}</span></div>
        <input type="search" id="pim-filter" class="form-control pim-filter" placeholder="{{ __('proxmox.images.filter') }}" aria-label="{{ __('proxmox.images.filter') }}">
    </div>
    <div class="card-body" style="padding:0;">
        <table class="data-table">
            <thead><tr><th>{{ __('proxmox.images.col_system') }}</th><th>{{ __('proxmox.images.col_about') }}</th><th>{{ __('proxmox.images.col_state') }}</th><th style="text-align:right;">{{ __('common.table.actions') }}</th></tr></thead>
            <tbody id="pim-lxc-body"><tr><td colspan="4" style="color:#888;">{{ __('proxmox.client.loading') }}</td></tr></tbody>
        </table>
    </div>
</div>

<p class="pim-note">{{ __('proxmox.images.after') }}</p>
@endsection

@push('scripts')
<script>
(function () {
    var T = @json(__('proxmox.images'));
    var urls = { status: @json(route('admin.config.servers.images.status', $server)), install: @json(route('admin.config.servers.images.install', $server)) };
    var csrf = @json(csrf_token());
    var $ = function (id) { return document.getElementById(id); };
    var timer = null, data = null;
    function esc(t) { return String(t === null || t === undefined ? '' : t).replace(/[&<>"']/g, function (c) { return '&#' + c.charCodeAt(0) + ';'; }); }
    function fill(s, map) { Object.keys(map).forEach(function (k) { s = String(s).split(':' + k).join(map[k]); }); return s; }
    function say(text, ok) { var m = $('pim-msg'); m.style.display = text ? '' : 'none'; m.className = 'alert ' + (ok ? 'alert-success' : 'alert-danger'); m.textContent = text || ''; }

    function state(row, installedText) {
        var j = row.job;
        if (j && j.step && j.step !== 'done' && j.step !== 'failed') {
            // A download reports "... 57% 8.1M 18s"; only the share done is worth showing.
            var pct = j.progress ? String(j.progress).match(/(\d{1,3})%/) : null;
            return '<span class="pim-state run">' + esc(T['step_' + j.step] || j.step) + (pct ? ' ' + esc(pct[1]) + '%' : '') + '</span>';
        }
        if (installedText) { return '<span class="pim-state ok">' + esc(installedText) + '</span>'; }
        if (j && j.step === 'failed') { return '<span class="pim-state bad">' + esc(fill(T.failed, { error: j.error || '' })) + '</span>'; }
        return '<span class="pim-state none">' + esc(T.not_installed) + '</span>';
    }
    function button(kind, id, row, installed) {
        var running = row.job && row.job.step && row.job.step !== 'done' && row.job.step !== 'failed';
        if (installed || running) { return ''; }
        return '<button type="button" class="btn btn-primary btn-xs" data-install="' + kind + '" data-id="' + esc(id) + '">' + esc(T.install) + '</button>';
    }

    function render(d) {
        data = d;
        if (!d.ok) { $('pim-error').style.display = ''; $('pim-error').textContent = d.error || T.load_failed; return; }
        $('pim-error').style.display = 'none';
        $('pim-bar').innerHTML = esc(T.node) + ' <b>' + esc(d.node) + '</b> &middot; ' + esc(T.import_storage) + ' <b>' + esc((d.import_storages || []).join(', ') || '-') + '</b> &middot; '
            + esc(T.template_storage) + ' <b>' + esc((d.template_storages || []).join(', ') || '-') + '</b>';
        var disk = $('pim-disk'), chosen = disk.value;
        disk.innerHTML = (d.disk_storages || []).map(function (s) { return '<option value="' + esc(s) + '"' + (s === chosen ? ' selected' : '') + '>' + esc(s) + '</option>'; }).join('');
        if (d.missing && d.missing.length) { $('pim-rights').style.display = ''; $('pim-rights-list').textContent = d.missing.join(', '); } else { $('pim-rights').style.display = 'none'; }

        $('pim-kvm-body').innerHTML = (d.kvm || []).map(function (r) {
            var installed = r.template ? fill(T.installed_template, { vmid: r.template }) : '';
            return '<tr><td style="font-weight:600;">' + esc(r.name) + '</td><td style="font-size:12px;color:#666;">' + esc(r.source) + '</td><td>' + state(r, installed) + '</td><td style="text-align:right;">' + button('kvm', r.id, r, !!r.template) + '</td></tr>';
        }).join('');
        if (!(d.import_storages || []).length) {
            $('pim-kvm-body').insertAdjacentHTML('afterbegin', '<tr><td colspan="4" style="color:#b45309;">' + esc(fill(T.no_import_storage, { node: d.node })) + '</td></tr>');
        }
        var q = ($('pim-filter').value || '').toLowerCase();
        $('pim-lxc-body').innerHTML = (d.lxc || []).map(function (r) {
            var hay = (r.name + ' ' + r.description).toLowerCase();
            return '<tr' + (q && hay.indexOf(q) === -1 ? ' hidden' : '') + ' data-find="' + esc(hay) + '"><td style="font-weight:600;">' + esc(r.name) + '</td><td style="font-size:12px;color:#666;">' + esc(r.description) + '</td><td>' + state(r, r.installed ? T.installed : '') + '</td><td style="text-align:right;">' + button('lxc', r.id, r, r.installed) + '</td></tr>';
        }).join('') || '<tr><td colspan="4" style="color:#888;">' + esc(T.none) + '</td></tr>';

        clearTimeout(timer);
        if (d.busy) { timer = setTimeout(load, 4000); }
    }
    function load() {
        return fetch(urls.status, { headers: { 'Accept': 'application/json' } }).then(function (r) { return r.json(); }).then(render)
            .catch(function () { $('pim-error').style.display = ''; $('pim-error').textContent = T.load_failed; });
    }

    document.addEventListener('click', function (e) {
        var b = e.target.closest('[data-install]'); if (!b) { return; }
        b.disabled = true;
        fetch(urls.install, { method: 'POST', headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify({ kind: b.getAttribute('data-install'), id: b.getAttribute('data-id'), disk_storage: $('pim-disk').value }) })
            .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
            .then(function (res) { var j = res.j || {}; say(j.message || (j.errors ? Object.values(j.errors).flat().join(' ') : T.load_failed), res.ok && j.success); load(); })
            .catch(function () { say(T.load_failed, false); b.disabled = false; });
    });
    $('pim-filter').addEventListener('input', function () {
        var q = this.value.toLowerCase();
        document.querySelectorAll('#pim-lxc-body tr[data-find]').forEach(function (tr) { tr.hidden = q !== '' && tr.getAttribute('data-find').indexOf(q) === -1; });
    });
    load();
})();
</script>
@endpush
