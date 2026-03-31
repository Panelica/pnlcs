@extends('admin.layouts.app')
@section('title', 'Network Issues')
@section('content')

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;">
    <h1>Network Issues</h1>
    <button type="button" onclick="document.getElementById('modal-add-ni').style.display='flex'" class="btn btn-primary btn-sm">+ Report Issue</button>
</div>

@if(session('success'))
<div style="padding:10px 15px;background:#dff0d8;border:1px solid #d6e9c6;border-radius:4px;color:#3c763d;margin-bottom:15px;font-size:13px;">{{ session('success') }}</div>
@endif

<div class="card">
    @if(($networkIssues ?? collect())->isEmpty())
    <div class="card-body" style="text-align:center;padding:40px;color:#999;">No network issues reported.</div>
    @else
    <table class="data-table">
        <thead><tr><th>Title</th><th>Type</th><th>Reported</th><th>Resolved</th><th>Status</th><th style="text-align:right;">Actions</th></tr></thead>
        <tbody>
        @foreach($networkIssues as $issue)
        <tr>
            <td style="font-weight:600;">{{ $issue->title }}</td>
            <td style="text-transform:capitalize;">{{ $issue->type ?? 'General' }}</td>
            <td style="font-size:12px;">{{ $issue->created_at->format('d M Y H:i') }}</td>
            <td style="font-size:12px;">{{ $issue->resolved_at?->format('d M Y H:i') ?? '&mdash;' }}</td>
            <td><span class="badge-{{ $issue->resolved_at ? 'active' : 'open' }}">{{ $issue->resolved_at ? 'Resolved' : 'Active' }}</span></td>
            <td style="text-align:right;">
                <form method="POST" action="{{ route('admin.config.network-issues.destroy', $issue) }}" style="display:inline;" onsubmit="return confirm('Delete this issue?')">
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

<div id="modal-add-ni" style="display:none;position:fixed;inset:0;z-index:1050;align-items:center;justify-content:center;">
    <div style="position:fixed;inset:0;background:rgba(0,0,0,0.5);" onclick="document.getElementById('modal-add-ni').style.display='none'"></div>
    <div style="position:relative;background:#fff;border-radius:4px;width:500px;max-width:95%;box-shadow:0 5px 30px rgba(0,0,0,0.3);">
        <div style="padding:15px 20px;border-bottom:1px solid #e5e5e5;display:flex;align-items:center;justify-content:space-between;">
            <h4 style="margin:0;font-size:16px;">Report Network Issue</h4>
            <button type="button" onclick="document.getElementById('modal-add-ni').style.display='none'" style="background:none;border:none;font-size:22px;cursor:pointer;color:#777;">&times;</button>
        </div>
        <form method="POST" action="{{ route('admin.config.network-issues.store') }}">
            @csrf
            <div style="padding:20px;">
                <div class="form-group"><label class="form-label">Title</label><input type="text" name="title" required class="form-control"></div>
                <div class="form-group"><label class="form-label">Type</label><select name="type" class="form-control"><option value="general">General</option><option value="network">Network</option><option value="server">Server</option><option value="datacenter">Datacenter</option></select></div>
                <div class="form-group"><label class="form-label">Description</label><textarea name="description" rows="4" required class="form-control"></textarea></div>
            </div>
            <div style="padding:12px 20px;border-top:1px solid #e5e5e5;display:flex;gap:8px;justify-content:flex-end;">
                <button type="button" onclick="document.getElementById('modal-add-ni').style.display='none'" class="btn btn-default btn-sm">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm">Report Issue</button>
            </div>
        </form>
    </div>
</div>
@endsection
