@extends('admin.layouts.app')
@section('title', 'Gateway Logs')
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
       class="flex-1 text-center px-4 py-2 rounded-lg text-sm font-medium transition-colors bg-indigo-600 text-white">
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

{{-- Gateway Log Filters --}}
<div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-4 mb-4">
    <form method="GET" action="{{ route('admin.logs.gateway') }}" class="flex flex-wrap items-end gap-4">
        <div>
            <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1 uppercase tracking-wider">Gateway</label>
            <select name="gateway" onchange="this.form.submit()"
                    class="px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500">
                <option value="">All gateways</option>
                @foreach($gateways as $gw)
                    <option value="{{ $gw }}" {{ request('gateway') === $gw ? 'selected' : '' }}>{{ ucfirst($gw) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1 uppercase tracking-wider">Date</label>
            <input type="date" name="date" value="{{ request('date') }}"
                   class="px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500">
        </div>
        <div class="flex gap-2">
            <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-medium rounded-lg transition-colors">Filter</button>
            <a href="{{ route('admin.logs.gateway') }}" class="px-4 py-2 bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-300 text-sm font-medium rounded-lg transition-colors">Clear</a>
        </div>
    </form>
</div>

{{-- Gateway Log Table --}}
<div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 dark:bg-slate-700/50">
            <tr>
                <th class="px-4 py-3 text-left font-medium text-slate-600 dark:text-slate-300 w-40">Date</th>
                <th class="px-4 py-3 text-left font-medium text-slate-600 dark:text-slate-300 w-32">Gateway</th>
                <th class="px-4 py-3 text-left font-medium text-slate-600 dark:text-slate-300">Data / Request</th>
                <th class="px-4 py-3 text-left font-medium text-slate-600 dark:text-slate-300 w-24">Result</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
            @forelse($logs as $log)
            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors">
                <td class="px-4 py-3 text-xs text-slate-500 dark:text-slate-400 whitespace-nowrap">
                    {{ $log->date ? \Carbon\Carbon::parse($log->date)->format('Y-m-d H:i:s') : '-' }}
                </td>
                <td class="px-4 py-3 font-medium text-slate-700 dark:text-slate-300">
                    {{ ucfirst($log->gateway ?? '-') }}
                </td>
                <td class="px-4 py-3 text-slate-600 dark:text-slate-400 text-xs font-mono max-w-xs truncate">
                    {{ $log->data ? Str::limit($log->data, 120) : '-' }}
                </td>
                <td class="px-4 py-3">
                    @if($log->result === 'success')
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400">Success</span>
                    @elseif($log->result === 'error' || $log->result === 'failed')
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400">Failed</span>
                    @else
                        <span class="text-xs text-slate-500 dark:text-slate-400">{{ $log->result ?? '-' }}</span>
                    @endif
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="4" class="px-4 py-8 text-center text-slate-500 dark:text-slate-400">No gateway log entries found.</td>
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
