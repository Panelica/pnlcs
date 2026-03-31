@extends('admin.layouts.app')
@section('title', 'Admin Roles')
@section('content')

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;">
    <h1>Admin Roles</h1>
    <button type="button" onclick="document.getElementById('modal-add-role').style.display='flex'" class="btn btn-primary btn-sm">+ Add Role</button>
</div>

@if(session('success'))
<div style="padding:10px 15px;background:#dff0d8;border:1px solid #d6e9c6;border-radius:4px;color:#3c763d;margin-bottom:15px;font-size:13px;">{{ session('success') }}</div>
@endif

<div class="card">
    @if(($roles ?? collect())->isEmpty())
    <div class="card-body" style="text-align:center;padding:40px;color:#999;">No roles defined yet.</div>
    @else
    <table class="data-table">
        <thead><tr><th>Role Name</th><th>Description</th><th>Admins</th><th style="text-align:right;">Actions</th></tr></thead>
        <tbody>
        @foreach($roles as $role)
        <tr>
            <td style="font-weight:600;">{{ $role->name }}</td>
            <td style="color:#777;">{{ $role->description ?? '-' }}</td>
            <td>{{ $role->admins_count ?? 0 }}</td>
            <td style="text-align:right;">
                <button type="button" class="btn btn-default btn-xs"
                    onclick="openEditRole({{ json_encode(['id'=>$role->id,'name'=>$role->name,'description'=>$role->description]) }})">Edit</button>
                <form method="POST" action="{{ route('admin.config.admin-roles.destroy', $role) }}" style="display:inline;" onsubmit="return confirm('Delete role {{ $role->name }}?')">
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

<div id="modal-add-role" style="display:none;position:fixed;inset:0;z-index:1050;align-items:center;justify-content:center;">
    <div style="position:fixed;inset:0;background:rgba(0,0,0,0.5);" onclick="document.getElementById('modal-add-role').style.display='none'"></div>
    <div style="position:relative;background:#fff;border-radius:4px;width:450px;max-width:95%;box-shadow:0 5px 30px rgba(0,0,0,0.3);">
        <div style="padding:15px 20px;border-bottom:1px solid #e5e5e5;display:flex;align-items:center;justify-content:space-between;">
            <h4 style="margin:0;font-size:16px;">Add Role</h4>
            <button type="button" onclick="document.getElementById('modal-add-role').style.display='none'" style="background:none;border:none;font-size:22px;cursor:pointer;color:#777;">&times;</button>
        </div>
        <form method="POST" action="{{ route('admin.config.admin-roles.store') }}">
            @csrf
            <div style="padding:20px;">
                <div class="form-group"><label class="form-label">Role Name</label><input type="text" name="name" required class="form-control"></div>
                <div class="form-group"><label class="form-label">Description</label><input type="text" name="description" class="form-control"></div>
            </div>
            <div style="padding:12px 20px;border-top:1px solid #e5e5e5;display:flex;gap:8px;justify-content:flex-end;">
                <button type="button" onclick="document.getElementById('modal-add-role').style.display='none'" class="btn btn-default btn-sm">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm">Add Role</button>
            </div>
        </form>
    </div>
</div>

<div id="modal-edit-role" style="display:none;position:fixed;inset:0;z-index:1050;align-items:center;justify-content:center;">
    <div style="position:fixed;inset:0;background:rgba(0,0,0,0.5);" onclick="document.getElementById('modal-edit-role').style.display='none'"></div>
    <div style="position:relative;background:#fff;border-radius:4px;width:450px;max-width:95%;box-shadow:0 5px 30px rgba(0,0,0,0.3);">
        <div style="padding:15px 20px;border-bottom:1px solid #e5e5e5;display:flex;align-items:center;justify-content:space-between;">
            <h4 style="margin:0;font-size:16px;">Edit Role</h4>
            <button type="button" onclick="document.getElementById('modal-edit-role').style.display='none'" style="background:none;border:none;font-size:22px;cursor:pointer;color:#777;">&times;</button>
        </div>
        <form method="POST" id="edit-role-form" action="">
            @csrf @method('PUT')
            <div style="padding:20px;">
                <div class="form-group"><label class="form-label">Role Name</label><input type="text" name="name" id="edit-role-name" required class="form-control"></div>
                <div class="form-group"><label class="form-label">Description</label><input type="text" name="description" id="edit-role-desc" class="form-control"></div>
            </div>
            <div style="padding:12px 20px;border-top:1px solid #e5e5e5;display:flex;gap:8px;justify-content:flex-end;">
                <button type="button" onclick="document.getElementById('modal-edit-role').style.display='none'" class="btn btn-default btn-sm">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditRole(data) {
    document.getElementById('edit-role-form').action = '/admin/config/admin-roles/' + data.id;
    document.getElementById('edit-role-name').value = data.name;
    document.getElementById('edit-role-desc').value = data.description || '';
    document.getElementById('modal-edit-role').style.display = 'flex';
}
</script>
@endsection
