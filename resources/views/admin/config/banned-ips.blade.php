@extends('admin.layouts.app')
@section('title', 'Banned IPs')
@section('content')

<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Banned IPs</h1>
    <button onclick="window.dispatchEvent(new CustomEvent('open-modal-add-ip'))"
            class="inline-flex items-center gap-2 px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-lg transition">
        <x-heroicon-s-no-symbol class="w-4 h-4"/>
        Ban IP
    </button>
</div>

<x-flash-message/>

<div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
    @if($ips->isEmpty())
        <x-empty-state title="No banned IPs" description="No IP addresses are currently banned." icon="shield"/>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 dark:bg-slate-700/50 border-b border-slate-200 dark:border-slate-700">
                    <tr>
                        <th class="text-left px-6 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">IP Address</th>
                        <th class="text-left px-6 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Reason</th>
                        <th class="text-left px-6 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Banned At</th>
                        <th class="text-right px-6 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                    @foreach($ips as $ip)
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition">
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-2">
                                <x-heroicon-o-no-symbol class="w-4 h-4 text-red-500"/>
                                <code class="font-mono text-sm font-semibold text-slate-900 dark:text-white">{{ $ip->ip }}</code>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-slate-500 dark:text-slate-400">{{ $ip->reason ?: '—' }}</td>
                        <td class="px-6 py-4 text-slate-500 dark:text-slate-400">{{ $ip->created_at->format('M d, Y H:i') }}</td>
                        <td class="px-6 py-4">
                            <div class="flex justify-end">
                                <x-confirm-delete action="{{ route('admin.config.banned-ips.destroy', $ip) }}" message="Remove this IP ban?"/>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

{{-- Add Modal --}}
<x-modal name="add-ip" title="Ban IP Address">
    <form method="POST" action="{{ route('admin.config.banned-ips.store') }}">
        @csrf
        <div class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">IP Address</label>
                <input type="text" name="ip" required placeholder="e.g. 192.168.1.1 or 10.0.0.0/24" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm font-mono focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Reason (optional)</label>
                <input type="text" name="reason" placeholder="e.g. Brute force attack" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500">
            </div>
        </div>
        <div class="flex justify-end gap-3 mt-6">
            <button type="button" onclick="window.dispatchEvent(new CustomEvent('close-modal-add-ip'))" class="px-4 py-2 border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-300 text-sm font-medium rounded-lg hover:bg-slate-50 dark:hover:bg-slate-700 transition">Cancel</button>
            <button type="submit" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-lg transition">Ban IP</button>
        </div>
    </form>
</x-modal>
@endsection
