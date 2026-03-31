@extends('admin.layouts.app')
@section('title', 'Module Logs')
@section('content')

<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-slate-900 dark:text-white">System Logs</h1>
</div>

<x-flash-message/>

{{-- Tab Navigation --}}
<div class="flex gap-1 mb-6 bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-1">
    <a href="{{ route('admin.logs.index') }}"
       class="flex-1 text-center px-4 py-2 rounded-lg text-sm font-medium transition-colors text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700">
        Activity
    </a>
    <a href="{{ route('admin.logs.gateway') }}"
       class="flex-1 text-center px-4 py-2 rounded-lg text-sm font-medium transition-colors text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700">
        Gateway
    </a>
    <a href="{{ route('admin.logs.module') }}"
       class="flex-1 text-center px-4 py-2 rounded-lg text-sm font-medium transition-colors bg-indigo-600 text-white">
        Module
    </a>
    <a href="{{ route('admin.logs.email') }}"
       class="flex-1 text-center px-4 py-2 rounded-lg text-sm font-medium transition-colors text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700">
        Email
    </a>
</div>

{{-- Module Log Filters --}}
<div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-4 mb-4">
    <form method="GET" action="{{ route('admin.logs.module') }}" class="flex flex-wrap items-end gap-4">
        <div>
            <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1 uppercase tracking-wider">Module</label>
            <select name="module" onchange="this.form.submit()"
                    class="px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500">
                <option value="">All modules</option>
                @foreach($modules as $mod)
                    <option value="{{ $mod }}" {{ request('module') === $mod ? 'selected' : '' }}>{{ ucfirst($mod) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1 uppercase tracking-wider">Action</label>
            <input type="text" name="action" value="{{ request('action') }}" placeholder="e.g. create, terminate..."
                   class="px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500 w-40">
        </div>
        <div class="flex gap-2">
            <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-medium rounded-lg transition-colors">Filter</button>
            <a href="{{ route('admin.logs.module') }}" class="px-4 py-2 bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-300 text-sm font-medium rounded-lg transition-colors">Clear</a>
        </div>
    </form>
</div>

{{-- Module Log Table --}}
<div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 dark:bg-slate-700/50">
            <tr>
                <th class="px-4 py-3 text-left font-medium text-slate-600 dark:text-slate-300 w-40">Date</th>
                <th class="px-4 py-3 text-left font-medium text-slate-600 dark:text-slate-300 w-28">Module</th>
                <th class="px-4 py-3 text-left font-medium text-slate-600 dark:text-slate-300 w-24">Action</th>
                <th class="px-4 py-3 text-left font-medium text-slate-600 dark:text-slate-300">Request</th>
                <th class="px-4 py-3 text-left font-medium text-slate-600 dark:text-slate-300">Response</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
            @forelse($logs as $log)
            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors">
                <td class="px-4 py-3 text-xs text-slate-500 dark:text-slate-400 whitespace-nowrap">
                    {{ $log->created_at?->format('Y-m-d H:i:s') ?? '-' }}
                </td>
                <td class="px-4 py-3 font-medium text-slate-700 dark:text-slate-300">
                    {{ ucfirst($log->module ?? '-') }}
                </td>
                <td class="px-4 py-3 text-slate-600 dark:text-slate-400">
                    {{ $log->action ?? '-' }}
                </td>
                <td class="px-4 py-3 text-xs text-slate-500 dark:text-slate-400 font-mono max-w-xs truncate">
                    {{ $log->request ? Str::limit($log->request, 80) : '-' }}
                </td>
                <td class="px-4 py-3 text-xs text-slate-500 dark:text-slate-400 font-mono max-w-xs truncate">
                    {{ $log->response ? Str::limit($log->response, 80) : '-' }}
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="5" class="px-4 py-8 text-center text-slate-500 dark:text-slate-400">No module log entries found.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
    @if($logs->hasPages())
    <div class="px-4 py-3 border-t border-slate-200 dark:border-slate-700">
        {{ $logs->withQueryString()->links() }}
    </div>
    @endif
</div>
@endsection
