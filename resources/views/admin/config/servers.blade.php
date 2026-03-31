@extends('admin.layouts.app')
@section('title', 'Servers')
@section('content')

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;">
    <h1>Servers</h1>
    <button type="button" onclick="document.getElementById('modal-add-server').style.display='flex'" class="btn btn-primary btn-sm">+ Add Server</button>
</div>
<div class="card">
    @if(($servers ?? collect())->isEmpty())
    <div class="card-body" style="text-align:center;padding:40px;color:#999;">No servers configured.</div>
    @else
    <table class="data-table">
        <thead><tr><th>Name</th><th>Hostname / IP</th><th>Type</th><th>Group</th><th>Max Accounts</th><th>Active</th><th>Status</th><th style="text-align:right;">Actions</th></tr></thead>
        <tbody>
        @foreach($servers as $server)
        <tr>
            <td style="font-weight:600;">{{ $server->name }}</td>
            <td style="font-family:monospace;font-size:12px;">{{ $server->hostname }}</td>
            <td style="text-transform:capitalize;">{{ $server->type }}</td>
            <td>{{ $server->servergroup->name ?? 'None' }}</td>
            <td>{{ $server->max_accounts ?: 'Unlimited' }}</td>
            <td>{{ $server->active_accounts ?? 0 }}</td>
            <td><span class="badge-{{ $server->active ? 'active' : 'suspended' }}">{{ $server->active ? 'Active' : 'Inactive' }}</span></td>
            <td style="text-align:right;">
                <button type="button" class="btn btn-default btn-xs"
                    onclick="openEditServer({{ json_encode(['id'=>$server->id,'name'=>$server->name,'hostname'=>$server->hostname,'type'=>$server->type,'username'=>$server->username,'port'=>$server->port,'max_accounts'=>$server->max_accounts,'secure'=>$server->secure,'active'=>$server->active]) }})">Edit</button>
                <form method="POST" action="{{ route('admin.config.servers.destroy', $server) }}" style="display:inline;" onsubmit="return confirm('Delete server {{ $server->name }}?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-danger btn-xs">Delete</button>
                </form>
            </td>
        </tr>
        @endforeach
        </tbody>
    </table>
    @endif
</div>

<div id="modal-add-server" style="display:none;position:fixed;inset:0;z-index:1050;align-items:center;justify-content:center;">
    <div style="position:fixed;inset:0;background:rgba(0,0,0,0.5);" onclick="document.getElementById('modal-add-server').style.display='none'"></div>
    <div style="position:relative;background:#fff;border-radius:4px;width:520px;max-width:95%;box-shadow:0 5px 30px rgba(0,0,0,0.3);">
        <div style="padding:15px 20px;border-bottom:1px solid #e5e5e5;display:flex;align-items:center;justify-content:space-between;">
            <h4 style="margin:0;font-size:16px;">Add Server</h4>
            <button type="button" onclick="document.getElementById('modal-add-server').style.display='none'" style="background:none;border:none;font-size:22px;cursor:pointer;color:#777;">&times;</button>
        </div>
        <form method="POST" action="{{ route('admin.config.servers.store') }}">
            @csrf
            <div style="padding:20px;">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div class="form-group" style="grid-column:span 2;"><label class="form-label">Server Name</label><input type="text" name="name" required class="form-control"></div>
                    <div class="form-group"><label class="form-label">Hostname / IP</label><input type="text" name="hostname" required class="form-control"></div>
                    <div class="form-group"><label class="form-label">Port</label><input type="number" name="port" value="2083" class="form-control"></div>
                    <div class="form-group"><label class="form-label">Server Type</label><select name="type" class="form-control"><option value="panelica">Panelica</option><option value="cpanel">cPanel</option><option value="plesk">Plesk</option><option value="directadmin">DirectAdmin</option><option value="custom">Custom</option></select></div>
                    <div class="form-group"><label class="form-label">Max Accounts</label><input type="number" name="max_accounts" value="0" min="0" class="form-control"></div>
                    <div class="form-group"><label class="form-label">Username</label><input type="text" name="username" class="form-control"></div>
                    <div class="form-group"><label class="form-label">Password / API Token</label><input type="password" name="password" class="form-control"></div>
                    <div class="form-group" style="grid-column:span 2;"><label style="font-size:13px;display:flex;align-items:center;gap:6px;cursor:pointer;"><input type="checkbox" name="secure" value="1" checked> Use SSL (HTTPS)</label></div>
                </div>
            </div>
            <div style="padding:12px 20px;border-top:1px solid #e5e5e5;display:flex;gap:8px;justify-content:flex-end;">
                <button type="button" onclick="document.getElementById('modal-add-server').style.display='none'" class="btn btn-default btn-sm">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm">Add Server</button>
            </div>
        </form>
    </div>
</div>

<div id="modal-edit-server" style="display:none;position:fixed;inset:0;z-index:1050;align-items:center;justify-content:center;">
    <div style="position:fixed;inset:0;background:rgba(0,0,0,0.5);" onclick="document.getElementById('modal-edit-server').style.display='none'"></div>
    <div style="position:relative;background:#fff;border-radius:4px;width:520px;max-width:95%;box-shadow:0 5px 30px rgba(0,0,0,0.3);">
        <div style="padding:15px 20px;border-bottom:1px solid #e5e5e5;display:flex;align-items:center;justify-content:space-between;">
            <h4 style="margin:0;font-size:16px;">Edit Server</h4>
            <button type="button" onclick="document.getElementById('modal-edit-server').style.display='none'" style="background:none;border:none;font-size:22px;cursor:pointer;color:#777;">&times;</button>
        </div>
        <form method="POST" id="edit-server-form" action="">
            @csrf @method('PUT')
            <div style="padding:20px;">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div class="form-group" style="grid-column:span 2;"><label class="form-label">Server Name</label><input type="text" name="name" id="es-name" required class="form-control"></div>
                    <div class="form-group"><label class="form-label">Hostname / IP</label><input type="text" name="hostname" id="es-hostname" required class="form-control"></div>
                    <div class="form-group"><label class="form-label">Port</label><input type="number" name="port" id="es-port" class="form-control"></div>
                    <div class="form-group"><label class="form-label">Max Accounts</label><input type="number" name="max_accounts" id="es-max" class="form-control"></div>
                    <div class="form-group"><label class="form-label">Username</label><input type="text" name="username" id="es-username" class="form-control"></div>
                    <div class="form-group" style="grid-column:span 2;"><label class="form-label">New Password / Token <small style="color:#999;">(leave blank to keep)</small></label><input type="password" name="password" class="form-control"></div>
                    <div class="form-group" style="grid-column:span 2;display:flex;gap:20px;"><label style="font-size:13px;display:flex;align-items:center;gap:6px;"><input type="checkbox" name="secure" value="1" id="es-secure"> Use SSL</label><label style="font-size:13px;display:flex;align-items:center;gap:6px;"><input type="checkbox" name="active" value="1" id="es-active"> Active</label></div>
                </div>
            </div>
            <div style="padding:12px 20px;border-top:1px solid #e5e5e5;display:flex;gap:8px;justify-content:flex-end;">
                <button type="button" onclick="document.getElementById('modal-edit-server').style.display='none'" class="btn btn-default btn-sm">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditServer(d) {
    document.getElementById('edit-server-form').action = '/admin/config/servers/' + d.id;
    document.getElementById('es-name').value = d.name;
    document.getElementById('es-hostname').value = d.hostname;
    document.getElementById('es-port').value = d.port || '';
    document.getElementById('es-max').value = d.max_accounts || 0;
    document.getElementById('es-username').value = d.username || '';
    document.getElementById('es-secure').checked = !!d.secure;
    document.getElementById('es-active').checked = !!d.active;
    document.getElementById('modal-edit-server').style.display = 'flex';
}
</script>
@endsection
