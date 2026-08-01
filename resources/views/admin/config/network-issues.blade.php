@extends('admin.layouts.app')
@section('title', __('admin.network_issues.title'))
@section('content')

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;">
    <h1>{{ __('admin.network_issues.title') }}</h1>
    <button type="button" onclick="document.getElementById('modal-add-ni').style.display='flex'" class="btn btn-primary btn-sm">+ {{ __('admin.network_issues.report_issue') }}</button>
</div>
<div class="card">
    @if(($networkIssues ?? collect())->isEmpty())
    <div class="card-body" style="text-align:center;padding:40px;color:#999;">{{ __('admin.network_issues.no_issues') }}</div>
    @else
    <table class="data-table">
        <thead><tr><th>{{ __('common.form.title') }}</th><th>{{ __('common.table.type') }}</th><th>{{ __('admin.network_issues.reported') }}</th><th>{{ __('admin.network_issues.resolved') }}</th><th>{{ __('common.table.status') }}</th><th style="text-align:right;">{{ __('common.table.actions') }}</th></tr></thead>
        <tbody>
        @foreach($networkIssues as $issue)
        <tr>
            <td style="font-weight:600;">{{ $issue->title }}</td>
            <td style="text-transform:capitalize;">{{ $issue->type ?? 'General' }}</td>
            <td style="font-size:12px;">{{ $issue->created_at->timezone(display_tz())->format(datetime_fmt()) }}</td>
            <td style="font-size:12px;">{{ $issue->end_date?->timezone(display_tz())->format(datetime_fmt()) ?? '&mdash;' }}</td>
            <td><span class="badge-{{ $issue->status === 'resolved' ? 'active' : 'open' }}">{{ $issue->status === 'resolved' ? __('admin.network_issues.resolved') : __('admin.network_issues.active') }}</span></td>
            <td style="text-align:right;">
                <button type="button" class="btn btn-default btn-xs" onclick="document.getElementById('modal-edit-ni-{{ $issue->id }}').style.display='flex'">{{ __('common.actions.edit') }}</button>
                <form method="POST" action="{{ route('admin.config.network-issues.destroy', $issue) }}" style="display:inline;" onsubmit="return confirm('{{ __('admin.network_issues.confirm_delete') }}')">
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

@foreach($networkIssues as $issue)
<div id="modal-edit-ni-{{ $issue->id }}" style="display:none;position:fixed;inset:0;z-index:1050;align-items:center;justify-content:center;">
    <div style="position:fixed;inset:0;background:rgba(0,0,0,0.5);" onclick="document.getElementById('modal-edit-ni-{{ $issue->id }}').style.display='none'"></div>
    <div style="position:relative;background:#fff;border-radius:4px;width:520px;max-width:95%;box-shadow:0 5px 30px rgba(0,0,0,0.3);">
        <div style="padding:15px 20px;border-bottom:1px solid #e5e5e5;display:flex;align-items:center;justify-content:space-between;">
            <h4 style="margin:0;font-size:16px;">{{ __('admin.network_issues.edit_issue') }}</h4>
            <button type="button" onclick="document.getElementById('modal-edit-ni-{{ $issue->id }}').style.display='none'" style="background:none;border:none;font-size:22px;cursor:pointer;color:#777;">&times;</button>
        </div>
        <form method="POST" action="{{ route('admin.config.network-issues.update', $issue) }}">
            @csrf @method('PUT')
            <div style="padding:20px;">
                <div class="form-group"><label class="form-label">{{ __('common.form.title') }}</label><input type="text" name="title" value="{{ $issue->title }}" required class="form-control"></div>
                <div class="form-group">
                    <label class="form-label">{{ __('admin.network_issues.type_label') }}</label>
                    <select name="type" class="form-control">
                        @foreach(['general', 'network', 'server', 'datacenter'] as $type)
                        <option value="{{ $type }}" @selected($issue->type === $type)>{{ __('admin.network_issues.type_'.$type) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">{{ __('common.table.status') }}</label>
                    <select name="status" class="form-control">
                        @foreach(['open', 'scheduled', 'investigating', 'resolved'] as $state)
                        <option value="{{ $state }}" @selected($issue->status === $state)>{{ __('admin.network_issues.status_'.$state) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group"><label class="form-label">{{ __('common.form.description') }}</label><textarea name="description" rows="4" required class="form-control">{{ $issue->description }}</textarea></div>
                <div class="form-group"><label class="form-label">{{ __('admin.network_issues.end_date') }}</label><input type="date" name="end_date" value="{{ $issue->end_date?->format('Y-m-d') }}" class="form-control"></div>
            </div>
            <div style="padding:12px 20px;border-top:1px solid #e5e5e5;display:flex;gap:8px;justify-content:flex-end;">
                <button type="button" onclick="document.getElementById('modal-edit-ni-{{ $issue->id }}').style.display='none'" class="btn btn-default btn-sm">{{ __('common.actions.cancel') }}</button>
                <button type="submit" class="btn btn-primary btn-sm">{{ __('common.actions.save_changes') }}</button>
            </div>
        </form>
    </div>
</div>
@endforeach
<div id="modal-add-ni" style="display:none;position:fixed;inset:0;z-index:1050;align-items:center;justify-content:center;">
    <div style="position:fixed;inset:0;background:rgba(0,0,0,0.5);" onclick="document.getElementById('modal-add-ni').style.display='none'"></div>
    <div style="position:relative;background:#fff;border-radius:4px;width:500px;max-width:95%;box-shadow:0 5px 30px rgba(0,0,0,0.3);">
        <div style="padding:15px 20px;border-bottom:1px solid #e5e5e5;display:flex;align-items:center;justify-content:space-between;">
            <h4 style="margin:0;font-size:16px;">{{ __('admin.network_issues.report_issue') }}</h4>
            <button type="button" onclick="document.getElementById('modal-add-ni').style.display='none'" style="background:none;border:none;font-size:22px;cursor:pointer;color:#777;">&times;</button>
        </div>
        <form method="POST" action="{{ route('admin.config.network-issues.store') }}">
            @csrf
            <div style="padding:20px;">
                <div class="form-group"><label class="form-label">{{ __('common.form.title') }}</label><input type="text" name="title" required class="form-control"></div>
                <div class="form-group"><label class="form-label">{{ __('admin.network_issues.type_label') }}</label><select name="type" class="form-control"><option value="general">{{ __('admin.network_issues.type_general') }}</option><option value="network">{{ __('admin.network_issues.type_network') }}</option><option value="server">{{ __('admin.network_issues.type_server') }}</option><option value="datacenter">{{ __('admin.network_issues.type_datacenter') }}</option></select></div>
                <div class="form-group"><label class="form-label">{{ __('common.table.status') }}</label><select name="status" class="form-control"><option value="open">{{ __('admin.network_issues.status_open') }}</option><option value="scheduled">{{ __('admin.network_issues.status_scheduled') }}</option><option value="investigating">{{ __('admin.network_issues.status_investigating') }}</option><option value="resolved">{{ __('admin.network_issues.status_resolved') }}</option></select></div>
                <div class="form-group"><label class="form-label">{{ __('common.form.description') }}</label><textarea name="description" rows="4" required class="form-control"></textarea></div>
            </div>
            <div style="padding:12px 20px;border-top:1px solid #e5e5e5;display:flex;gap:8px;justify-content:flex-end;">
                <button type="button" onclick="document.getElementById('modal-add-ni').style.display='none'" class="btn btn-default btn-sm">{{ __('common.actions.cancel') }}</button>
                <button type="submit" class="btn btn-primary btn-sm">{{ __('admin.network_issues.report_issue') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection
