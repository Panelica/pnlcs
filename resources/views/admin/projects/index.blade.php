@extends('admin.layouts.app')
@section('title', 'Projects')
@section('content')
<div class="page-header">
    <h1>Projects</h1>
    <a href="{{ route('admin.projects.create') }}" class="btn btn-primary btn-sm">+ New Project</a>
</div>
<div style="display:flex;gap:6px;margin-bottom:10px;flex-wrap:wrap;">
    @foreach([''=>'All','pending'=>'Pending','in_progress'=>'In Progress','completed'=>'Completed','cancelled'=>'Cancelled'] as $val=>$label)
    <a href="{{ route('admin.projects.index', ['status'=>$val,'search'=>request('search')]) }}"
       class="btn btn-sm {{ request('status')==$val ? 'btn-primary' : 'btn-default' }}">{{ $label }}</a>
    @endforeach
</div>

<form method="GET" style="margin-bottom:10px;">
    <input type="hidden" name="status" value="{{ request('status') }}">
    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search by title or client..." class="form-control" style="max-width:300px;display:inline-block;">
</form>

<div class="card">
    <table class="data-table">
        <thead><tr>
            <th>Title</th><th>Client</th><th>Status</th><th>Progress</th><th>Due Date</th><th>Actions</th>
        </tr></thead>
        <tbody>
            @forelse($projects as $project)
            @php
                $total=$project->tasks->count();
                $done=$project->tasks->where('completed',true)->count();
                $pct=$total>0?round($done/$total*100):0;
                $badgeClass = match($project->status) { 'completed'=>'badge-active', 'cancelled'=>'badge-cancelled', 'in_progress'=>'badge-open', default=>'badge-pending' };
            @endphp
            <tr>
                <td>
                    <a href="{{ route('admin.projects.show', $project) }}" style="color:#337ab7;font-weight:600;">{{ $project->title }}</a>
                    @if($project->description)<div style="font-size:12px;color:#999;margin-top:2px;">{{ Str::limit($project->description,60) }}</div>@endif
                </td>
                <td>{{ $project->client->full_name ?? 'N/A' }}</td>
                <td><span class="{{ $badgeClass }}">{{ ucfirst(str_replace('_',' ',$project->status)) }}</span></td>
                <td style="min-width:120px;">
                    <div style="background:#e9e9e9;border-radius:3px;height:10px;margin-bottom:3px;">
                        <div style="height:10px;border-radius:3px;background:#337ab7;width:{{ $pct }}%;"></div>
                    </div>
                    <span style="font-size:11px;color:#777;">{{ $done }}/{{ $total }} tasks</span>
                </td>
                <td style="font-size:12px;color:#777;">{{ $project->due_date ? \Carbon\Carbon::parse($project->due_date)->format('d M Y') : '-' }}</td>
                <td>
                    <div style="display:flex;gap:6px;">
                        <a href="{{ route('admin.projects.show', $project) }}" class="btn btn-default btn-xs">View</a>
                        <a href="{{ route('admin.projects.edit', $project) }}" class="btn btn-default btn-xs">Edit</a>
                        <form method="POST" action="{{ route('admin.projects.destroy', $project) }}" onsubmit="return confirm('Delete this project?')" style="display:inline;">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-danger btn-xs">Del</button>
                        </form>
                    </div>
                </td>
            </tr>
            @empty
            <tr><td colspan="6" style="text-align:center;color:#999;padding:30px;">No projects found.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div style="padding:10px 15px;">{{ $projects->withQueryString()->links() }}</div>
</div>
@endsection
