@extends("admin.layouts.app")
@section("title", __("admin.servers"))
@section("content")

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;">
    <h1>{{ __('admin.servers.title') }}</h1>
    <div style="display:flex;gap:8px;">
        <button type="button" onclick="document.getElementById('modal-add-server').style.display='flex'" class="btn btn-primary btn-sm">+ {{ __('admin.servers.add_server') }}</button>
    </div>
</div>

@if($errors->any())
<div class="alert alert-danger" style="margin-bottom:15px;">
    <ul style="margin:0;padding-left:18px;">
        @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
    </ul>
</div>
@endif

<div class="card">
    @if(($servers ?? collect())->isEmpty())
    <div class="card-body" style="text-align:center;padding:40px;color:#999;">{{ __('admin.servers.no_servers') }}</div>
    @else
    <table class="data-table">
        <thead><tr><th>{{ __('common.table.name') }}</th><th>{{ __('admin.servers.hostname') }}</th><th>{{ __('common.table.ip_address') }}</th><th>{{ __('common.table.type') }}</th><th>{{ __('admin.servers.port') }}</th><th>{{ __('admin.servers.max_accounts') }}</th><th>{{ __('common.table.status') }}</th><th style="text-align:right;">{{ __('common.table.actions') }}</th></tr></thead>
        <tbody>
        @foreach($servers as $server)
        <tr>
            <td style="font-weight:600;">{{ $server->name }}</td>
            <td style="font-family:monospace;font-size:12px;">{{ $server->hostname }}</td>
            <td style="font-family:monospace;font-size:12px;">{{ $server->ip_address ?? "-" }}</td>
            <td><span class="badge badge-active" style="text-transform:capitalize;">{{ $server->type }}</span></td>
            <td>{{ $server->port ?? "-" }}</td>
            <td>{{ $server->max_accounts ?: __("admin.servers.unlimited") }}</td>
            <td><span class="badge {{ $server->active ? "badge-active" : "badge-suspended" }}">{{ $server->active ? __("common.status.active") : __("common.status.disabled") }}</span></td>
            <td style="text-align:right;">
                <form method="POST" action="{{ route('admin.config.servers.test', $server) }}" style="display:inline;">
                    @csrf
                    <button type="submit" class="btn btn-default btn-xs">{{ __('common.actions.test') }}</button>
                </form>
                <button type="button" class="btn btn-default btn-xs" onclick="editServer({{ $server->id }},{{ json_encode($server->name) }},{{ json_encode($server->hostname) }},{{ json_encode($server->ip_address) }},{{ json_encode($server->type) }},{{ (int)($server->port ?? 8443) }},{{ json_encode($server->username) }},{{ (int)($server->max_accounts ?? 500) }},{{ json_encode($server->nameserver1 ?? '') }},{{ json_encode($server->nameserver2 ?? '') }},{{ $server->active ? 'true' : 'false' }})">{{ __('common.actions.edit') }}</button>
                <form method="POST" action="{{ route('admin.config.servers.destroy', $server) }}" style="display:inline;" onsubmit="return confirm('{{ __('admin.servers.confirm_delete') }}')">
                    @csrf @method("DELETE")
                    <button type="submit" class="btn btn-danger btn-xs">{{ __('common.actions.delete') }}</button>
                </form>
            </td>
        </tr>
        @endforeach
        </tbody>
    </table>
    @endif
</div>

{{-- Add Server Modal --}}
<div id="modal-add-server" style="display:none;position:fixed;inset:0;z-index:1050;align-items:center;justify-content:center;">
    <div style="position:fixed;inset:0;background:rgba(0,0,0,0.5);" onclick="this.parentElement.style.display='none'"></div>
    <div style="position:relative;background:#fff;border-radius:4px;width:620px;max-width:95%;box-shadow:0 5px 30px rgba(0,0,0,0.3);max-height:90vh;overflow-y:auto;">
        <div style="padding:15px 20px;border-bottom:1px solid #e5e5e5;display:flex;align-items:center;justify-content:space-between;">
            <h4 style="margin:0;font-size:16px;">{{ __('admin.servers.add_server') }}</h4>
            <button type="button" onclick="this.closest('[id]').style.display='none'" style="background:none;border:none;font-size:22px;cursor:pointer;color:#777;">&times;</button>
        </div>
        <form method="POST" action="{{ route('admin.config.servers.store') }}">
            @csrf
            <div style="padding:20px;">
                {{-- What to paste where, per panel type. The generic form showed
                     seven fields to someone holding exactly two strings; this
                     says which two, in the words the other panel used. --}}
                <div data-role="type-hint" style="display:none;margin-bottom:14px;padding:10px 12px;border-radius:6px;background:#eef4ff;border:1px solid #c7d8f8;font-size:13px;line-height:1.55;"></div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div class="form-group" style="grid-column:span 2;"><label class="form-label">{{ __('admin.servers.server_name') }} *</label><input type="text" name="name" required class="form-control" placeholder="e.g. Panelica PROD"></div>
                    <div class="form-group"><label class="form-label">{{ __('admin.servers.hostname') }} *</label><input type="text" name="hostname" required class="form-control" placeholder="e.g. server1.panelica.com"></div>
                    <div class="form-group"><label class="form-label">{{ __('admin.servers.ip_address') }}</label><input type="text" name="ip_address" class="form-control" placeholder="e.g. 138.201.59.57"></div>
                    <div class="form-group"><label class="form-label">{{ __('admin.servers.server_type') }}</label>
                        <select name="type" class="form-control" onchange="serverTypeTuning(this, '')">
                            <option value="panelica">Panelica</option>
                            <option value="cpanel">cPanel/WHM</option>
                            <option value="plesk">Plesk</option>
                            <option value="directadmin">DirectAdmin</option>
                            <option value="cyberpanel">CyberPanel</option>
                            <option value="custom">Custom / Other</option>
                        </select>
                    </div>
                    <div class="form-group"><label class="form-label">{{ __('admin.servers.port') }}</label><input type="number" name="port" value="8443" class="form-control" data-role="port"></div>
                    <div class="form-group" data-role="username-group"><label class="form-label">{{ __('common.form.username') }}</label><input type="text" name="username" class="form-control" placeholder="e.g. root"></div>
                    <div class="form-group"><label class="form-label" data-role="password-label">{{ __('admin.servers.password_api_token') }}</label><input type="password" name="password" class="form-control" data-role="password" placeholder=""></div>
                    <div class="form-group" data-role="hash-group"><label class="form-label" data-role="hash-label">{{ __('admin.servers.access_hash') }}</label><textarea name="access_hash" rows="2" class="form-control" data-role="hash" placeholder=""></textarea></div>
                    <div class="form-group"><label class="form-label">{{ __('admin.servers.max_accounts') }}</label><input type="number" name="max_accounts" value="500" min="0" class="form-control"></div>
                </div>
                <div style="margin-top:15px;padding-top:15px;border-top:1px solid #eee;">
                    <label class="form-label">{{ __('admin.servers.nameservers') }}</label>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
                        <input type="text" name="nameserver1" class="form-control" placeholder="ns1.example.com">
                        <input type="text" name="nameserver2" class="form-control" placeholder="ns2.example.com">
                    </div>
                </div>
                <div style="margin-top:12px;">
                    <label style="font-size:13px;display:flex;align-items:center;gap:6px;cursor:pointer;">
                        <input type="checkbox" name="active" value="1" checked> {{ __('admin.servers.server_active') }}
                    </label>
                </div>
            </div>
            <div style="padding:12px 20px;border-top:1px solid #e5e5e5;display:flex;gap:8px;justify-content:flex-end;">
                <button type="button" onclick="this.closest('[id]').style.display='none'" class="btn btn-default btn-sm">{{ __('common.actions.cancel') }}</button>
                <button type="submit" class="btn btn-primary btn-sm">{{ __('admin.servers.add_server') }}</button>
            </div>
        </form>
    </div>
</div>

{{-- Edit Server Modal --}}
<div id="modal-edit-server" style="display:none;position:fixed;inset:0;z-index:1050;align-items:center;justify-content:center;">
    <div style="position:fixed;inset:0;background:rgba(0,0,0,0.5);" onclick="this.parentElement.style.display='none'"></div>
    <div style="position:relative;background:#fff;border-radius:4px;width:620px;max-width:95%;box-shadow:0 5px 30px rgba(0,0,0,0.3);max-height:90vh;overflow-y:auto;">
        <div style="padding:15px 20px;border-bottom:1px solid #e5e5e5;display:flex;align-items:center;justify-content:space-between;">
            <h4 style="margin:0;font-size:16px;">{{ __('admin.servers.edit_server') }}</h4>
            <button type="button" onclick="this.closest('[id]').style.display='none'" style="background:none;border:none;font-size:22px;cursor:pointer;color:#777;">&times;</button>
        </div>
        <form id="form-edit-server" method="POST" action="">
            @csrf @method("PUT")
            <div style="padding:20px;">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div class="form-group" style="grid-column:span 2;"><label class="form-label">{{ __('admin.servers.server_name') }} *</label><input type="text" id="edit-name" name="name" required class="form-control"></div>
                    <div class="form-group"><label class="form-label">{{ __('admin.servers.hostname') }} *</label><input type="text" id="edit-hostname" name="hostname" required class="form-control"></div>
                    <div class="form-group"><label class="form-label">{{ __('admin.servers.ip_address') }}</label><input type="text" id="edit-ip" name="ip_address" class="form-control"></div>
                    <div class="form-group"><label class="form-label">{{ __('admin.servers.server_type') }}</label>
                        <select id="edit-type" name="type" class="form-control" onchange="serverTypeTuning(this, 'edit')">
                            <option value="panelica">Panelica</option>
                            <option value="cpanel">cPanel/WHM</option>
                            <option value="plesk">Plesk</option>
                            <option value="directadmin">DirectAdmin</option>
                            <option value="cyberpanel">CyberPanel</option>
                            <option value="custom">Custom / Other</option>
                        </select>
                    </div>
                    <div class="form-group"><label class="form-label">{{ __('admin.servers.port') }}</label><input type="number" id="edit-port" name="port" class="form-control"></div>
                    <div class="form-group" data-role="edit-username-group"><label class="form-label">{{ __('common.form.username') }}</label><input type="text" id="edit-username" name="username" class="form-control"></div>
                    <div class="form-group"><label class="form-label" data-role="edit-password-label">{{ __('common.form.new_password') }}<small style="color:#999;">(leave blank to keep)</small></label><input type="password" name="password" class="form-control" placeholder="Leave blank to keep unchanged"></div>
                    <div class="form-group" data-role="edit-hash-group"><label class="form-label" data-role="edit-hash-label">{{ __('admin.servers.access_hash') }}</label><textarea name="access_hash" rows="2" class="form-control" placeholder="Leave blank to keep unchanged"></textarea></div>
                    <div class="form-group"><label class="form-label">{{ __('admin.servers.max_accounts') }}</label><input type="number" id="edit-max-accounts" name="max_accounts" min="0" class="form-control"></div>
                </div>
                <div style="margin-top:15px;padding-top:15px;border-top:1px solid #eee;">
                    <label class="form-label">{{ __('admin.servers.nameservers') }}</label>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
                        <input type="text" id="edit-ns1" name="nameserver1" class="form-control" placeholder="ns1.example.com">
                        <input type="text" id="edit-ns2" name="nameserver2" class="form-control" placeholder="ns2.example.com">
                    </div>
                </div>
                <div style="margin-top:12px;">
                    <label style="font-size:13px;display:flex;align-items:center;gap:6px;cursor:pointer;">
                        <input type="checkbox" id="edit-active" name="active" value="1"> {{ __('admin.servers.server_active') }}
                    </label>
                </div>
            </div>
            <div style="padding:12px 20px;border-top:1px solid #e5e5e5;display:flex;gap:8px;justify-content:flex-end;">
                <button type="button" onclick="this.closest('[id]').style.display='none'" class="btn btn-default btn-sm">{{ __('common.actions.cancel') }}</button>
                <button type="submit" class="btn btn-primary btn-sm">{{ __('common.actions.save_changes') }}</button>
            </div>
        </form>
    </div>
</div>

@push("scripts")
<script>
var serverRouteBase = "{{ url('admin/config/servers') }}";
// Which fields matter for which panel, said in that panel's own words.
//
// The Panelica module reads Password as the API Key (pk_live_...) and Access
// Hash as the API Secret (sk_live_...) - see modules/Servers/Panelica. The
// generic form put seven fields in front of someone holding exactly those two
// strings, and nothing said which went where.
const SERVER_TYPE_TUNING = {
    panelica: {
        port: 8443, username: false,
        passwordLabel: 'API Key', passwordPlaceholder: 'pk_live_...',
        hashLabel: 'API Secret', hashPlaceholder: 'sk_live_...',
        hint: '<strong>Panelica:</strong> in the Panelica panel open <em>Settings → API Keys</em> and create a key. Paste the <strong>API Key</strong> (pk_live_…) and the <strong>API Secret</strong> (sk_live_…) below — the secret is shown only once over there. Username is not used; the port is the panel port (8443).',
    },
    cpanel: {
        port: 2087, username: true,
        passwordLabel: 'API Token', passwordPlaceholder: 'WHM → Development → Manage API Tokens',
        hashLabel: 'Access Hash (legacy)', hashPlaceholder: 'Only for old servers without API tokens',
        hint: '<strong>cPanel/WHM:</strong> username is the WHM account (usually <code>root</code>); create the token under <em>WHM → Development → Manage API Tokens</em> and paste it as the API Token. Port 2087.',
    },
    plesk: {
        port: 8443, username: true,
        passwordLabel: 'Password / API Key', passwordPlaceholder: 'Plesk admin password or API key',
        hashLabel: 'Access Hash', hashPlaceholder: 'Not used by Plesk',
        hint: '<strong>Plesk:</strong> username is the Plesk administrator (usually <code>admin</code>) with their password, on port 8443.',
    },
    directadmin: {
        port: 2222, username: true,
        passwordLabel: 'Password / Login Key', passwordPlaceholder: 'DirectAdmin password or login key',
        hashLabel: 'Access Hash', hashPlaceholder: 'Not used by DirectAdmin',
        hint: '<strong>DirectAdmin:</strong> username is the admin account with its password or a login key, on port 2222.',
    },
    cyberpanel: { port: 8090, username: true, passwordLabel: 'Password', passwordPlaceholder: '', hashLabel: 'Access Hash', hashPlaceholder: '', hint: '' },
    custom: { port: 8443, username: true, passwordLabel: 'Password / API Token', passwordPlaceholder: '', hashLabel: 'Access Hash / API Key', hashPlaceholder: '', hint: '' },
};

function serverTypeTuning(selectEl, prefix) {
    const t = SERVER_TYPE_TUNING[selectEl.value] || SERVER_TYPE_TUNING.custom;
    const modal = selectEl.closest('form');
    const q = (role) => modal.querySelector('[data-role="' + (prefix ? prefix + '-' : '') + role + '"]');

    const userGroup = q('username-group');
    if (userGroup) { userGroup.style.display = t.username ? '' : 'none'; }

    const passLabel = q('password-label');
    // The edit form's password label carries its own "leave blank" note; only
    // the add form gets the type-specific wording.
    if (passLabel && !prefix) { passLabel.textContent = t.passwordLabel; }
    const pass = q('password');
    if (pass) { pass.placeholder = t.passwordPlaceholder; }

    const hashLabel = q('hash-label');
    if (hashLabel) { hashLabel.textContent = t.hashLabel; }
    const hash = q('hash');
    if (hash) { hash.placeholder = t.hashPlaceholder; }

    const port = q('port');
    // Only steer an untouched port: overwriting a number the operator typed
    // because they changed the type would throw their work away.
    if (port && !prefix && !port.dataset.touched) { port.value = t.port; }
    if (port && !port.dataset.listener) {
        port.dataset.listener = '1';
        port.addEventListener('input', () => { port.dataset.touched = '1'; });
    }

    const hint = modal.querySelector('[data-role="type-hint"]');
    if (hint) {
        hint.innerHTML = t.hint;
        hint.style.display = t.hint ? '' : 'none';
    }
}

document.addEventListener('DOMContentLoaded', function () {
    const addType = document.querySelector('form[action$="servers"] select[name="type"], form select[name="type"]');
    if (addType) { serverTypeTuning(addType, ''); }
});

function editServer(id, name, hostname, ip, type, port, username, maxAccounts, ns1, ns2, active) {
    document.getElementById('edit-name').value = name || '';
    document.getElementById('edit-hostname').value = hostname || '';
    document.getElementById('edit-ip').value = ip || '';
    document.getElementById('edit-port').value = port || 8443;
    document.getElementById('edit-username').value = username || '';
    document.getElementById('edit-max-accounts').value = maxAccounts || 500;
    document.getElementById('edit-ns1').value = ns1 || '';
    document.getElementById('edit-ns2').value = ns2 || '';
    document.getElementById('edit-active').checked = active;
    var typeSelect = document.getElementById('edit-type');
    for (var i = 0; i < typeSelect.options.length; i++) {
        if (typeSelect.options[i].value === type) { typeSelect.selectedIndex = i; break; }
    }
    document.getElementById('form-edit-server').action = serverRouteBase + '/' + id;
    // Same per-type field tuning as the add form, applied to the server being
    // edited - a Panelica server's edit screen should not offer a username.
    serverTypeTuning(typeSelect, 'edit');
    document.getElementById('modal-edit-server').style.display = 'flex';
}
@if($errors->any())
document.getElementById('modal-add-server').style.display = 'flex';
@endif
</script>
@endpush

@endsection
