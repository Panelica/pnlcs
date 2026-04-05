@extends('admin.layouts.app')
@section('title', $project->title)
@section('content')
<div class="page-header">
    <div>
        <h1>{{ $project->title }}</h1>
        <div style="font-size:13px;color:#777;margin-top:3px;">{{ $project->client?->full_name ?? 'N/A' }} &bull; Created {{ $project->created_at->format('d M Y') }}</div>
    </div>
    <a href="{{ route('admin.projects.index') }}" class="btn btn-default btn-sm">&larr; {{ __('admin.projects.back') }}</a>
</div>

@php
    $total=$project->tasks->count();
    $done=$project->tasks->where('completed',true)->count();
    $pct=$total>0?round($done/$total*100):0;
    $badgeClass = match($project->status) { 'completed'=>'badge-active', 'cancelled'=>'badge-cancelled', 'in_progress'=>'badge-open', default=>'badge-pending' };
@endphp

<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:15px;">
    <div class="stat-card"><div class="stat-value"><span class="{{ $badgeClass }}">{{ ucfirst(str_replace('_',' ',$project->status)) }}</span></div><div class="stat-label">{{ __('admin.projects.status') }}</div></div>
    <div class="stat-card"><div class="stat-value">{{ $done }}<span style="font-size:14px;font-weight:400;color:#999;">/{{ $total }}</span></div><div class="stat-label">{{ __('admin.projects.tasks_done') }}</div></div>
    <div class="stat-card">
        <div style="margin-bottom:5px;">
            <div style="background:#e9e9e9;border-radius:3px;height:14px;">
                <div style="height:14px;border-radius:3px;background:#337ab7;width:{{ $pct }}%;"></div>
            </div>
        </div>
        <div class="stat-label">{{ $pct }}% Progress</div>
    </div>
    <div class="stat-card"><div class="stat-value" style="font-size:16px;">{{ $project->due_date ? \Carbon\Carbon::parse($project->due_date)->format('d M Y') : '—' }}</div><div class="stat-label">Due Date</div></div>
</div>

<div style="display:grid;grid-template-columns:1fr 300px;gap:15px;">
    <div>
        <div class="card" style="margin-bottom:15px;">
            <div class="card-header"><strong>{{ __('admin.projects.tasks') }}</strong></div>
            <div class="card-body" style="padding:0;">
                @forelse($project->tasks->sortBy('sort_order') as $task)
                <div style="display:flex;align-items:flex-start;gap:10px;padding:10px 15px;border-bottom:1px solid #f5f5f5;">
                    <form method="POST" action="{{ route('admin.projects.tasks.update', [$project, $task]) }}" style="margin-top:2px;">
                        @csrf @method('PUT')
                        <input type="hidden" name="completed" value="{{ $task->completed ? '0' : '1' }}">
                        <button type="submit" style="width:18px;height:18px;border-radius:3px;border:2px solid {{ $task->completed ? '#337ab7' : '#ccc' }};background:{{ $task->completed ? '#337ab7' : '#fff' }};cursor:pointer;display:flex;align-items:center;justify-content:center;padding:0;">
                            @if($task->completed)<span style="color:#fff;font-size:11px;font-weight:700;">&#10003;</span>@endif
                        </button>
                    </form>
                    <div style="flex:1;">
                        <p style="margin:0;font-size:13px;{{ $task->completed ? 'text-decoration:line-through;color:#999;' : '' }}">{{ $task->task }}</p>
                        @if($task->notes)<p style="margin:3px 0 0;font-size:12px;color:#999;">{{ $task->notes }}</p>@endif
                        @if($task->due_date)<p style="margin:3px 0 0;font-size:11px;color:#aaa;">Due: {{ \Carbon\Carbon::parse($task->due_date)->format('d M Y') }}</p>@endif
                    </div>
                    <form method="POST" action="{{ route('admin.projects.tasks.destroy', [$project, $task]) }}" onsubmit="return confirm('Delete task?')">
                        @csrf @method('DELETE')
                        <button type="submit" style="background:none;border:none;color:#d9534f;cursor:pointer;font-size:14px;padding:0;">&times;</button>
                    </form>
                </div>
                @empty
                <div style="padding:20px;text-align:center;color:#999;font-size:13px;">{{ __('admin.projects.no_tasks') }}</div>
                @endforelse
            </div>
            <div class="card-body" style="border-top:1px solid #e5e7eb;">
                <form method="POST" action="{{ route('admin.projects.tasks.store', $project) }}">
                    @csrf
                    <label class="form-label">{{ __('admin.projects.add_task_label') }}</label>
                    <div style="display:flex;gap:8px;">
                        <input type="text" name="task" placeholder="{{ __('admin.projects.task_placeholder') }}" required class="form-control">
                        <button type="submit" class="btn btn-primary btn-sm">{{ __('common.actions.add') }}</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><strong>{{ __('admin.projects.timeline_messages') }}</strong></div>
            <div class="card-body" style="max-height:300px;overflow-y:auto;">
                @forelse($project->messages->sortByDesc('created_at') as $msg)
                <div style="display:flex;gap:10px;margin-bottom:12px;">
                    <div style="width:30px;height:30px;background:#e8eef7;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <span style="font-size:11px;font-weight:700;color:#337ab7;">{{ strtoupper(substr($msg->admin??'A',0,1)) }}</span>
                    </div>
                    <div style="flex:1;background:#f9f9f9;border:1px solid #eee;border-radius:4px;padding:8px 10px;">
                        <div style="display:flex;justify-content:space-between;margin-bottom:4px;">
                            <strong style="font-size:12px;">{{ $msg->admin ?? 'Admin' }}</strong>
                            <span style="font-size:11px;color:#999;">{{ $msg->created_at->diffForHumans() }}</span>
                        </div>
                        <p style="font-size:13px;color:#555;margin:0;white-space:pre-wrap;">{{ $msg->message }}</p>
                    </div>
                </div>
                @empty
                <p style="font-size:13px;color:#999;">{{ __('admin.projects.no_messages') }}</p>
                @endforelse
            </div>
            <div class="card-body" style="border-top:1px solid #e5e7eb;">
                <form method="POST" action="{{ route('admin.projects.messages.store', $project) }}">
                    @csrf
                    <div class="form-group"><textarea name="message" rows="2" placeholder="{{ __('admin.projects.post_placeholder') }}" required class="form-control"></textarea></div>
                    <button type="submit" class="btn btn-primary btn-sm">{{ __('admin.projects.post_message') }}</button>
                </form>
            </div>
        </div>
    </div>

    <div>
        <div class="panel" style="margin-bottom:15px;">
            <div class="panel-heading panel-primary">{{ __('admin.projects.project_info') }}</div>
            <div class="panel-body">
                <table style="width:100%;font-size:13px;border-collapse:collapse;">
                    <tr><td style="padding:4px 0;color:#777;">{{ __('admin.projects.client') }}</td><td style="padding:4px 0;font-weight:600;">{{ $project->client?->full_name ?? 'N/A' }}</td></tr>
                    @if($project->start_date)<tr><td style="padding:4px 0;color:#777;">{{ __('admin.projects.start') }}</td><td style="padding:4px 0;">{{ \Carbon\Carbon::parse($project->start_date)->format('d M Y') }}</td></tr>@endif
                    @if($project->due_date)<tr><td style="padding:4px 0;color:#777;">{{ __('admin.projects.due') }}</td><td style="padding:4px 0;">{{ \Carbon\Carbon::parse($project->due_date)->format('d M Y') }}</td></tr>@endif
                    <tr><td style="padding:4px 0;color:#777;">{{ __('admin.projects.messages') }}</td><td style="padding:4px 0;">{{ $project->messages->count() }}</td></tr>
                </table>
                @if($project->description)
                <div style="margin-top:10px;padding-top:10px;border-top:1px solid #eee;">
                    <p style="font-size:11px;font-weight:600;color:#777;text-transform:uppercase;margin-bottom:4px;">{{ __('admin.projects.description') }}</p>
                    <p style="font-size:13px;color:#555;white-space:pre-wrap;">{{ $project->description }}</p>
                </div>
                @endif
            </div>
        </div>
        <div class="panel">
            <div class="panel-heading panel-primary">Actions</div>
            <div class="panel-body" style="display:flex;flex-direction:column;gap:6px;">
                <a href="{{ route('admin.projects.edit', $project) }}" class="btn btn-default btn-sm" style="width:100%;text-align:center;">{{ __('admin.projects.edit_project') }}</a>
                <form method="POST" action="{{ route('admin.projects.destroy', $project) }}" onsubmit="return confirm('Delete this project and all its tasks?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-danger btn-sm" style="width:100%;">{{ __('admin.projects.delete_project') }}</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
