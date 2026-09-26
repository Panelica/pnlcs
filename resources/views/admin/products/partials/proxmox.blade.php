{{-- What a Proxmox product sells. The drop-downs are filled from the cluster
     itself (nodes, storages, bridges, templates); until that answer arrives
     each shows the value already saved, so saving without it changes nothing. --}}
@php
    $pve = fn (string $key, $default = '') => $cfg["pve_{$key}"] ?? $default;
    $pveType = $pve('type', $cfg['type'] ?? 'qemu') === 'lxc' ? 'lxc' : 'qemu';
    $choices = collect(is_array($pve('os_choices', [])) ? $pve('os_choices', []) : []);
    $current = fn (string $key, string $legacy = '') => (string) ($pve($key, '') !== '' ? $pve($key) : ($legacy !== '' ? ($cfg[$legacy] ?? '') : ''));
@endphp
<div class="card" data-module-card="proxmox" style="margin-bottom:15px;display:none;">
    <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">
        <div><strong>{{ __('proxmox.product.title') }}</strong> <span style="font-size:11px;color:#888;">&mdash; {{ __('proxmox.product.subtitle') }}</span></div>
        <div style="display:flex;gap:6px;align-items:center;">
            <label style="font-size:12px;color:#666;margin:0;">{{ __('proxmox.product.read_from') }}</label>
            <select id="pve-server" class="form-control" style="font-size:12px;width:auto;">
                @forelse($proxmoxServers as $ps)
                <option value="{{ $ps->id }}">{{ $ps->name }}{{ $ps->active ? '' : ' ('.__('common.status.disabled').')' }}</option>
                @empty
                <option value="">{{ __('admin.products.no_server_for_module') }}</option>
                @endforelse
            </select>
            <button type="button" class="btn btn-default btn-xs" id="pve-refresh">{{ __('proxmox.product.refresh') }}</button>
        </div>
    </div>
    <div class="card-body">
        <input type="hidden" name="pve_section" value="1">
        <div id="pve-note" style="font-size:12px;color:#666;margin-bottom:12px;">{{ __('proxmox.product.loading') }}</div>

        <div style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;">
            <div class="form-group">
                <label class="form-label">{{ __('proxmox.product.type') }}</label>
                <select name="pve_type" id="pve-type" class="form-control">
                    <option value="qemu" @selected($pveType === 'qemu')>{{ __('proxmox.product.type_qemu') }}</option>
                    <option value="lxc" @selected($pveType === 'lxc')>{{ __('proxmox.product.type_lxc') }}</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">{{ __('proxmox.product.node') }}</label>
                <select name="pve_node" data-pve-list="nodes" data-current="{{ $current('node', 'node') }}" class="form-control">
                    <option value="">{{ __('proxmox.product.node_auto') }}</option>
                    @if($current('node', 'node') !== '')<option value="{{ $current('node', 'node') }}" selected>{{ $current('node', 'node') }}</option>@endif
                </select>
            </div>
            <div class="form-group" data-pve-only="qemu" style="grid-column:span 2;">
                <label class="form-label">{{ __('proxmox.product.template') }}</label>
                <select name="pve_template" data-pve-list="templates" data-current="{{ $current('template', 'template_id') }}" class="form-control">
                    <option value="">{{ __('proxmox.product.template_none') }}</option>
                    @if($current('template', 'template_id') !== '')<option value="{{ $current('template', 'template_id') }}" selected>#{{ $current('template', 'template_id') }}</option>@endif
                </select>
                <small style="color:#888;">{{ __('proxmox.product.template_hint') }}</small>
            </div>
            <div class="form-group" data-pve-only="lxc" style="grid-column:span 2;">
                <label class="form-label">{{ __('proxmox.product.ostemplate') }}</label>
                <select name="pve_ostemplate" data-pve-list="ostemplates" data-current="{{ $current('ostemplate', 'os_template') }}" class="form-control">
                    <option value="">&mdash;</option>
                    @if($current('ostemplate', 'os_template') !== '')<option value="{{ $current('ostemplate', 'os_template') }}" selected>{{ $current('ostemplate', 'os_template') }}</option>@endif
                </select>
            </div>
            <div class="form-group" data-pve-only="qemu" style="grid-column:span 2;">
                <label class="form-label">{{ __('proxmox.product.iso') }}</label>
                <select name="pve_iso" data-pve-list="isos" data-current="{{ $current('iso') }}" class="form-control">
                    <option value="">&mdash;</option>
                    @if($current('iso') !== '')<option value="{{ $current('iso') }}" selected>{{ $current('iso') }}</option>@endif
                </select>
                <small style="color:#888;">{{ __('proxmox.product.iso_hint') }}</small>
            </div>
            <div class="form-group">
                <label class="form-label">{{ __('proxmox.product.storage') }}</label>
                <select name="pve_storage" data-pve-list="storages" data-current="{{ $current('storage', 'storage') ?: 'local-lvm' }}" class="form-control">
                    <option value="{{ $current('storage', 'storage') ?: 'local-lvm' }}" selected>{{ $current('storage', 'storage') ?: 'local-lvm' }}</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">{{ __('proxmox.product.bridge') }}</label>
                <select name="pve_bridge" data-pve-list="bridges" data-current="{{ $current('bridge', 'bridge') ?: 'vmbr0' }}" class="form-control">
                    <option value="{{ $current('bridge', 'bridge') ?: 'vmbr0' }}" selected>{{ $current('bridge', 'bridge') ?: 'vmbr0' }}</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">{{ __('proxmox.product.vlan') }}</label>
                <input type="number" min="0" max="4094" name="pve_vlan" value="{{ $pve('vlan', 0) }}" class="form-control">
            </div>
            <div class="form-group">
                <label class="form-label">{{ __('proxmox.product.rate') }}</label>
                <input type="number" min="0" step="0.1" name="pve_rate" value="{{ $pve('rate', 0) }}" class="form-control">
            </div>

            <div class="form-group">
                <label class="form-label">{{ __('proxmox.product.cores') }}</label>
                <input type="number" min="1" name="pve_cores" value="{{ $pve('cores', $cfg['cores'] ?? 1) }}" required class="form-control">
            </div>
            <div class="form-group" data-pve-only="qemu">
                <label class="form-label">{{ __('proxmox.product.sockets') }}</label>
                <input type="number" min="1" max="8" name="pve_sockets" value="{{ $pve('sockets', 1) }}" class="form-control">
            </div>
            <div class="form-group">
                <label class="form-label">{{ __('proxmox.product.cpulimit') }}</label>
                <input type="number" min="0" step="0.1" name="pve_cpulimit" value="{{ $pve('cpulimit', 0) }}" class="form-control">
            </div>
            <div class="form-group">
                <label class="form-label">{{ __('proxmox.product.memory') }}</label>
                <input type="number" min="64" name="pve_memory" value="{{ $pve('memory', $cfg['memory'] ?? 1024) }}" required class="form-control">
            </div>
            <div class="form-group" data-pve-only="lxc">
                <label class="form-label">{{ __('proxmox.product.swap') }}</label>
                <input type="number" min="0" name="pve_swap" value="{{ $pve('swap', 512) }}" class="form-control">
            </div>
            <div class="form-group">
                <label class="form-label">{{ __('proxmox.product.disk') }}</label>
                <input type="number" min="1" name="pve_disk" value="{{ $pve('disk', $cfg['disk'] ?? 20) }}" required class="form-control">
            </div>
            <div class="form-group">
                <label class="form-label">{{ __('proxmox.product.bandwidth') }}</label>
                <input type="number" min="0" name="pve_bandwidth" value="{{ $pve('bandwidth', 0) }}" class="form-control">
            </div>

            <div class="form-group">
                <label class="form-label">{{ __('proxmox.product.snapshots') }}</label>
                <input type="number" min="0" max="50" name="pve_snapshots" value="{{ $pve('snapshots', 0) }}" class="form-control">
            </div>
            <div class="form-group">
                <label class="form-label">{{ __('proxmox.product.backups') }}</label>
                <input type="number" min="0" max="100" name="pve_backups" value="{{ $pve('backups', 0) }}" class="form-control">
            </div>
            <div class="form-group">
                <label class="form-label">{{ __('proxmox.product.ipv4') }}</label>
                <select name="pve_ipv4" class="form-control">
                    <option value="dhcp" @selected($pve('ipv4', 'dhcp') !== 'pool')>{{ __('proxmox.product.ipv4_dhcp') }}</option>
                    <option value="pool" @selected($pve('ipv4', 'dhcp') === 'pool')>{{ __('proxmox.product.ipv4_pool') }}</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">{{ __('proxmox.product.ipv6') }}</label>
                <select name="pve_ipv6" class="form-control">
                    <option value="none" @selected($pve('ipv6', 'none') !== 'auto')>{{ __('proxmox.product.ipv6_none') }}</option>
                    <option value="auto" @selected($pve('ipv6', 'none') === 'auto')>{{ __('proxmox.product.ipv6_auto') }}</option>
                </select>
            </div>
            <div class="form-group" data-pve-only="qemu">
                <label class="form-label">{{ __('proxmox.product.ciuser') }}</label>
                <input type="text" name="pve_ciuser" value="{{ $pve('ciuser', 'root') }}" class="form-control">
            </div>
            <div class="form-group">
                <label class="form-label">{{ __('proxmox.product.nameserver') }}</label>
                <input type="text" name="pve_nameserver" value="{{ $pve('nameserver', '') }}" class="form-control" placeholder="1.1.1.1 8.8.8.8">
            </div>
        </div>

        <div style="display:flex;flex-wrap:wrap;gap:18px;margin:6px 0 14px;">
            <label style="font-size:13px;display:flex;gap:6px;align-items:center;"><input type="checkbox" name="pve_protection" value="1" @checked((bool) $pve('protection', 1))> {{ __('proxmox.product.protection') }}</label>
            <label style="font-size:13px;display:flex;gap:6px;align-items:center;"><input type="checkbox" name="pve_firewall" value="1" @checked((bool) $pve('firewall', 0))> {{ __('proxmox.product.firewall') }}</label>
            <label style="font-size:13px;display:flex;gap:6px;align-items:center;" data-pve-only="lxc"><input type="checkbox" name="pve_nesting" value="1" @checked((bool) $pve('nesting', 0))> {{ __('proxmox.product.nesting') }}</label>
            <label style="font-size:13px;display:flex;gap:6px;align-items:center;" data-pve-only="qemu"><input type="checkbox" name="pve_ciupgrade" value="1" @checked((bool) $pve('ciupgrade', 0))> {{ __('proxmox.product.ciupgrade') }}</label>
        </div>

        <div style="border-top:1px solid #eee;padding-top:12px;">
            <label class="form-label" style="font-weight:700;">{{ __('proxmox.product.os_choices') }}</label>
            <div style="font-size:12px;color:#777;margin-bottom:8px;">{{ __('proxmox.product.os_choices_hint') }}</div>
            <div id="pve-os-choices" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:6px;">
                @foreach($choices as $choice)
                <label data-os-id="{{ $choice['id'] }}" style="display:flex;gap:8px;align-items:center;font-size:12px;border:1px solid #e5e5e5;border-radius:6px;padding:6px 8px;">
                    <input type="checkbox" name="pve_os_choices[]" value="{{ $choice['id'] }}" checked>
                    <input type="text" name="pve_os_names[{{ $choice['id'] }}]" value="{{ $choice['name'] }}" class="form-control" style="font-size:12px;padding:3px 6px;height:auto;">
                </label>
                @endforeach
            </div>
        </div>

        <div style="margin-top:14px;font-size:12px;color:#555;background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;padding:10px 12px;line-height:1.6;">
            {{ trans_markup('proxmox.product.options_help') }}
        </div>
    </div>
</div>

@push('scripts')
<script>
(function () {
    var card = document.querySelector('[data-module-card="proxmox"]');
    if (!card) { return; }
    var typeSel = document.getElementById('pve-type');
    var serverSel = document.getElementById('pve-server');
    var note = document.getElementById('pve-note');
    var osBox = document.getElementById('pve-os-choices');
    var catalog = null;

    function applyType() {
        card.querySelectorAll('[data-pve-only]').forEach(function (el) {
            var on = el.getAttribute('data-pve-only') === typeSel.value;
            el.style.display = on ? '' : 'none';
            el.querySelectorAll('input,select').forEach(function (f) { f.disabled = !on; });
        });
        renderOsChoices();
    }

    function fill(select, items) {
        var current = select.getAttribute('data-current') || '';
        var first = select.options[0] && select.options[0].value === '' ? select.options[0].cloneNode(true) : null;
        select.innerHTML = '';
        if (first) { select.appendChild(first); }
        var seen = false;
        (items || []).forEach(function (it) {
            var o = document.createElement('option');
            o.value = it.id; o.textContent = it.name;
            if (it.id === current) { o.selected = true; seen = true; }
            select.appendChild(o);
        });
        if (current && !seen) {
            // Saved, but the cluster no longer lists it: keep it and say so.
            var o = document.createElement('option');
            o.value = current; o.selected = true;
            o.textContent = current + ' - ' + @json(__('proxmox.product.not_on_cluster'));
            select.appendChild(o);
        }
    }

    function renderOsChoices() {
        if (!catalog) { return; }
        var list = typeSel.value === 'lxc' ? catalog.ostemplates : catalog.templates;
        osBox.querySelectorAll('label[data-os-new]').forEach(function (l) { l.remove(); });
        osBox.querySelectorAll('label[data-os-id]').forEach(function (l) {
            var known = (list || []).some(function (it) { return it.id === l.getAttribute('data-os-id'); });
            l.style.opacity = known ? '' : '.6';
        });
        (list || []).forEach(function (it) {
            if (osBox.querySelector('label[data-os-id="' + CSS.escape(it.id) + '"]')) { return; }
            var l = document.createElement('label');
            l.setAttribute('data-os-id', it.id); l.setAttribute('data-os-new', '1');
            l.style.cssText = 'display:flex;gap:8px;align-items:center;font-size:12px;border:1px solid #e5e5e5;border-radius:6px;padding:6px 8px;';
            var cb = document.createElement('input'); cb.type = 'checkbox'; cb.name = 'pve_os_choices[]'; cb.value = it.id;
            var name = document.createElement('input'); name.type = 'text'; name.name = 'pve_os_names[' + it.id + ']';
            name.value = it.name.replace(/^#\d+\s*/, '').replace(/\s*\((qemu|lxc),.*\)$/, '');
            name.className = 'form-control'; name.style.cssText = 'font-size:12px;padding:3px 6px;height:auto;';
            l.appendChild(cb); l.appendChild(name); osBox.appendChild(l);
        });
    }

    function load() {
        if (!serverSel.value) { note.textContent = @json(__('admin.products.no_server_for_module')); return; }
        note.textContent = @json(__('proxmox.product.loading'));
        var nodeSel = card.querySelector('[data-pve-list="nodes"]');
        var url = @json(route('admin.products.proxmox-catalog')) + '?server_id=' + encodeURIComponent(serverSel.value)
            + '&node=' + encodeURIComponent(nodeSel.value || '');
        fetch(url, { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.ok) { note.textContent = @json(__('proxmox.product.load_failed')) + ' ' + (data.error || ''); note.style.color = '#b91c1c'; return; }
                catalog = data;
                card.querySelectorAll('[data-pve-list]').forEach(function (sel) { fill(sel, data[sel.getAttribute('data-pve-list')]); });
                note.style.color = '#15803d';
                note.textContent = @json(__('proxmox.product.loaded')).replace(':node', data.node)
                    .replace(':templates', (data.templates || []).length).replace(':ostemplates', (data.ostemplates || []).length);
                renderOsChoices();
            })
            .catch(function () { note.textContent = @json(__('proxmox.product.load_failed')); note.style.color = '#b91c1c'; });
    }

    typeSel.addEventListener('change', applyType);
    document.getElementById('pve-refresh').addEventListener('click', load);
    serverSel.addEventListener('change', load);
    card.querySelector('[data-pve-list="nodes"]').addEventListener('change', function () {
        this.setAttribute('data-current', this.value); load();
    });
    card.querySelectorAll('[data-pve-list]').forEach(function (sel) {
        sel.addEventListener('change', function () { sel.setAttribute('data-current', sel.value); });
    });

    window.pnlcsProxmoxCard = { show: function (on) {
        card.style.display = on ? '' : 'none';
        card.querySelectorAll('input,select').forEach(function (f) { f.disabled = !on; });
        if (on) { applyType(); if (!catalog) { load(); } }
    } };
})();
</script>
@endpush
