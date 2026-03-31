@extends("admin.layouts.app")
@section("title", "Projects")
@section("content")
<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold">Projects</h1>
    <a href="{{ route('admin.projects.create') }}" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition-colors">+ New Project</a>
</div>
<div class="flex gap-2 mb-4 flex-wrap">
    @foreach([''=>'All','pending'=>'Pending','in_progress'=>'In Progress','completed'=>'Completed','cancelled'=>'Cancelled'] as $val=>$label)
    <a href="{{ route('admin.projects.index', ['status'=>$val,'search'=>request('search')]) }}"
       class="px-3 py-1.5 rounded-lg text-sm font-medium {{ request('status')==$val ? 'bg-indigo-600 text-white' : 'bg-slate-100 dark:bg-slate-700 hover:bg-slate-200' }} transition-colors">{{ $label }}</a>
    @endforeach
</div>
<form method="GET" class="mb-4">
    <input type="hidden" name="status" value="{{ request('status') }}">
    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search by title or client..." class="w-full max-w-sm px-3 py-2 text-sm border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 focus:ring-2 focus:ring-indigo-500 focus:outline-none">
</form>
@if(session('success'))
    <div class="mb-4 px-4 py-3 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg text-sm">{{ session('success') }}</div>
@endif
<div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 dark:bg-slate-700/50">
            <tr>
                <th class="px-4 py-3 text-left font-medium text-slate-600">Title</th>
                <th class="px-4 py-3 text-left font-medium text-slate-600">Client</th>
                <th class="px-4 py-3 text-left font-medium text-slate-600">Status</th>
                <th class="px-4 py-3 text-left font-medium text-slate-600">Progress</th>
                <th class="px-4 py-3 text-left font-medium text-slate-600">Due Date</th>
                <th class="px-4 py-3 text-left font-medium text-slate-600">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
            @forelse($projects as $project)
            @php
                $colors=['pending'=>'slate','in_progress'=>'blue','completed'=>'emerald','cancelled'=>'red'];
                $c=$colors[$project->status]??'slate';
                $total=$project->tasks->count();
                $done=$project->tasks->where('completed',true)->count();
                $pct=$total>0?round($done/$total*100):0;
            @endphp
            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30">
                <td class="px-4 py-3">
                    <a href="{{ route('admin.projects.show', $project) }}" class="text-indigo-600 hover:text-indigo-500 font-medium">{{ $project->title }}</a>
                    @if($project->description)<p class="text-xs text-slate-400 mt-0.5 truncate max-w-xs">{{ $project->description }}</p>@endif
                </td>
                <td class="px-4 py-3">{{ $project->client->full_name??'N/A' }}</td>
                <td class="px-4 py-3"><span class="px-2 py-0.5 text-xs font-medium rounded-full bg-{{ $c }}-100 text-{{ $c }}-700">{{ ucfirst(str_replace('_',' ',$project->status)) }}</span></td>
                <td class="px-4 py-3">
                    <div class="flex items-center gap-2">
                        <div class="flex-1 bg-slate-200 dark:bg-slate-700 rounded-full h-1.5">
                            <div class="bg-indigo-600 h-1.5 rounded-full" style="width:{{ $pct }}%"></div>
                        </div>
                        <span class="text-xs text-slate-500 w-16">{{ $done }}/{{ $total }} tasks</span>
                    </div>
                </td>
                <td class="px-4 py-3 text-slate-500">{{ $project->due_date ? \Carbon\Carbon::parse($project->due_date)->format('d M Y') : '-' }}</td>
                <td class="px-4 py-3">
                    <div class="flex items-center gap-2">
                        <a href="{{ route('admin.projects.show', $project) }}" class="text-indigo-600 hover:underline text-xs">View</a>
                        <a href="{{ route('admin.projects.edit', $project) }}" class="text-slate-500 hover:underline text-xs">Edit</a>
                        <form method="POST" action="{{ route('admin.projects.destroy', $project) }}" onsubmit="return confirm('Delete this project?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-red-500 hover:underline text-xs">Delete</button>
                        </form>
                    </div>
                </td>
            </tr>
            @empty
            <tr><td colspan="6" class="px-4 py-12 text-center text-slate-500">No projects found.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="px-4 py-3 border-t border-slate-200 dark:border-slate-700">{{ $projects->withQueryString()->links() }}</div>
</div>
@endsection
