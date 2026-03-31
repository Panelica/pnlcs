@extends('admin.layouts.app')
@section('title', 'Ticket Departments')
@section('content')

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;">
    <h1>Ticket Departments</h1>
    <button type="button" onclick="document.getElementById('modal-add-dept').style.display='flex'" class="btn btn-primary btn-sm">+ Add Department</button>
</div>
<div class="card">
    @if(($departments ?? collect())->isEmpty())
    <div class="card-body" style="text-align:center;padding:40px;color:#999;">No departments configured.</div>
    @else
    <table class="data-table">
        <thead><tr><th>Department Name</th><th>Email</th><th>Public</th><th>Tickets</th><th style="text-align:right;">Actions</th></tr></thead>
        <tbody>
        @foreach($departments as $dept)
        <tr>
            <td style="font-weight:600;">{{ $dept->name }}</td>
            <td style="font-size:12px;">{{ $dept->email ?? '-' }}</td>
            <td>{{ $dept->hidden ? 'No' : 'Yes' }}</td>
            <td>{{ $dept->tickets_count ?? 0 }}</td>
            <td style="text-align:right;">
                <button type="button" class="btn btn-default btn-xs"
                    onclick="openEditDept({{ json_encode(['id'=>$dept->id,'name'=>$dept->name,'email'=>$dept->email,'description'=>$dept->description,'hidden'=>$dept->hidden]) }})">Edit</button>
                <form method="POST" action="{{ route('admin.config.ticket-departments.destroy', $dept) }}" style="display:inline;" onsubmit="return confirm('Delete department {{ $dept->name }}?')">
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

<div id="modal-add-dept" style="display:none;position:fixed;inset:0;z-index:1050;align-items:center;justify-content:center;">
    <div style="position:fixed;inset:0;background:rgba(0,0,0,0.5);" onclick="document.getElementById('modal-add-dept').style.display='none'"></div>
    <div style="position:relative;background:#fff;border-radius:4px;width:460px;max-width:95%;box-shadow:0 5px 30px rgba(0,0,0,0.3);">
        <div style="padding:15px 20px;border-bottom:1px solid #e5e5e5;display:flex;align-items:center;justify-content:space-between;">
            <h4 style="margin:0;font-size:16px;">Add Department</h4>
            <button type="button" onclick="document.getElementById('modal-add-dept').style.display='none'" style="background:none;border:none;font-size:22px;cursor:pointer;color:#777;">&times;</button>
        </div>
        <form method="POST" action="{{ route('admin.config.ticket-departments.store') }}">
            @csrf
            <div style="padding:20px;">
                <div class="form-group"><label class="form-label">Department Name</label><input type="text" name="name" required class="form-control"></div>
                <div class="form-group"><label class="form-label">Email Address <small style="color:#999;">(for ticket notifications)</small></label><input type="email" name="email" class="form-control"></div>
                <div class="form-group"><label class="form-label">Description</label><textarea name="description" rows="2" class="form-control"></textarea></div>
                <div class="form-group"><label style="font-size:13px;display:flex;align-items:center;gap:6px;cursor:pointer;"><input type="checkbox" name="hidden" value="1"> Hidden from client ticket form</label></div>
            </div>
            <div style="padding:12px 20px;border-top:1px solid #e5e5e5;display:flex;gap:8px;justify-content:flex-end;">
                <button type="button" onclick="document.getElementById('modal-add-dept').style.display='none'" class="btn btn-default btn-sm">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm">Add Department</button>
            </div>
        </form>
    </div>
</div>

<div id="modal-edit-dept" style="display:none;position:fixed;inset:0;z-index:1050;align-items:center;justify-content:center;">
    <div style="position:fixed;inset:0;background:rgba(0,0,0,0.5);" onclick="document.getElementById('modal-edit-dept').style.display='none'"></div>
    <div style="position:relative;background:#fff;border-radius:4px;width:460px;max-width:95%;box-shadow:0 5px 30px rgba(0,0,0,0.3);">
        <div style="padding:15px 20px;border-bottom:1px solid #e5e5e5;display:flex;align-items:center;justify-content:space-between;">
            <h4 style="margin:0;font-size:16px;">Edit Department</h4>
            <button type="button" onclick="document.getElementById('modal-edit-dept').style.display='none'" style="background:none;border:none;font-size:22px;cursor:pointer;color:#777;">&times;</button>
        </div>
        <form method="POST" id="edit-dept-form" action="">
            @csrf @method('PUT')
            <div style="padding:20px;">
                <div class="form-group"><label class="form-label">Department Name</label><input type="text" name="name" id="ed-name" required class="form-control"></div>
                <div class="form-group"><label class="form-label">Email Address</label><input type="email" name="email" id="ed-email" class="form-control"></div>
                <div class="form-group"><label class="form-label">Description</label><textarea name="description" id="ed-desc" rows="2" class="form-control"></textarea></div>
                <div class="form-group"><label style="font-size:13px;display:flex;align-items:center;gap:6px;cursor:pointer;"><input type="checkbox" name="hidden" value="1" id="ed-hidden"> Hidden from clients</label></div>
            </div>
            <div style="padding:12px 20px;border-top:1px solid #e5e5e5;display:flex;gap:8px;justify-content:flex-end;">
                <button type="button" onclick="document.getElementById('modal-edit-dept').style.display='none'" class="btn btn-default btn-sm">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditDept(d) {
    document.getElementById('edit-dept-form').action = '/admin/config/ticket-departments/' + d.id;
    document.getElementById('ed-name').value = d.name;
    document.getElementById('ed-email').value = d.email || '';
    document.getElementById('ed-desc').value = d.description || '';
    document.getElementById('ed-hidden').checked = !!d.hidden;
    document.getElementById('modal-edit-dept').style.display = 'flex';
}
</script>
@endsection
