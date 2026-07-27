@extends('admin.layouts.app')
@section('title', __('admin.server_groups.title'))
@section('content')

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;">
    <h1>{{ __('admin.server_groups.title') }}</h1>
    <button type="button" onclick="document.getElementById('modal-add-sg').style.display='flex'" class="btn btn-primary btn-sm">+ {{ __('admin.server_groups.add_group') }}</button>
</div>

@if($errors->any())
<div class="alert alert-danger" style="margin-bottom:15px;">
    <ul style="margin:0;padding-left:18px;">
        @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
    </ul>
</div>
@endif

<div class="card">
    @if(($serverGroups ?? collect())->isEmpty())
    <div class="card-body" style="text-align:center;padding:40px;color:#999;">{{ __('admin.server_groups.no_groups') }}</div>
    @else
    <table class="data-table">
        <thead><tr><th>{{ __('common.table.name') }}</th><th>{{ __('admin.server_groups.fill_type') }}</th><th>{{ __('admin.server_groups.servers') }}</th><th style="text-align:right;">{{ __('common.table.actions') }}</th></tr></thead>
        <tbody>
        @foreach($serverGroups as $group)
        <tr>
            <td style="font-weight:600;">{{ $group->name }}</td>
            <td>{{ $group->fill_type === 'round_robin' ? __('admin.server_groups.roundrobin_short') : __('admin.server_groups.sequential_short') }}</td>
            <td>
                @forelse($group->servers as $srv)
                <span class="badge badge-active" title="{{ $srv->type }}">{{ $srv->name }}</span>
                @empty
                0
                @endforelse
            </td>
            <td style="text-align:right;">
                <button type="button" class="btn btn-default btn-xs"
                    onclick="openEditSG({{ json_encode(['id'=>$group->id,'name'=>$group->name,'fill_type'=>$group->fill_type,'server_ids'=>$group->servers->pluck('id')]) }})">{{ __('common.actions.edit') }}</button>
                <form method="POST" action="{{ route('admin.config.server-groups.destroy', $group) }}" style="display:inline;" onsubmit="return confirm('{{ __('admin.server_groups.confirm_delete') }}')">
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
            <h4 style="margin:0;font-size:16px;">{{ __('admin.server_groups.add_group') }}</h4>
            <button type="button" onclick="document.getElementById('modal-add-sg').style.display='none'" style="background:none;border:none;font-size:22px;cursor:pointer;color:#777;">&times;</button>
        </div>
        <form method="POST" action="{{ route('admin.config.server-groups.store') }}">
            @csrf
            <div style="padding:20px;">
                <div class="form-group"><label class="form-label">{{ __('admin.server_groups.group_name') }}</label><input type="text" name="name" value="{{ old('name') }}" required class="form-control"></div>
                <div class="form-group"><label class="form-label">{{ __('admin.server_groups.fill_type') }}</label><select name="fill_type" class="form-control"><option value="fill" @selected(old('fill_type')==='fill')>{{ __('admin.server_groups.sequential') }}</option><option value="round_robin" @selected(old('fill_type')==='round_robin')>{{ __('admin.server_groups.roundrobin') }}</option></select></div>
                <div class="form-group">
                    <label class="form-label">{{ __('admin.server_groups.servers') }}</label>
                    <div style="max-height:160px;overflow-y:auto;border:1px solid #ddd;border-radius:3px;padding:8px 10px;">
                        @forelse($allServers ?? [] as $srv)
                        <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer;margin:2px 0;">
                            <input type="checkbox" name="server_ids[]" value="{{ $srv->id }}" @checked(collect(old('server_ids', []))->contains($srv->id))>
                            {{ $srv->name }} <span style="color:#999;">({{ $srv->type ?? 'custom' }})</span>
                        </label>
                        @empty
                        <span style="color:#999;font-size:13px;">{{ __('admin.servers.no_servers') }}</span>
                        @endforelse
                    </div>
                </div>
            </div>
            <div style="padding:12px 20px;border-top:1px solid #e5e5e5;display:flex;gap:8px;justify-content:flex-end;">
                <button type="button" onclick="document.getElementById('modal-add-sg').style.display='none'" class="btn btn-default btn-sm">{{ __('common.actions.cancel') }}</button>
                <button type="submit" class="btn btn-primary btn-sm">{{ __('admin.server_groups.add_group') }}</button>
            </div>
        </form>
    </div>
</div>

<div id="modal-edit-sg" style="display:none;position:fixed;inset:0;z-index:1050;align-items:center;justify-content:center;">
    <div style="position:fixed;inset:0;background:rgba(0,0,0,0.5);" onclick="document.getElementById('modal-edit-sg').style.display='none'"></div>
    <div style="position:relative;background:#fff;border-radius:4px;width:420px;max-width:95%;box-shadow:0 5px 30px rgba(0,0,0,0.3);">
        <div style="padding:15px 20px;border-bottom:1px solid #e5e5e5;display:flex;align-items:center;justify-content:space-between;">
            <h4 style="margin:0;font-size:16px;">{{ __('admin.server_groups.edit_group') }}</h4>
            <button type="button" onclick="document.getElementById('modal-edit-sg').style.display='none'" style="background:none;border:none;font-size:22px;cursor:pointer;color:#777;">&times;</button>
        </div>
        <form method="POST" id="edit-sg-form" action="">
            @csrf @method('PUT')
            <div style="padding:20px;">
                <div class="form-group"><label class="form-label">{{ __('admin.server_groups.group_name') }}</label><input type="text" name="name" id="esg-name" required class="form-control"></div>
                <div class="form-group"><label class="form-label">{{ __('admin.server_groups.fill_type') }}</label><select name="fill_type" id="esg-fill" class="form-control"><option value="fill">{{ __('admin.server_groups.sequential_short') }}</option><option value="round_robin">{{ __('admin.server_groups.roundrobin_short') }}</option></select></div>
                <div class="form-group">
                    <label class="form-label">{{ __('admin.server_groups.servers') }}</label>
                    <div id="esg-servers" style="max-height:160px;overflow-y:auto;border:1px solid #ddd;border-radius:3px;padding:8px 10px;">
                        @forelse($allServers ?? [] as $srv)
                        <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer;margin:2px 0;">
                            <input type="checkbox" name="server_ids[]" value="{{ $srv->id }}">
                            {{ $srv->name }} <span style="color:#999;">({{ $srv->type ?? 'custom' }})</span>
                        </label>
                        @empty
                        <span style="color:#999;font-size:13px;">{{ __('admin.servers.no_servers') }}</span>
                        @endforelse
                    </div>
                </div>
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
    document.getElementById('esg-fill').value = d.fill_type === 'round_robin' ? 'round_robin' : 'fill';
    var ids = (d.server_ids || []).map(Number);
    document.querySelectorAll('#esg-servers input[type=checkbox]').forEach(function (cb) {
        cb.checked = ids.indexOf(Number(cb.value)) !== -1;
    });
    document.getElementById('modal-edit-sg').style.display = 'flex';
}
@if($errors->any())
document.getElementById('modal-add-sg').style.display = 'flex';
@endif
</script>
@endsection
