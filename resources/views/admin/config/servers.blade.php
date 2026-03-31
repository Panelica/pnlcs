@extends('admin.layouts.app')
@section('title', 'Servers')
@section('content')

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;">
    <h1>Servers</h1>
    <div style="display:flex;gap:8px;">
        <button type="button" onclick="document.getElementById('modal-add-server').style.display='flex'" class="btn btn-primary btn-sm">+ Add Server</button>
    </div>
</div>

<div class="card">
    @if(($servers ?? collect())->isEmpty())
    <div class="card-body" style="text-align:center;padding:40px;color:#999;">No servers configured. Click "Add Server" to add one.</div>
    @else
    <table class="data-table">
        <thead><tr><th>Name</th><th>Hostname</th><th>IP Address</th><th>Type</th><th>Port</th><th>Max Accts</th><th>Status</th><th style="text-align:right;">Actions</th></tr></thead>
        <tbody>
        @foreach($servers as $server)
        <tr>
            <td style="font-weight:600;">{{ $server->name }}</td>
            <td style="font-family:monospace;font-size:12px;">{{ $server->hostname }}</td>
            <td style="font-family:monospace;font-size:12px;">{{ $server->ip_address ?? '-' }}</td>
            <td><span class="badge badge-active" style="text-transform:capitalize;">{{ $server->type }}</span></td>
            <td>{{ $server->port ?? '-' }}</td>
            <td>{{ $server->max_accounts ?: 'Unlimited' }}</td>
            <td><span class="badge {{ $server->active ? 'badge-active' : 'badge-suspended' }}">{{ $server->active ? 'Active' : 'Disabled' }}</span></td>
            <td style="text-align:right;">
                <form method="POST" action="{{ route('admin.config.servers.test', $server) }}" style="display:inline;">
                    @csrf
                    <button type="submit" class="btn btn-default btn-xs">Test</button>
                </form>
                <button type="button" class="btn btn-default btn-xs" onclick="editServer({{ $server->id }})">Edit</button>
                <form method="POST" action="{{ route('admin.config.servers.destroy', $server) }}" style="display:inline;" onsubmit="return confirm('Delete {{ $server->name }}?')">
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

{{-- Add Server Modal --}}
<div id="modal-add-server" style="display:none;position:fixed;inset:0;z-index:1050;align-items:center;justify-content:center;">
    <div style="position:fixed;inset:0;background:rgba(0,0,0,0.5);" onclick="this.parentElement.style.display='none'"></div>
    <div style="position:relative;background:#fff;border-radius:4px;width:620px;max-width:95%;box-shadow:0 5px 30px rgba(0,0,0,0.3);max-height:90vh;overflow-y:auto;">
        <div style="padding:15px 20px;border-bottom:1px solid #e5e5e5;display:flex;align-items:center;justify-content:space-between;">
            <h4 style="margin:0;font-size:16px;">Add Server</h4>
            <button type="button" onclick="this.closest('[id]').style.display='none'" style="background:none;border:none;font-size:22px;cursor:pointer;color:#777;">&times;</button>
        </div>
        <form method="POST" action="{{ route('admin.config.servers.store') }}">
            @csrf
            <div style="padding:20px;">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div class="form-group" style="grid-column:span 2;"><label class="form-label">Server Name *</label><input type="text" name="name" required class="form-control" placeholder="e.g. Panelica PROD"></div>
                    <div class="form-group"><label class="form-label">Hostname *</label><input type="text" name="hostname" required class="form-control" placeholder="e.g. server1.panelica.com"></div>
                    <div class="form-group"><label class="form-label">IP Address</label><input type="text" name="ip_address" class="form-control" placeholder="e.g. 138.201.59.57"></div>
                    <div class="form-group"><label class="form-label">Server Type</label>
                        <select name="type" class="form-control">
                            <option value="panelica">Panelica</option>
                            <option value="cpanel">cPanel/WHM</option>
                            <option value="plesk">Plesk</option>
                            <option value="directadmin">DirectAdmin</option>
                            <option value="cyberpanel">CyberPanel</option>
                            <option value="custom">Custom / Other</option>
                        </select>
                    </div>
                    <div class="form-group"><label class="form-label">Port</label><input type="number" name="port" value="8443" class="form-control"></div>
                    <div class="form-group"><label class="form-label">Username</label><input type="text" name="username" class="form-control" placeholder="e.g. root"></div>
                    <div class="form-group"><label class="form-label">Password / API Token</label><input type="password" name="password" class="form-control"></div>
                    <div class="form-group"><label class="form-label">Access Hash / API Key</label><textarea name="access_hash" rows="2" class="form-control" placeholder="Optional — for WHM API hash"></textarea></div>
                    <div class="form-group"><label class="form-label">Max Accounts</label><input type="number" name="max_accounts" value="500" min="0" class="form-control"></div>
                </div>

                <div style="margin-top:15px;padding-top:15px;border-top:1px solid #eee;">
                    <label class="form-label">Nameservers</label>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
                        <input type="text" name="nameserver1" class="form-control" placeholder="ns1.example.com">
                        <input type="text" name="nameserver2" class="form-control" placeholder="ns2.example.com">
                        <input type="text" name="nameserver3" class="form-control" placeholder="ns3 (optional)">
                        <input type="text" name="nameserver4" class="form-control" placeholder="ns4 (optional)">
                    </div>
                </div>

                <div style="margin-top:12px;">
                    <label style="font-size:13px;display:flex;align-items:center;gap:6px;cursor:pointer;">
                        <input type="checkbox" name="active" value="1" checked> Server Active
                    </label>
                </div>
            </div>
            <div style="padding:12px 20px;border-top:1px solid #e5e5e5;display:flex;gap:8px;justify-content:flex-end;">
                <button type="button" onclick="this.closest('[id]').style.display='none'" class="btn btn-default btn-sm">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm">Add Server</button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
function editServer(id) {
    // For now redirect to a simple inline approach — TODO: AJAX edit modal
    alert('Edit server #' + id + ' — coming soon. Use delete + re-add for now.');
}
</script>
@endpush

@endsection
