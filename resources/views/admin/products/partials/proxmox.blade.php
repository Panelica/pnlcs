{{-- What a Proxmox product sells. The drop-downs are filled from the cluster
     itself (nodes, storages, bridges, templates); until that answer arrives
     each shows the value already saved, so saving without it changes nothing. --}}
@php
    $pve = fn (string $key, $default = '') => $cfg["pve_{$key}"] ?? $default;
    $pveType = $pve('type', $cfg['type'] ?? 'qemu') === 'lxc' ? 'lxc' : 'qemu';
    $choices = collect(is_array($pve('os_choices', [])) ? $pve('os_choices', []) : []);
    $current = fn (string $key, string $legacy = '') => (string) ($pve($key, '') !== '' ? $pve($key) : ($legacy !== '' ? ($cfg[$legacy] ?? '') : ''));
@endphp
@php
    $pveMemory = (int) $pve('memory', $cfg['memory'] ?? 1024);
    $pveCores = (int) $pve('cores', $cfg['cores'] ?? 1);
    $pveDisk = (int) $pve('disk', $cfg['disk'] ?? 20);
    $pveCurrency = \App\Models\Currency::getDefault();
    $pveLinked = $pveOrderOptions ?? [];
    $pveLinkedKeys = collect($pveLinked)->pluck('key')->all();
    $pveTiers = [
        'memory' => collect([$pveMemory, $pveMemory * 2, $pveMemory * 4])->map(fn ($v, $i) => ['value' => $v, 'price' => [0, 5, 12][$i]])->all(),
        'cores' => collect([$pveCores, $pveCores * 2, $pveCores * 4])->map(fn ($v, $i) => ['value' => $v, 'price' => [0, 4, 10][$i]])->all(),
        'disk' => collect([$pveDisk, $pveDisk * 2, $pveDisk * 4])->map(fn ($v, $i) => ['value' => $v, 'price' => [0, 2, 5][$i]])->all(),
    ];
    $pvePresets = [[1, 1024, 20], [1, 2048, 40], [2, 4096, 80], [4, 8192, 160], [8, 16384, 320]];
@endphp
<style>
    .pve-card .pve-sec{border:1px solid #e5e7eb;border-radius:8px;padding:14px 16px 4px;margin-bottom:14px;background:#fff}
    .pve-card .pve-sec-h{display:flex;align-items:baseline;gap:10px;flex-wrap:wrap;margin-bottom:12px}
    .pve-card .pve-num{display:inline-flex;align-items:center;justify-content:center;width:22px;height:22px;border-radius:50%;background:#1a4d80;color:#fff;font-size:12px;font-weight:700;flex:none}
    .pve-card .pve-sec-h strong{font-size:14px}
    .pve-card .pve-sec-h small{color:#6b7280;font-size:12px;flex-basis:100%;padding-inline-start:32px;margin-top:-4px}
    .pve-card .pve-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}
    .pve-card .pve-span2{grid-column:span 2}
    .pve-card .pve-sum{display:flex;flex-wrap:wrap;gap:8px;align-items:center;background:#f1f5fb;border:1px solid #dbe5f3;border-radius:8px;padding:10px 12px;margin-bottom:14px;font-size:12.5px}
    .pve-card .pve-sum b{font-size:12px;color:#1a4d80;margin-inline-end:4px}
    .pve-card .pve-chip{background:#fff;border:1px solid #dbe5f3;border-radius:99px;padding:3px 10px;font-weight:600;color:#1f2937;white-space:nowrap}
    .pve-card .pve-chip span{color:#6b7280;font-weight:500;margin-inline-end:4px}
    .pve-card .pve-presets{display:flex;gap:6px;flex-wrap:wrap;align-items:center;margin-bottom:12px;font-size:12px;color:#6b7280}
    .pve-card .pve-checks{display:flex;flex-wrap:wrap;gap:18px;margin:2px 0 12px}
    .pve-card .pve-checks label{font-size:13px;display:flex;gap:6px;align-items:center;margin:0}
    .pve-card .pve-opt{border:1px solid #e5e7eb;border-radius:8px;padding:10px 12px;margin-bottom:10px}
    .pve-card .pve-opt-h{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
    .pve-card .pve-opt-h label{margin:0;font-weight:700;font-size:13px;display:flex;gap:6px;align-items:center}
    .pve-card .pve-opt-body{margin-top:10px}
    .pve-card .pve-opt table{width:100%;border-collapse:collapse;font-size:12.5px}
    .pve-card .pve-opt th{text-align:start;color:#6b7280;font-weight:600;padding:4px 6px;font-size:11.5px}
    .pve-card .pve-opt td{padding:4px 6px}
    .pve-card .pve-opt td input{font-size:12.5px;padding:5px 8px;height:auto}
    .pve-card .pve-linked{width:100%;border-collapse:collapse;font-size:12.5px;margin-bottom:10px}
    .pve-card .pve-linked th,.pve-card .pve-linked td{border-bottom:1px solid #eef0f3;padding:7px 8px;text-align:start;vertical-align:top}
    .pve-card .pve-linked th{color:#6b7280;font-weight:600;font-size:11.5px}
    @media (max-width:900px){.pve-card .pve-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
    @media (max-width:560px){.pve-card .pve-grid{grid-template-columns:minmax(0,1fr)}.pve-card .pve-span2{grid-column:auto}}
</style>
<div class="card pve-card" data-module-card="proxmox" style="margin-bottom:15px;display:none;">
    <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">
        <div><strong>{{ __('proxmox.product.title') }}</strong> <span style="font-size:11px;color:#888;">&mdash; {{ __('proxmox.product.subtitle') }}</span></div>
        <div style="display:flex;gap:6px;align-items:center;">
            <label for="pve-server" style="font-size:12px;color:#666;margin:0;">{{ __('proxmox.product.read_from') }}</label>
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
    <div class="card-body" style="background:#fafbfc;">
        <input type="hidden" name="pve_section" value="1">
        <div id="pve-note" style="font-size:12px;color:#666;margin-bottom:12px;">{{ __('proxmox.product.loading') }}</div>

        <div class="pve-sum" id="pve-summary" aria-live="polite"><b>{{ __('proxmox.product.summary_label') }}</b></div>

        {{-- 1. Resources --}}
        <div class="pve-sec">
            <div class="pve-sec-h"><span class="pve-num">1</span><strong>{{ __('proxmox.product.sec_resources') }}</strong><small>{{ __('proxmox.product.sec_resources_hint') }}</small></div>
            <div class="pve-presets">
                <span>{{ __('proxmox.product.presets') }}</span>
                @foreach($pvePresets as [$pc, $pm, $pd])
                <button type="button" class="btn btn-default btn-xs" data-pve-preset="{{ $pc }},{{ $pm }},{{ $pd }}">{{ __('proxmox.product.preset_value', ['cores' => $pc, 'memory' => $pm / 1024, 'disk' => $pd]) }}</button>
                @endforeach
            </div>
            <div class="pve-grid">
                <div class="form-group">
                    <label class="form-label" for="pve-cores">{{ __('proxmox.product.cores') }}</label>
                    <input id="pve-cores" type="number" min="1" name="pve_cores" value="{{ $pveCores }}" required class="form-control" data-pve-sum>
                </div>
                <div class="form-group">
                    <label class="form-label" for="pve-memory">{{ __('proxmox.product.memory') }}</label>
                    <input id="pve-memory" type="number" min="64" step="64" name="pve_memory" value="{{ $pveMemory }}" required class="form-control" data-pve-sum>
                </div>
                <div class="form-group">
                    <label class="form-label" for="pve-disk">{{ __('proxmox.product.disk') }}</label>
                    <input id="pve-disk" type="number" min="1" name="pve_disk" value="{{ $pveDisk }}" required class="form-control" data-pve-sum>
                </div>
                <div class="form-group">
                    <label class="form-label" for="pve-bandwidth">{{ __('proxmox.product.bandwidth') }}</label>
                    <input id="pve-bandwidth" type="number" min="0" name="pve_bandwidth" value="{{ $pve('bandwidth', 0) }}" class="form-control" data-pve-sum>
                </div>
                <div class="form-group" data-pve-only="qemu">
                    <label class="form-label" for="pve-sockets">{{ __('proxmox.product.sockets') }}</label>
                    <input id="pve-sockets" type="number" min="1" max="8" name="pve_sockets" value="{{ $pve('sockets', 1) }}" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label" for="pve-cpulimit">{{ __('proxmox.product.cpulimit') }}</label>
                    <input id="pve-cpulimit" type="number" min="0" step="0.1" name="pve_cpulimit" value="{{ $pve('cpulimit', 0) }}" class="form-control">
                </div>
                <div class="form-group" data-pve-only="lxc">
                    <label class="form-label" for="pve-swap">{{ __('proxmox.product.swap') }}</label>
                    <input id="pve-swap" type="number" min="0" name="pve_swap" value="{{ $pve('swap', 512) }}" class="form-control">
                </div>
            </div>
        </div>

        {{-- 2. Image and placement --}}
        <div class="pve-sec">
            <div class="pve-sec-h"><span class="pve-num">2</span><strong>{{ __('proxmox.product.sec_image') }}</strong><small>{{ __('proxmox.product.sec_image_hint') }}</small></div>
            <div class="pve-grid">
                <div class="form-group">
                    <label class="form-label" for="pve-type">{{ __('proxmox.product.type') }}</label>
                    <select name="pve_type" id="pve-type" class="form-control" data-pve-sum>
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
                <div class="form-group pve-span2" data-pve-only="qemu">
                    <label class="form-label">{{ __('proxmox.product.template') }}</label>
                    <select name="pve_template" data-pve-list="templates" data-current="{{ $current('template', 'template_id') }}" class="form-control" data-pve-sum>
                        <option value="">{{ __('proxmox.product.template_none') }}</option>
                        @if($current('template', 'template_id') !== '')<option value="{{ $current('template', 'template_id') }}" selected>#{{ $current('template', 'template_id') }}</option>@endif
                    </select>
                    <small style="color:#888;">{{ __('proxmox.product.template_hint') }}</small>
                </div>
                <div class="form-group pve-span2" data-pve-only="lxc">
                    <label class="form-label">{{ __('proxmox.product.ostemplate') }}</label>
                    <select name="pve_ostemplate" data-pve-list="ostemplates" data-current="{{ $current('ostemplate', 'os_template') }}" class="form-control" data-pve-sum>
                        <option value="">&mdash;</option>
                        @if($current('ostemplate', 'os_template') !== '')<option value="{{ $current('ostemplate', 'os_template') }}" selected>{{ $current('ostemplate', 'os_template') }}</option>@endif
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">{{ __('proxmox.product.storage') }}</label>
                    <select name="pve_storage" data-pve-list="storages" data-current="{{ $current('storage', 'storage') ?: 'local-lvm' }}" class="form-control">
                        <option value="{{ $current('storage', 'storage') ?: 'local-lvm' }}" selected>{{ $current('storage', 'storage') ?: 'local-lvm' }}</option>
                    </select>
                </div>
                <div class="form-group pve-span2" data-pve-only="qemu">
                    <label class="form-label">{{ __('proxmox.product.iso') }}</label>
                    <select name="pve_iso" data-pve-list="isos" data-current="{{ $current('iso') }}" class="form-control">
                        <option value="">&mdash;</option>
                        @if($current('iso') !== '')<option value="{{ $current('iso') }}" selected>{{ $current('iso') }}</option>@endif
                    </select>
                    <small style="color:#888;">{{ __('proxmox.product.iso_hint') }}</small>
                </div>
                <div class="form-group" data-pve-only="qemu">
                    <label class="form-label" for="pve-ciuser">{{ __('proxmox.product.ciuser') }}</label>
                    <input id="pve-ciuser" type="text" name="pve_ciuser" value="{{ $pve('ciuser', 'root') }}" class="form-control">
                </div>
            </div>
            <div class="pve-checks">
                <label data-pve-only="qemu"><input type="checkbox" name="pve_ciupgrade" value="1" @checked((bool) $pve('ciupgrade', 0))> {{ __('proxmox.product.ciupgrade') }}</label>
                <label data-pve-only="lxc"><input type="checkbox" name="pve_nesting" value="1" @checked((bool) $pve('nesting', 0))> {{ __('proxmox.product.nesting') }}</label>
                <label><input type="checkbox" name="pve_protection" value="1" @checked((bool) $pve('protection', 1))> {{ __('proxmox.product.protection') }}</label>
            </div>
        </div>

        {{-- 3. Network --}}
        <div class="pve-sec">
            <div class="pve-sec-h"><span class="pve-num">3</span><strong>{{ __('proxmox.product.sec_network') }}</strong><small>{{ __('proxmox.product.sec_network_hint') }}</small></div>
            <div class="pve-grid">
                <div class="form-group">
                    <label class="form-label">{{ __('proxmox.product.bridge') }}</label>
                    <select name="pve_bridge" data-pve-list="bridges" data-current="{{ $current('bridge', 'bridge') ?: 'vmbr0' }}" class="form-control">
                        <option value="{{ $current('bridge', 'bridge') ?: 'vmbr0' }}" selected>{{ $current('bridge', 'bridge') ?: 'vmbr0' }}</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="pve-ipv4">{{ __('proxmox.product.ipv4') }}</label>
                    <select id="pve-ipv4" name="pve_ipv4" class="form-control" data-pve-sum>
                        <option value="dhcp" @selected($pve('ipv4', 'dhcp') !== 'pool')>{{ __('proxmox.product.ipv4_dhcp') }}</option>
                        <option value="pool" @selected($pve('ipv4', 'dhcp') === 'pool')>{{ __('proxmox.product.ipv4_pool') }}</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="pve-ipv6">{{ __('proxmox.product.ipv6') }}</label>
                    <select id="pve-ipv6" name="pve_ipv6" class="form-control">
                        <option value="none" @selected($pve('ipv6', 'none') !== 'auto')>{{ __('proxmox.product.ipv6_none') }}</option>
                        <option value="auto" @selected($pve('ipv6', 'none') === 'auto')>{{ __('proxmox.product.ipv6_auto') }}</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="pve-nameserver">{{ __('proxmox.product.nameserver') }}</label>
                    <input id="pve-nameserver" type="text" name="pve_nameserver" value="{{ $pve('nameserver', '') }}" class="form-control" placeholder="1.1.1.1 8.8.8.8">
                </div>
                <div class="form-group">
                    <label class="form-label" for="pve-vlan">{{ __('proxmox.product.vlan') }}</label>
                    <input id="pve-vlan" type="number" min="0" max="4094" name="pve_vlan" value="{{ $pve('vlan', 0) }}" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label" for="pve-rate">{{ __('proxmox.product.rate') }}</label>
                    <input id="pve-rate" type="number" min="0" step="0.1" name="pve_rate" value="{{ $pve('rate', 0) }}" class="form-control">
                </div>
            </div>
            <div class="pve-checks">
                <label><input type="checkbox" name="pve_firewall" value="1" @checked((bool) $pve('firewall', 0))> {{ __('proxmox.product.firewall') }}</label>
            </div>
        </div>

        {{-- 4. What the customer may do --}}
        <div class="pve-sec">
            <div class="pve-sec-h"><span class="pve-num">4</span><strong>{{ __('proxmox.product.sec_customer') }}</strong><small>{{ __('proxmox.product.sec_customer_hint') }}</small></div>
            <div class="pve-grid">
                <div class="form-group">
                    <label class="form-label" for="pve-snapshots">{{ __('proxmox.product.snapshots') }}</label>
                    <input id="pve-snapshots" type="number" min="0" max="50" name="pve_snapshots" value="{{ $pve('snapshots', 0) }}" class="form-control" data-pve-sum>
                </div>
                <div class="form-group">
                    <label class="form-label" for="pve-backups">{{ __('proxmox.product.backups') }}</label>
                    <input id="pve-backups" type="number" min="0" max="100" name="pve_backups" value="{{ $pve('backups', 0) }}" class="form-control" data-pve-sum>
                </div>
            </div>
            <div style="font-weight:700;font-size:13px;margin:4px 0 2px;">{{ __('proxmox.product.os_choices') }}</div>
            <div style="font-size:12px;color:#777;margin-bottom:8px;">{{ __('proxmox.product.os_choices_hint') }}</div>
            <div id="pve-os-choices" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:6px;margin-bottom:12px;">
                @foreach($choices as $choice)
                <label data-os-id="{{ $choice['id'] }}" style="display:flex;gap:8px;align-items:center;font-size:12px;border:1px solid #e5e5e5;border-radius:6px;padding:6px 8px;background:#fff;margin:0;">
                    <input type="checkbox" name="pve_os_choices[]" value="{{ $choice['id'] }}" checked>
                    <input type="text" name="pve_os_names[{{ $choice['id'] }}]" value="{{ $choice['name'] }}" class="form-control" style="font-size:12px;padding:3px 6px;height:auto;" aria-label="{{ __('proxmox.product.opt_label') }}">
                </label>
                @endforeach
            </div>
        </div>

        {{-- 5. Order options --}}
        <div class="pve-sec" id="pve-options">
            <div class="pve-sec-h"><span class="pve-num">5</span><strong>{{ __('proxmox.product.sec_options') }}</strong><small>{{ __('proxmox.product.sec_options_hint') }}</small></div>
            <div id="pve-linked" @if(empty($pveLinked)) style="display:none" @endif>
                <div style="font-weight:700;font-size:12.5px;margin-bottom:6px;">{{ __('proxmox.product.opt_existing') }}</div>
                <table class="pve-linked"><thead><tr><th>{{ __('proxmox.product.opt_title') }}</th><th>{{ __('proxmox.product.opt_choices') }}</th></tr></thead><tbody id="pve-linked-rows">
                    @foreach($pveLinked as $opt)
                    <tr><td><strong>{{ $opt['title'] }}</strong><br><span style="color:#888;font-size:11.5px;">{{ $opt['group'] }}</span></td>
                        <td>@foreach($opt['choices'] as $c)<span class="pve-chip" style="display:inline-block;margin:0 4px 4px 0;">{{ $c['label'] }} <span>{{ $c['monthly'] > 0 ? '+'.number_format($c['monthly'], 2) : __('proxmox.product.opt_included') }}</span></span>@endforeach</td></tr>
                    @endforeach
                </tbody></table>
                <a href="{{ route('admin.config.config-options') }}" class="btn btn-default btn-xs" style="margin-bottom:12px;">{{ __('proxmox.product.opt_edit') }}</a>
            </div>
            <div id="pve-builder" @if(count($pveLinkedKeys) >= count(\Modules\Servers\Proxmox\ProxmoxOrderOptions::KEYS)) style="display:none" @endif>
                @foreach(\Modules\Servers\Proxmox\ProxmoxOrderOptions::KEYS as $key)
                <div class="pve-opt" data-opt="{{ $key }}" @if(in_array($key, $pveLinkedKeys, true)) style="display:none" data-linked="1" @endif>
                    <div class="pve-opt-h">
                        <label><input type="checkbox" data-opt-on> {{ __('proxmox.product.opt_'.$key) }}</label>
                        <input type="text" data-opt-title hidden value="{{ __('proxmox.product.opt_'.$key) }}" class="form-control" style="max-width:220px;font-size:12.5px;padding:4px 8px;height:auto;" aria-label="{{ __('proxmox.product.opt_title') }}">
                    </div>
                    <div class="pve-opt-body" hidden>
                        <table>
                            <thead><tr>
                                <th>{{ $key === 'os' ? __('proxmox.product.opt_os_template') : __('proxmox.product.'.$key) }}</th>
                                <th>{{ __('proxmox.product.opt_label') }}</th>
                                <th>{{ __('proxmox.product.opt_price', ['currency' => $pveCurrency?->code ?? '']) }}</th>
                                <th></th>
                            </tr></thead>
                            <tbody data-opt-rows>
                                @if($key !== 'os')
                                @foreach($pveTiers[$key] as $tier)
                                <tr>
                                    <td><input type="number" min="1" data-v value="{{ $tier['value'] }}" class="form-control"></td>
                                    <td><input type="text" data-l value="{{ \Modules\Servers\Proxmox\ProxmoxOrderOptions::defaultLabel($key, (string) $tier['value']) }}" class="form-control"></td>
                                    <td><input type="number" min="0" step="0.01" data-p value="{{ number_format($tier['price'], 2, '.', '') }}" class="form-control"></td>
                                    <td><button type="button" class="btn btn-default btn-xs" data-opt-remove>{{ __('proxmox.product.opt_remove') }}</button></td>
                                </tr>
                                @endforeach
                                @endif
                            </tbody>
                        </table>
                        @if($key === 'os')
                        <div style="font-size:12px;color:#777;margin-top:4px;">{{ __('proxmox.product.opt_os_hint') }}</div>
                        @else
                        <button type="button" class="btn btn-default btn-xs" data-opt-add style="margin-top:6px;">{{ __('proxmox.product.opt_add') }}</button>
                        @endif
                    </div>
                </div>
                @endforeach
                <div style="font-size:12px;color:#666;margin:4px 0 10px;">{{ __('proxmox.product.opt_note', ['currency' => $pveCurrency?->code ?? '']) }}</div>
                <div id="pve-opt-msg" style="display:none;font-size:12.5px;font-weight:600;margin-bottom:10px;padding:8px 10px;border-radius:6px;"></div>
                <button type="button" class="btn btn-primary btn-sm" id="pve-opt-create" data-url="{{ $product->exists ? route('admin.products.proxmox-options', $product) : '' }}">{{ __('proxmox.product.opt_create') }}</button>
            </div>
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
                summary(); osRows();
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

    // ---- what each order gets, kept in step with the form ----
    var lang = document.documentElement.lang || 'en';
    var S = @json(__('proxmox.product'));
    function gb(mb) { try { return new Intl.NumberFormat(lang, { style: 'unit', unit: mb >= 1024 ? 'gigabyte' : 'megabyte', maximumFractionDigits: 1 }).format(mb >= 1024 ? mb / 1024 : mb); } catch (e) { return mb + ' MB'; } }
    function gig(n) { try { return new Intl.NumberFormat(lang, { style: 'unit', unit: 'gigabyte' }).format(n); } catch (e) { return n + ' GB'; } }
    function val(name) { var f = card.querySelector('[name="' + name + '"]'); return f ? f.value : ''; }
    function imageText() {
        var sel = card.querySelector('[name="' + (typeSel.value === 'lxc' ? 'pve_ostemplate' : 'pve_template') + '"]');
        if (!sel || !sel.value) { return ''; }
        var named = osBox.querySelector('input[name="pve_os_names[' + CSS.escape(sel.value) + ']"]');
        return named && named.value ? named.value : sel.options[sel.selectedIndex].text.replace(/^#\d+\s*/, '').replace(/\s*\((qemu|lxc),.*\)$/, '');
    }
    function chip(label, value) { return '<span class="pve-chip"><span>' + esc(label) + '</span>' + esc(value) + '</span>'; }
    function esc(t) { return String(t).replace(/[&<>"']/g, function (c) { return '&#' + c.charCodeAt(0) + ';'; }); }
    function summary() {
        var box = document.getElementById('pve-summary'); if (!box) { return; }
        var bw = parseInt(val('pve_bandwidth') || '0', 10);
        var html = '<b>' + esc(S.summary_label) + '</b>'
            + chip(S.chip_cores, val('pve_cores') || '-')
            + chip(S.chip_memory, gb(parseInt(val('pve_memory') || '0', 10)))
            + chip(S.chip_disk, gig(parseInt(val('pve_disk') || '0', 10)))
            + chip(S.chip_traffic, bw > 0 ? gig(bw) : S.chip_unlimited)
            + chip(S.chip_address, val('pve_ipv4') === 'pool' ? S.chip_address_pool : S.chip_address_dhcp)
            + chip(S.chip_snapshots, val('pve_snapshots') || '0')
            + chip(S.chip_backups, val('pve_backups') || '0');
        var img = imageText(); if (img) { html += chip(S.chip_image, img); }
        box.innerHTML = html;
    }
    card.addEventListener('input', function (e) { if (e.target.closest('[data-pve-sum], #pve-os-choices')) { summary(); } });
    card.addEventListener('change', function (e) { if (e.target.closest('[data-pve-sum], #pve-os-choices')) { summary(); osRows(); } });
    card.querySelectorAll('[data-pve-preset]').forEach(function (b) {
        b.addEventListener('click', function () {
            var v = b.getAttribute('data-pve-preset').split(',');
            card.querySelector('[name="pve_cores"]').value = v[0];
            card.querySelector('[name="pve_memory"]').value = v[1];
            card.querySelector('[name="pve_disk"]').value = v[2];
            summary();
        });
    });

    // ---- order options builder ----
    var builder = document.getElementById('pve-builder');
    function osRows() {
        var wrap = builder && builder.querySelector('[data-opt="os"] [data-opt-rows]'); if (!wrap) { return; }
        var seen = {}, rows = [];
        var base = card.querySelector('[name="' + (typeSel.value === 'lxc' ? 'pve_ostemplate' : 'pve_template') + '"]');
        if (base && base.value) { rows.push([base.value, imageText()]); seen[base.value] = 1; }
        osBox.querySelectorAll('input[name="pve_os_choices[]"]:checked').forEach(function (cb) {
            if (seen[cb.value]) { return; } seen[cb.value] = 1;
            var n = osBox.querySelector('input[name="pve_os_names[' + CSS.escape(cb.value) + ']"]');
            rows.push([cb.value, n ? n.value : cb.value]);
        });
        var prices = {};
        wrap.querySelectorAll('tr').forEach(function (tr) { prices[tr.getAttribute('data-os')] = tr.querySelector('[data-p]').value; });
        wrap.innerHTML = rows.length ? rows.map(function (r) {
            return '<tr data-os="' + esc(r[0]) + '"><td><code>' + esc(r[0]) + '</code><input type="hidden" data-v value="' + esc(r[0]) + '"></td>'
                + '<td><input type="text" data-l class="form-control" value="' + esc(r[1]) + '"></td>'
                + '<td><input type="number" min="0" step="0.01" data-p class="form-control" value="' + esc(prices[r[0]] || '0.00') + '"></td><td></td></tr>';
        }).join('') : '<tr><td colspan="4" style="color:#b45309">' + esc(S.opt_os_none) + '</td></tr>';
    }
    if (builder) {
        builder.querySelectorAll('[data-opt]').forEach(function (box) {
            var on = box.querySelector('[data-opt-on]'), body = box.querySelector('.pve-opt-body');
            on.addEventListener('change', function () { body.hidden = !on.checked; box.querySelector('[data-opt-title]').hidden = !on.checked; if (box.getAttribute('data-opt') === 'os') { osRows(); } });
            var add = box.querySelector('[data-opt-add]');
            if (add) {
                add.addEventListener('click', function () {
                    var tr = document.createElement('tr');
                    tr.innerHTML = '<td><input type="number" min="1" data-v class="form-control"></td><td><input type="text" data-l class="form-control" placeholder="' + esc(S.opt_label_auto) + '"></td>'
                        + '<td><input type="number" min="0" step="0.01" data-p class="form-control" value="0.00"></td><td><button type="button" class="btn btn-default btn-xs" data-opt-remove>' + esc(S.opt_remove) + '</button></td>';
                    box.querySelector('[data-opt-rows]').appendChild(tr);
                });
            }
            box.addEventListener('click', function (e) { var r = e.target.closest('[data-opt-remove]'); if (r) { r.closest('tr').remove(); } });
        });
        var say = function (text, ok) { var m = document.getElementById('pve-opt-msg'); m.style.display = 'block'; m.textContent = text; m.style.background = ok ? '#ecfdf5' : '#fef2f2'; m.style.color = ok ? '#047857' : '#b91c1c'; };
        document.getElementById('pve-opt-create').addEventListener('click', function () {
            var btn = this, url = btn.getAttribute('data-url');
            if (!url) { say(S.opt_save_first, false); return; }
            var spec = {};
            builder.querySelectorAll('[data-opt]').forEach(function (box) {
                if (!box.querySelector('[data-opt-on]').checked || box.getAttribute('data-linked')) { return; }
                var choices = [];
                box.querySelectorAll('[data-opt-rows] tr').forEach(function (tr) {
                    var v = tr.querySelector('[data-v]'); if (!v || !v.value) { return; }
                    choices.push({ value: v.value, label: tr.querySelector('[data-l]').value, price: tr.querySelector('[data-p]').value || 0 });
                });
                spec[box.getAttribute('data-opt')] = { title: box.querySelector('[data-opt-title]').value, choices: choices };
            });
            if (!Object.keys(spec).length) { say(S.opt_none_chosen, false); return; }
            btn.disabled = true;
            fetch(url, { method: 'POST', headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': @json(csrf_token()) }, body: JSON.stringify({ options: spec }) })
                .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
                .then(function (res) {
                    var j = res.j || {};
                    if (!res.ok || !j.success) { say(j.message || (j.errors ? Object.values(j.errors).flat().join(' ') : S.load_failed), false); return; }
                    say(j.message, true);
                    var rows = document.getElementById('pve-linked-rows'); rows.innerHTML = '';
                    var linkedKeys = {};
                    (j.linked || []).forEach(function (o) {
                        linkedKeys[o.key] = 1;
                        rows.insertAdjacentHTML('beforeend', '<tr><td><strong>' + esc(o.title) + '</strong><br><span style="color:#888;font-size:11.5px;">' + esc(o.group) + '</span></td><td>'
                            + o.choices.map(function (c) { return '<span class="pve-chip" style="display:inline-block;margin:0 4px 4px 0;">' + esc(c.label) + ' <span>' + (c.monthly > 0 ? '+' + Number(c.monthly).toFixed(2) : esc(S.opt_included)) + '</span></span>'; }).join('') + '</td></tr>');
                    });
                    document.getElementById('pve-linked').style.display = '';
                    builder.querySelectorAll('[data-opt]').forEach(function (box) {
                        if (linkedKeys[box.getAttribute('data-opt')]) { box.style.display = 'none'; box.setAttribute('data-linked', '1'); box.querySelector('[data-opt-on]').checked = false; }
                    });
                })
                .catch(function () { say(S.load_failed, false); })
                .finally(function () { btn.disabled = false; });
        });
    }

    window.pnlcsProxmoxCard = { show: function (on) {
        card.style.display = on ? '' : 'none';
        card.querySelectorAll('input,select').forEach(function (f) { f.disabled = !on; });
        if (on) { applyType(); summary(); if (!catalog) { load(); } }
    } };
})();
</script>
@endpush
