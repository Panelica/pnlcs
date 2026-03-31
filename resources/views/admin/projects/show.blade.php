@extends("admin.layouts.app")
@section("title", $project->title)
@section("content")
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold">{{ $project->title }}</h1>
        <p class="text-slate-500 text-sm">{{ $project->client->full_name??'N/A' }} &bull; Created {{ $project->created_at->format('d M Y') }}</p>
    </div>
    <a href="{{ route('admin.projects.index') }}" class="text-slate-500 hover:text-slate-700 text-sm">← Back to Projects</a>
</div>
@if(session('success'))
    <div class="mb-4 px-4 py-3 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg text-sm">{{ session('success') }}</div>
@endif
@php
    $colors=['pending'=>'slate','in_progress'=>'blue','completed'=>'emerald','cancelled'=>'red'];
    $c=$colors[$project->status]??'slate';
    $total=$project->tasks->count();
    $done=$project->tasks->where('completed',true)->count();
    $pct=$total>0?round($done/$total*100):0;
@endphp
{{-- Stats bar --}}
<div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-4">
        <p class="text-xs text-slate-500 uppercase tracking-wider">Status</p>
        <span class="mt-1 inline-flex px-2 py-0.5 text-sm font-medium rounded-full bg-{{ $c }}-100 text-{{ $c }}-700">{{ ucfirst(str_replace('_',' ',$project->status)) }}</span>
    </div>
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-4">
        <p class="text-xs text-slate-500 uppercase tracking-wider">Tasks</p>
        <p class="mt-1 text-2xl font-bold">{{ $done }}<span class="text-base font-normal text-slate-400">/{{ $total }}</span></p>
    </div>
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-4">
        <p class="text-xs text-slate-500 uppercase tracking-wider">Progress</p>
        <div class="mt-2">
            <div class="flex items-center gap-2">
                <div class="flex-1 bg-slate-200 dark:bg-slate-700 rounded-full h-2">
                    <div class="bg-indigo-600 h-2 rounded-full" style="width:{{ $pct }}%"></div>
                </div>
                <span class="text-sm font-medium">{{ $pct }}%</span>
            </div>
        </div>
    </div>
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-4">
        <p class="text-xs text-slate-500 uppercase tracking-wider">Due Date</p>
        <p class="mt-1 font-medium">{{ $project->due_date ? \Carbon\Carbon::parse($project->due_date)->format('d M Y') : '—' }}</p>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-6">
        {{-- Tasks --}}
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
            <h2 class="font-semibold mb-4">Tasks</h2>
            @forelse($project->tasks->sortBy('sort_order') as $task)
            <div class="flex items-start gap-3 py-3 border-b border-slate-100 dark:border-slate-700 last:border-0 group">
                <form method="POST" action="{{ route('admin.projects.tasks.update', [$project, $task]) }}" class="mt-0.5">
                    @csrf @method('PUT')
                    <input type="hidden" name="completed" value="{{ $task->completed ? '0' : '1' }}">
                    <button type="submit" class="w-5 h-5 rounded border-2 {{ $task->completed ? 'bg-indigo-600 border-indigo-600' : 'border-slate-300' }} flex items-center justify-center flex-shrink-0 hover:border-indigo-500 transition-colors">
                        @if($task->completed)<svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>@endif
                    </button>
                </form>
                <div class="flex-1 min-w-0">
                    <p class="text-sm {{ $task->completed ? 'line-through text-slate-400' : '' }}">{{ $task->task }}</p>
                    @if($task->notes)<p class="text-xs text-slate-400 mt-0.5">{{ $task->notes }}</p>@endif
                    @if($task->due_date)<p class="text-xs text-slate-400 mt-0.5">Due: {{ \Carbon\Carbon::parse($task->due_date)->format('d M Y') }}</p>@endif
                </div>
                <form method="POST" action="{{ route('admin.projects.tasks.destroy', [$project, $task]) }}" onsubmit="return confirm('Delete task?')" class="opacity-0 group-hover:opacity-100 transition-opacity">
                    @csrf @method('DELETE')
                    <button type="submit" class="text-red-400 hover:text-red-600 text-xs">✕</button>
                </form>
            </div>
            @empty
            <p class="text-sm text-slate-400 py-3">No tasks yet.</p>
            @endforelse
            <form method="POST" action="{{ route('admin.projects.tasks.store', $project) }}" class="mt-4 pt-4 border-t border-slate-200 dark:border-slate-700">
                @csrf
                <p class="text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Add Task</p>
                <div class="flex gap-2">
                    <input type="text" name="task" placeholder="Task description..." required class="flex-1 px-3 py-2 text-sm border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition-colors">Add</button>
                </div>
            </form>
        </div>

        {{-- Messages timeline --}}
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
            <h2 class="font-semibold mb-4">Timeline / Messages</h2>
            <div class="space-y-4 max-h-80 overflow-y-auto mb-4">
                @forelse($project->messages->sortByDesc('created_at') as $msg)
                <div class="flex gap-3">
                    <div class="w-8 h-8 bg-indigo-100 rounded-full flex items-center justify-center flex-shrink-0">
                        <span class="text-xs font-semibold text-indigo-700">{{ strtoupper(substr($msg->admin??'A',0,1)) }}</span>
                    </div>
                    <div class="flex-1 bg-slate-50 dark:bg-slate-700/50 rounded-lg p-3">
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-xs font-medium text-slate-700 dark:text-slate-300">{{ $msg->admin??'Admin' }}</span>
                            <span class="text-xs text-slate-400">{{ $msg->created_at->diffForHumans() }}</span>
                        </div>
                        <p class="text-sm text-slate-600 dark:text-slate-300 whitespace-pre-wrap">{{ $msg->message }}</p>
                    </div>
                </div>
                @empty
                <p class="text-sm text-slate-400">No messages yet.</p>
                @endforelse
            </div>
            <form method="POST" action="{{ route('admin.projects.messages.store', $project) }}" class="pt-4 border-t border-slate-200 dark:border-slate-700">
                @csrf
                <textarea name="message" rows="2" placeholder="Post a message or update..." required class="w-full px-3 py-2 text-sm border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 focus:ring-2 focus:ring-indigo-500 focus:outline-none mb-2"></textarea>
                <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition-colors">Post Message</button>
            </form>
        </div>
    </div>

    <div class="space-y-4">
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
            <h2 class="font-semibold mb-3">Project Info</h2>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between"><dt class="text-slate-500">Client</dt><dd class="font-medium">{{ $project->client->full_name??'N/A' }}</dd></div>
                @if($project->start_date)<div class="flex justify-between"><dt class="text-slate-500">Start</dt><dd>{{ \Carbon\Carbon::parse($project->start_date)->format('d M Y') }}</dd></div>@endif
                @if($project->due_date)<div class="flex justify-between"><dt class="text-slate-500">Due</dt><dd>{{ \Carbon\Carbon::parse($project->due_date)->format('d M Y') }}</dd></div>@endif
                <div class="flex justify-between"><dt class="text-slate-500">Messages</dt><dd>{{ $project->messages->count() }}</dd></div>
            </dl>
            @if($project->description)
            <div class="mt-4 pt-4 border-t border-slate-200 dark:border-slate-700">
                <p class="text-xs font-medium text-slate-500 uppercase tracking-wider mb-1">Description</p>
                <p class="text-sm text-slate-600 dark:text-slate-300 whitespace-pre-wrap">{{ $project->description }}</p>
            </div>
            @endif
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6 space-y-2">
            <h2 class="font-semibold mb-3">Actions</h2>
            <a href="{{ route('admin.projects.edit', $project) }}" class="flex w-full items-center justify-center px-4 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 text-sm font-medium rounded-lg transition-colors">Edit Project</a>
            <form method="POST" action="{{ route('admin.projects.destroy', $project) }}" onsubmit="return confirm('Delete this project and all its tasks?')">
                @csrf @method('DELETE')
                <button type="submit" class="w-full px-4 py-2 bg-red-50 hover:bg-red-100 text-red-700 text-sm font-medium rounded-lg transition-colors">Delete Project</button>
            </form>
        </div>
    </div>
</div>
@endsection
