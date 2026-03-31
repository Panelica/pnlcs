@extends('admin.layouts.app')
@section('title', 'System Logs')
@section('content')

<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-slate-900 dark:text-white">System Logs</h1>
</div>

<x-flash-message/>

{{-- Tab Navigation --}}
<div class="flex gap-1 mb-6 bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-1">
    <a href="{{ route('admin.logs.index') }}"
       class="flex-1 text-center px-4 py-2 rounded-lg text-sm font-medium transition-colors {{ request()->routeIs('admin.logs.index') && !request('type') || request('type', 'activity') === 'activity' ? 'bg-indigo-600 text-white' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700' }}">
        Activity
    </a>
    <a href="{{ route('admin.logs.gateway') }}"
       class="flex-1 text-center px-4 py-2 rounded-lg text-sm font-medium transition-colors text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700">
        Gateway
    </a>
    <a href="{{ route('admin.logs.module') }}"
       class="flex-1 text-center px-4 py-2 rounded-lg text-sm font-medium transition-colors text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700">
        Module
    </a>
    <a href="{{ route('admin.logs.email') }}"
       class="flex-1 text-center px-4 py-2 rounded-lg text-sm font-medium transition-colors text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700">
        Email
    </a>
</div>

{{-- Activity Log Filters --}}
<div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-4 mb-4">
    <form method="GET" action="{{ route('admin.logs.index') }}" class="flex flex-wrap items-end gap-4">
        <div>
            <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1 uppercase tracking-wider">Admin/User</label>
            <input type="text" name="user" value="{{ request('user') }}" placeholder="Filter by user..."
                   class="px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500 w-44">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1 uppercase tracking-wider">Date</label>
            <input type="date" name="date" value="{{ request('date') }}"
                   class="px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500">
        </div>
        <div class="flex-1 min-w-[200px]">
            <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1 uppercase tracking-wider">Search</label>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search description..."
                   class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500">
        </div>
        <div class="flex gap-2">
            <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-medium rounded-lg transition-colors">Filter</button>
            <a href="{{ route('admin.logs.index') }}" class="px-4 py-2 bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-300 text-sm font-medium rounded-lg transition-colors">Clear</a>
        </div>
    </form>
</div>

{{-- Activity Log Table --}}
<div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 dark:bg-slate-700/50">
            <tr>
                <th class="px-4 py-3 text-left font-medium text-slate-600 dark:text-slate-300 w-40">Date</th>
                <th class="px-4 py-3 text-left font-medium text-slate-600 dark:text-slate-300 w-32">User</th>
                <th class="px-4 py-3 text-left font-medium text-slate-600 dark:text-slate-300">Description</th>
                <th class="px-4 py-3 text-left font-medium text-slate-600 dark:text-slate-300 w-32">IP Address</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
            @forelse($logs as $log)
            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors">
                <td class="px-4 py-3 text-xs text-slate-500 dark:text-slate-400 whitespace-nowrap">
                    {{ $log->date?->format('Y-m-d H:i:s') ?? '-' }}
                </td>
                <td class="px-4 py-3 font-medium text-slate-700 dark:text-slate-300">
                    {{ $log->user ?: 'System' }}
                </td>
                <td class="px-4 py-3 text-slate-700 dark:text-slate-300">
                    {{ $log->description }}
                </td>
                <td class="px-4 py-3 text-xs text-slate-500 dark:text-slate-400 font-mono">
                    {{ $log->ip_address ?? '-' }}
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="4" class="px-4 py-8 text-center text-slate-500 dark:text-slate-400">No activity log entries found.</td>
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
