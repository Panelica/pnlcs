@extends('admin.layouts.app')
@section('title', 'Quotes')
@section('content')

<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Quotes</h1>
</div>

<x-flash-message/>

<div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
    @if($quotes->isEmpty())
        <x-empty-state title="No quotes yet" description="Client quotes will appear here." icon="document"/>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 dark:bg-slate-700/50 border-b border-slate-200 dark:border-slate-700">
                    <tr>
                        <th class="text-left px-6 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Quote #</th>
                        <th class="text-left px-6 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Client</th>
                        <th class="text-left px-6 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Subject</th>
                        <th class="text-left px-6 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Total</th>
                        <th class="text-left px-6 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Status</th>
                        <th class="text-left px-6 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Date</th>
                        <th class="text-left px-6 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Valid Until</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                    @foreach($quotes as $quote)
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition">
                        <td class="px-6 py-4">
                            <span class="font-mono font-semibold text-indigo-600 dark:text-indigo-400">#{{ str_pad($quote->id, 5, '0', STR_PAD_LEFT) }}</span>
                        </td>
                        <td class="px-6 py-4">
                            @if($quote->client)
                                <p class="font-medium text-slate-900 dark:text-white">{{ $quote->client->first_name }} {{ $quote->client->last_name }}</p>
                                <p class="text-xs text-slate-400">{{ $quote->client->email }}</p>
                            @else
                                <span class="text-slate-400">—</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-slate-700 dark:text-slate-300">{{ $quote->subject ?: '—' }}</td>
                        <td class="px-6 py-4 font-medium text-slate-900 dark:text-white">${{ number_format($quote->total, 2) }}</td>
                        <td class="px-6 py-4">
                            <x-status-badge status="{{ strtolower($quote->status ?? 'draft') }}"/>
                        </td>
                        <td class="px-6 py-4 text-slate-500 dark:text-slate-400">{{ $quote->date ? \Carbon\Carbon::parse($quote->date)->format('M d, Y') : '—' }}</td>
                        <td class="px-6 py-4 text-slate-500 dark:text-slate-400">
                            @if($quote->valid_until)
                                @php $validUntil = \Carbon\Carbon::parse($quote->valid_until); @endphp
                                <span class="{{ $validUntil->isPast() ? 'text-red-500 dark:text-red-400' : '' }}">
                                    {{ $validUntil->format('M d, Y') }}
                                </span>
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if($quotes->hasPages())
        <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-700">
            {{ $quotes->links() }}
        </div>
        @endif
    @endif
</div>
@endsection
