@extends('admin.layouts.app')
@section('title', __('admin.ticket_statuses.title'))
@section('content')

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;">
    <h1>{{ __('admin.ticket_statuses.title') }}</h1>
    <button type="button" onclick="document.getElementById('modal-add-ts').style.display='flex'" class="btn btn-primary btn-sm">+ {{ __('admin.ticket_statuses.add_status') }}</button>
</div>
<div class="card">
    @if(($statuses ?? collect())->isEmpty())
    <div class="card-body" style="text-align:center;padding:40px;color:#999;">{{ __('admin.ticket_statuses.no_statuses_msg') }}</div>
    @else
    <table class="data-table">
        <thead><tr><th>{{ __('admin.ticket_statuses.status_name_col') }}</th><th>{{ __('admin.ticket_statuses.color_col') }}</th><th>{{ __('admin.ticket_statuses.show_on_client') }}</th><th>{{ __('admin.ticket_statuses.sort_order') }}</th><th style="text-align:right;">{{ __('common.table.actions') }}</th></tr></thead>
        <tbody>
        @foreach($statuses as $status)
        <tr>
            <td style="font-weight:600;">
                <span style="display:inline-block;width:12px;height:12px;border-radius:2px;background:{{ $status->color ?? '#999' }};margin-right:6px;vertical-align:middle;"></span>
                {{ $status->title }}
            </td>
            <td style="font-family:monospace;font-size:12px;">{{ $status->color ?? '-' }}</td>
            <td>{{ $status->show_client ? __('common.yes') : __('common.no') }}</td>
            <td>{{ $status->order ?? 0 }}</td>
            <td style="text-align:right;">
                <button type="button" class="btn btn-default btn-xs"
                    onclick="openEditTS({{ json_encode(['id'=>$status->id,'title'=>$status->title,'color'=>$status->color,'show_client'=>$status->show_client,'order'=>$status->order]) }})">{{ __('common.actions.edit') }}</button>
                <form method="POST" action="{{ route('admin.config.ticket-statuses.destroy', $status) }}" style="display:inline;" onsubmit="return confirm('{{ __('admin.ticket_statuses.confirm_delete') }}')">
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

<div id="modal-add-ts" style="display:none;position:fixed;inset:0;z-index:1050;align-items:center;justify-content:center;">
    <div style="position:fixed;inset:0;background:rgba(0,0,0,0.5);" onclick="document.getElementById('modal-add-ts').style.display='none'"></div>
    <div style="position:relative;background:#fff;border-radius:4px;width:420px;max-width:95%;box-shadow:0 5px 30px rgba(0,0,0,0.3);">
        <div style="padding:15px 20px;border-bottom:1px solid #e5e5e5;display:flex;align-items:center;justify-content:space-between;">
            <h4 style="margin:0;font-size:16px;">{{ __('admin.ticket_statuses.add_status') }}</h4>
            <button type="button" onclick="document.getElementById('modal-add-ts').style.display='none'" style="background:none;border:none;font-size:22px;cursor:pointer;color:#777;">&times;</button>
        </div>
        <form method="POST" action="{{ route('admin.config.ticket-statuses.store') }}">
            @csrf
            <div style="padding:20px;">
                <div class="form-group"><label class="form-label">{{ __('admin.ticket_statuses.status_name') }}</label><input type="text" name="title" required class="form-control"></div>
                <div class="form-group"><label class="form-label">{{ __('admin.ticket_statuses.color_hex') }}</label><input type="color" name="color" value="#337ab7" class="form-control" style="height:38px;padding:2px 6px;"></div>
                <div class="form-group"><label class="form-label">{{ __('admin.ticket_statuses.sort_order') }}</label><input type="number" name="order" value="0" class="form-control"></div>
                <div class="form-group"><label style="font-size:13px;display:flex;align-items:center;gap:6px;cursor:pointer;"><input type="checkbox" name="show_client" value="1" checked> {{ __('admin.ticket_statuses.show_to_clients') }}</label></div>
            </div>
            <div style="padding:12px 20px;border-top:1px solid #e5e5e5;display:flex;gap:8px;justify-content:flex-end;">
                <button type="button" onclick="document.getElementById('modal-add-ts').style.display='none'" class="btn btn-default btn-sm">{{ __('common.actions.cancel') }}</button>
                <button type="submit" class="btn btn-primary btn-sm">{{ __('admin.ticket_statuses.add_status') }}</button>
            </div>
        </form>
    </div>
</div>

<div id="modal-edit-ts" style="display:none;position:fixed;inset:0;z-index:1050;align-items:center;justify-content:center;">
    <div style="position:fixed;inset:0;background:rgba(0,0,0,0.5);" onclick="document.getElementById('modal-edit-ts').style.display='none'"></div>
    <div style="position:relative;background:#fff;border-radius:4px;width:420px;max-width:95%;box-shadow:0 5px 30px rgba(0,0,0,0.3);">
        <div style="padding:15px 20px;border-bottom:1px solid #e5e5e5;display:flex;align-items:center;justify-content:space-between;">
            <h4 style="margin:0;font-size:16px;">{{ __('admin.ticket_statuses.edit_status_title') }}</h4>
            <button type="button" onclick="document.getElementById('modal-edit-ts').style.display='none'" style="background:none;border:none;font-size:22px;cursor:pointer;color:#777;">&times;</button>
        </div>
        <form method="POST" id="edit-ts-form" action="">
            @csrf @method('PUT')
            <div style="padding:20px;">
                <div class="form-group"><label class="form-label">{{ __('admin.ticket_statuses.status_name') }}</label><input type="text" name="title" id="ets-title" required class="form-control"></div>
                <div class="form-group"><label class="form-label">{{ __('admin.ticket_statuses.color') }}</label><input type="color" name="color" id="ets-color" class="form-control" style="height:38px;padding:2px 6px;"></div>
                <div class="form-group"><label class="form-label">{{ __('admin.ticket_statuses.sort_order') }}</label><input type="number" name="order" id="ets-order" class="form-control"></div>
                <div class="form-group"><label style="font-size:13px;display:flex;align-items:center;gap:6px;cursor:pointer;"><input type="checkbox" name="show_client" value="1" id="ets-show"> {{ __('admin.ticket_statuses.show_to_clients') }}</label></div>
            </div>
            <div style="padding:12px 20px;border-top:1px solid #e5e5e5;display:flex;gap:8px;justify-content:flex-end;">
                <button type="button" onclick="document.getElementById('modal-edit-ts').style.display='none'" class="btn btn-default btn-sm">{{ __('common.actions.cancel') }}</button>
                <button type="submit" class="btn btn-primary btn-sm">{{ __('common.actions.save_changes') }}</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditTS(d) {
    document.getElementById('edit-ts-form').action = '/admin/config/ticket-statuses/' + d.id;
    document.getElementById('ets-title').value = d.title;
    document.getElementById('ets-color').value = d.color || '#337ab7';
    document.getElementById('ets-order').value = d.order || 0;
    document.getElementById('ets-show').checked = !!d.show_client;
    document.getElementById('modal-edit-ts').style.display = 'flex';
}
</script>
@endsection
