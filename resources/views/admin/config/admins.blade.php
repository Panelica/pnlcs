@extends('admin.layouts.app')
@section('title', 'Staff Management')
@section('content')

@if(session('success'))
<div style="padding:10px 15px;background:#dff0d8;border:1px solid #d6e9c6;border-radius:4px;color:#3c763d;margin-bottom:15px;font-size:13px;">{{ session('success') }}</div>
@endif
@if(session('error'))
<div style="padding:10px 15px;background:#f2dede;border:1px solid #ebccd1;border-radius:4px;color:#a94442;margin-bottom:15px;font-size:13px;">{{ session('error') }}</div>
@endif

<div class="page-header">
    <div>
        <h1>Staff Management</h1>
        <div style="font-size:13px;color:#777;">Manage administrator accounts and access</div>
    </div>
    <button type="button" onclick="openModal('add-admin')" class="btn btn-primary btn-sm">+ Add Admin</button>
</div>

@if($admins->isEmpty())
<div class="card"><div class="card-body" style="text-align:center;color:#999;padding:40px;">No admins found.</div></div>
@else
<div class="card">
    <table class="data-table">
        <thead><tr><th>Name</th><th>Username / Email</th><th>Role</th><th>Last Login</th><th>Status</th><th style="text-align:right;">Actions</th></tr></thead>
        <tbody>
            @foreach($admins as $admin)
            <tr>
                <td>
                    <div style="display:flex;align-items:center;gap:8px;">
                        <div style="width:32px;height:32px;border-radius:50%;background:#e8eef7;display:flex;align-items:center;justify-content:center;color:#337ab7;font-weight:700;font-size:11px;flex-shrink:0;">
                            {{ strtoupper(substr($admin->first_name,0,1)) }}{{ strtoupper(substr($admin->last_name,0,1)) }}
                        </div>
                        <span style="font-weight:600;">{{ $admin->full_name }}</span>
                    </div>
                </td>
                <td>
                    <div style="font-size:13px;">{{ $admin->username }}</div>
                    <div style="font-size:12px;color:#777;">{{ $admin->email }}</div>
                </td>
                <td>
                    {{ $admin->role?->name ?? '—' }}
                    @if($admin->role?->is_full_admin)<span style="margin-left:4px;background:#fcf8e3;color:#8a6d3b;font-size:11px;padding:1px 6px;border-radius:3px;">Full</span>@endif
                </td>
                <td style="font-size:12px;color:#777;">{{ $admin->last_login ? $admin->last_login->diffForHumans() : 'Never' }}</td>
                <td>
                    @if($admin->is_disabled)<span class="badge-suspended">Disabled</span>
                    @else<span class="badge-active">Active</span>@endif
                </td>
                <td style="text-align:right;">
                    <div style="display:flex;gap:6px;justify-content:flex-end;">
                        <button type="button" onclick="openModal('edit-admin-{{ $admin->id }}')" class="btn btn-default btn-xs">Edit</button>
                        @if($admin->id !== auth('admin')->id())
                        <form method="POST" action="{{ route('admin.config.admins.destroy', $admin) }}" onsubmit="return confirm('Delete admin {{ $admin->full_name }}?')" style="display:inline;">
                            @csrf @method('DELETE')<button type="submit" class="btn btn-danger btn-xs">Delete</button>
                        </form>
                        @endif
                    </div>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif

@foreach($admins as $admin)
<x-modal :name="'edit-admin-' . $admin->id" title="Edit Admin" maxWidth="lg">
    <form method="POST" action="{{ route('admin.config.admins.update', $admin) }}">
        @csrf @method('PUT')
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:0 15px;">
            <div class="form-group"><label class="form-label">First Name</label><input type="text" name="first_name" value="{{ $admin->first_name }}" required class="form-control"></div>
            <div class="form-group"><label class="form-label">Last Name</label><input type="text" name="last_name" value="{{ $admin->last_name }}" required class="form-control"></div>
            <div class="form-group"><label class="form-label">Username</label><input type="text" name="username" value="{{ $admin->username }}" required class="form-control"></div>
            <div class="form-group"><label class="form-label">Email</label><input type="email" name="email" value="{{ $admin->email }}" required class="form-control"></div>
            <div class="form-group"><label class="form-label">Role</label>
                <select name="role_id" required class="form-control">
                    @foreach($roles as $role)<option value="{{ $role->id }}" @selected($admin->role_id===$role->id)>{{ $role->name }}</option>@endforeach
                </select>
            </div>
            <div class="form-group"><label class="form-label">New Password <span style="color:#999;font-weight:400;">(leave blank to keep)</span></label><input type="password" name="password" minlength="6" class="form-control"></div>
        </div>
        <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:10px;">
            <button type="button" onclick="closeModal('edit-admin-{{ $admin->id }}')" class="btn btn-default btn-sm">Cancel</button>
            <button type="submit" class="btn btn-primary btn-sm">Save Changes</button>
        </div>
    </form>
</x-modal>
@endforeach

<x-modal name="add-admin" title="Add New Admin" maxWidth="lg">
    <form method="POST" action="{{ route('admin.config.admins.store') }}">
        @csrf
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:0 15px;">
            <div class="form-group"><label class="form-label">First Name</label><input type="text" name="first_name" required class="form-control"></div>
            <div class="form-group"><label class="form-label">Last Name</label><input type="text" name="last_name" required class="form-control"></div>
            <div class="form-group"><label class="form-label">Username</label><input type="text" name="username" required class="form-control"></div>
            <div class="form-group"><label class="form-label">Email</label><input type="email" name="email" required class="form-control"></div>
            <div class="form-group"><label class="form-label">Password</label><input type="password" name="password" minlength="6" required class="form-control"></div>
            <div class="form-group"><label class="form-label">Role</label>
                <select name="role_id" required class="form-control">
                    <option value="">-- Select Role --</option>
                    @foreach($roles as $role)<option value="{{ $role->id }}">{{ $role->name }}</option>@endforeach
                </select>
            </div>
        </div>
        <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:10px;">
            <button type="button" onclick="closeModal('add-admin')" class="btn btn-default btn-sm">Cancel</button>
            <button type="submit" class="btn btn-primary btn-sm">Create Admin</button>
        </div>
    </form>
</x-modal>
@endsection
