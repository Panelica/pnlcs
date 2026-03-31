@extends('admin.layouts.app')
@section('title', 'Module Logs')
@section('content')

<div class="page-header"><h1>System Logs</h1></div>

<div style="border-bottom:2px solid #ddd;margin-bottom:15px;display:flex;">
    @foreach(['admin.logs.index'=>'Activity','admin.logs.gateway'=>'Gateway','admin.logs.module'=>'Module','admin.logs.email'=>'Email'] as $route=>$label)
    <a href="{{ route($route) }}" style="padding:8px 16px;font-size:13px;text-decoration:none;border-bottom:3px solid transparent;margin-bottom:-2px;{{ request()->routeIs($route) ? 'border-bottom-color:#337ab7;color:#337ab7;font-weight:600;' : 'color:#555;' }}">{{ $label }}</a>
    @endforeach
</div>

<div class="card" style="margin-bottom:15px;">
    <div class="card-body">
        <form method="GET" action="{{ route('admin.logs.module') }}" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
            <div><label class="form-label">Module</label>
                <select name="module" onchange="this.form.submit()" class="form-control" style="width:160px;">
                    <option value="">All modules</option>
                    @foreach($modules as $mod)<option value="{{ $mod }}" {{ request('module')===$mod?'selected':'' }}>{{ ucfirst($mod) }}</option>@endforeach
                </select>
            </div>
            <div><label class="form-label">Action</label><input type="text" name="action" value="{{ request('action') }}" placeholder="e.g. create, terminate..." class="form-control" style="width:160px;"></div>
            <div style="display:flex;gap:6px;">
                <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                <a href="{{ route('admin.logs.module') }}" class="btn btn-default btn-sm">Clear</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <table class="data-table">
        <thead><tr><th>Date</th><th>Module</th><th>Action</th><th>Request</th><th>Response</th></tr></thead>
        <tbody>
            @forelse($logs as $log)
            <tr>
                <td style="font-size:12px;white-space:nowrap;color:#777;">{{ $log->created_at?->format('Y-m-d H:i:s') ?? '-' }}</td>
                <td style="font-weight:600;text-transform:capitalize;">{{ ucfirst($log->module ?? '-') }}</td>
                <td>{{ $log->action ?? '-' }}</td>
                <td style="font-family:monospace;font-size:12px;color:#777;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $log->request ? Str::limit($log->request, 80) : '-' }}</td>
                <td style="font-family:monospace;font-size:12px;color:#777;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $log->response ? Str::limit($log->response, 80) : '-' }}</td>
            </tr>
            @empty
            <tr><td colspan="5" style="text-align:center;color:#999;padding:30px;">No module log entries found.</td></tr>
            @endforelse
        </tbody>
    </table>
    @if($logs->hasPages())<div style="padding:10px 15px;">{{ $logs->withQueryString()->links() }}</div>@endif
</div>
@endsection
