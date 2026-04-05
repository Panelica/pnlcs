@extends('admin.layouts.app')
@section('title', 'Server Groups')
@section('content')

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;">
    <h1>Server Groups</h1>
    <button type="button" onclick="document.getElementById('modal-add-sg').style.display='flex'" class="btn btn-primary btn-sm">+ Add Server Group</button>
</div>
<div class="card">
    @if(($serverGroups ?? collect())->isEmpty())
    <div class="card-body" style="text-align:center;padding:40px;color:#999;">No server groups configured.</div>
    @else
    <table class="data-table">
        <thead><tr><th>{{ __('common.table.name') }}</th><th>Fill Type</th><th>Servers</th><th style="text-align:right;">{{ __('common.table.actions') }}</th></tr></thead>
        <tbody>
        @foreach($serverGroups as $group)
        <tr>
            <td style="font-weight:600;">{{ $group->name }}</td>
            <td style="text-transform:capitalize;">{{ $group->fill_type ?? 'Sequential' }}</td>
            <td>{{ $group->servers_count ?? 0 }}</td>
            <td style="text-align:right;">
                <button type="button" class="btn btn-default btn-xs"
                    onclick="openEditSG({{ json_encode(['id'=>$group->id,'name'=>$group->name,'fill_type'=>$group->fill_type]) }})">{{ __('common.actions.edit') }}</button>
                <form method="POST" action="{{ route('admin.config.server-groups.destroy', $group) }}" style="display:inline;" onsubmit="return confirm('Delete group {{ $group->name }}?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-danger btn-xs">{{ __('common.actions.delete') }}</button>
                </form>
            </td>
        </tr>
        @endforeach
        </tbody>
    </table>
    @endif
</div>

<div id="modal-add-sg" style="display:none;position:fixed;inset:0;z-index:1050;align-items:center;justify-content:center;">
    <div style="position:fixed;inset:0;background:rgba(0,0,0,0.5);" onclick="document.getElementById('modal-add-sg').style.display='none'"></div>
    <div style="position:relative;background:#fff;border-radius:4px;width:420px;max-width:95%;box-shadow:0 5px 30px rgba(0,0,0,0.3);">
        <div style="padding:15px 20px;border-bottom:1px solid #e5e5e5;display:flex;align-items:center;justify-content:space-between;">
            <h4 style="margin:0;font-size:16px;">Add Server Group</h4>
            <button type="button" onclick="document.getElementById('modal-add-sg').style.display='none'" style="background:none;border:none;font-size:22px;cursor:pointer;color:#777;">&times;</button>
        </div>
        <form method="POST" action="{{ route('admin.config.server-groups.store') }}">
            @csrf
            <div style="padding:20px;">
                <div class="form-group"><label class="form-label">Group Name</label><input type="text" name="name" required class="form-control"></div>
                <div class="form-group"><label class="form-label">Fill Type</label><select name="fill_type" class="form-control"><option value="sequential">Sequential (fill one at a time)</option><option value="roundrobin">Round Robin (distribute evenly)</option></select></div>
            </div>
            <div style="padding:12px 20px;border-top:1px solid #e5e5e5;display:flex;gap:8px;justify-content:flex-end;">
                <button type="button" onclick="document.getElementById('modal-add-sg').style.display='none'" class="btn btn-default btn-sm">{{ __('common.actions.cancel') }}</button>
                <button type="submit" class="btn btn-primary btn-sm">Add Group</button>
            </div>
        </form>
    </div>
</div>

<div id="modal-edit-sg" style="display:none;position:fixed;inset:0;z-index:1050;align-items:center;justify-content:center;">
    <div style="position:fixed;inset:0;background:rgba(0,0,0,0.5);" onclick="document.getElementById('modal-edit-sg').style.display='none'"></div>
    <div style="position:relative;background:#fff;border-radius:4px;width:420px;max-width:95%;box-shadow:0 5px 30px rgba(0,0,0,0.3);">
        <div style="padding:15px 20px;border-bottom:1px solid #e5e5e5;display:flex;align-items:center;justify-content:space-between;">
            <h4 style="margin:0;font-size:16px;">Edit Server Group</h4>
            <button type="button" onclick="document.getElementById('modal-edit-sg').style.display='none'" style="background:none;border:none;font-size:22px;cursor:pointer;color:#777;">&times;</button>
        </div>
        <form method="POST" id="edit-sg-form" action="">
            @csrf @method('PUT')
            <div style="padding:20px;">
                <div class="form-group"><label class="form-label">Group Name</label><input type="text" name="name" id="esg-name" required class="form-control"></div>
                <div class="form-group"><label class="form-label">Fill Type</label><select name="fill_type" id="esg-fill" class="form-control"><option value="sequential">Sequential</option><option value="roundrobin">Round Robin</option></select></div>
            </div>
            <div style="padding:12px 20px;border-top:1px solid #e5e5e5;display:flex;gap:8px;justify-content:flex-end;">
                <button type="button" onclick="document.getElementById('modal-edit-sg').style.display='none'" class="btn btn-default btn-sm">{{ __('common.actions.cancel') }}</button>
                <button type="submit" class="btn btn-primary btn-sm">{{ __('common.actions.save_changes') }}</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditSG(d) {
    document.getElementById('edit-sg-form').action = '/admin/config/server-groups/' + d.id;
    document.getElementById('esg-name').value = d.name;
    document.getElementById('esg-fill').value = d.fill_type || 'sequential';
    document.getElementById('modal-edit-sg').style.display = 'flex';
}
</script>
@endsection
