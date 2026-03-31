@extends('admin.layouts.app')
@section('title', 'Activity Log')
@section('content')

<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Activity Log</h1>
    <div class="text-sm text-slate-500 dark:text-slate-400">
        Read-only audit trail
    </div>
</div>

<x-flash-message/>

{{-- Filter bar --}}
<div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-4 mb-4">
    <form method="GET" action="{{ route('admin.config.activity-log') }}" class="flex flex-wrap items-end gap-4">
        <div>
            <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1 uppercase tracking-wider">Filter by Admin</label>
            <select name="user" onchange="this.form.submit()" class="px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500">
                <option value="">All admins</option>
                @foreach($logs->unique('user')->pluck('user')->filter() as $admin)
                    <option value="{{ $admin }}" {{ request('user') === $admin ? 'selected' : '' }}>{{ $admin }}</option>
                @endforeach
            </select>
        </div>
        @if(request('user'))
            <a href="{{ route('admin.config.activity-log') }}" class="px-3 py-2 text-sm text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white">
                <x-heroicon-o-x-mark class="w-4 h-4 inline"/> Clear filter
            </a>
        @endif
    </form>
</div>

<div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
    @if($logs->isEmpty())
        <x-empty-state title="No activity recorded" description="Admin activity will appear here once actions are taken." icon="document"/>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 dark:bg-slate-700/50 border-b border-slate-200 dark:border-slate-700">
                    <tr>
                        <th class="text-left px-6 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Date / Time</th>
                        <th class="text-left px-6 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Admin</th>
                        <th class="text-left px-6 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Description</th>
                        <th class="text-left px-6 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">IP Address</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                    @foreach($logs as $log)
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition">
                        <td class="px-6 py-3 text-slate-500 dark:text-slate-400 whitespace-nowrap">
                            {{ $log->date ? \Carbon\Carbon::parse($log->date)->format('M d, Y H:i:s') : $log->created_at->format('M d, Y H:i:s') }}
                        </td>
                        <td class="px-6 py-3">
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400">
                                <x-heroicon-s-user class="w-3 h-3"/>
                                {{ $log->user ?: 'System' }}
                            </span>
                        </td>
                        <td class="px-6 py-3 text-slate-700 dark:text-slate-300">{{ $log->description }}</td>
                        <td class="px-6 py-3">
                            <code class="text-xs font-mono text-slate-500 dark:text-slate-400">{{ $log->ip_address ?: '—' }}</code>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if($logs->hasPages())
        <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-700">
            {{ $logs->withQueryString()->links() }}
        </div>
        @endif
    @endif
</div>
@endsection
