@extends('admin.layouts.app')
@section('title', 'Transactions')
@section('content')

<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Transactions</h1>
</div>

<x-flash-message/>

{{-- Filter bar --}}
<div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-4 mb-4">
    <form method="GET" action="{{ route('admin.config.transactions') }}" class="flex flex-wrap items-end gap-4">
        <div>
            <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1 uppercase tracking-wider">Gateway</label>
            <select name="gateway" onchange="this.form.submit()" class="px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500">
                <option value="">All gateways</option>
                @foreach($transactions->unique('gateway')->pluck('gateway')->filter() as $gw)
                    <option value="{{ $gw }}" {{ request('gateway') === $gw ? 'selected' : '' }}>{{ ucfirst($gw) }}</option>
                @endforeach
            </select>
        </div>
        @if(request('gateway'))
            <a href="{{ route('admin.config.transactions') }}" class="px-3 py-2 text-sm text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white">
                <x-heroicon-o-x-mark class="w-4 h-4 inline"/> Clear
            </a>
        @endif
    </form>
</div>

<div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
    @if($transactions->isEmpty())
        <x-empty-state title="No transactions" description="Payment transactions will appear here." icon="currency"/>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 dark:bg-slate-700/50 border-b border-slate-200 dark:border-slate-700">
                    <tr>
                        <th class="text-left px-6 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Trans. ID</th>
                        <th class="text-left px-6 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Client</th>
                        <th class="text-left px-6 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Gateway</th>
                        <th class="text-left px-6 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Date</th>
                        <th class="text-left px-6 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Description</th>
                        <th class="text-right px-6 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Amount In</th>
                        <th class="text-right px-6 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Fees</th>
                        <th class="text-right px-6 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Amount Out</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                    @foreach($transactions as $tx)
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition">
                        <td class="px-6 py-3">
                            <code class="text-xs font-mono text-indigo-600 dark:text-indigo-400">{{ $tx->transaction_id ?: '#' . $tx->id }}</code>
                        </td>
                        <td class="px-6 py-3">
                            @if($tx->client)
                                <p class="font-medium text-slate-900 dark:text-white">{{ $tx->client->first_name }} {{ $tx->client->last_name }}</p>
                                <p class="text-xs text-slate-400">{{ $tx->client->email }}</p>
                            @else
                                <span class="text-slate-400">—</span>
                            @endif
                        </td>
                        <td class="px-6 py-3">
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300 capitalize">
                                {{ $tx->gateway ?: 'Unknown' }}
                            </span>
                        </td>
                        <td class="px-6 py-3 text-slate-500 dark:text-slate-400 whitespace-nowrap">
                            {{ $tx->date ? \Carbon\Carbon::parse($tx->date)->format('M d, Y') : $tx->created_at->format('M d, Y') }}
                        </td>
                        <td class="px-6 py-3 text-slate-500 dark:text-slate-400">{{ Str::limit($tx->description, 40) ?: '—' }}</td>
                        <td class="px-6 py-3 text-right font-medium text-emerald-600 dark:text-emerald-400">
                            {{ $tx->amount_in > 0 ? '+$' . number_format($tx->amount_in, 2) : '—' }}
                        </td>
                        <td class="px-6 py-3 text-right text-slate-500 dark:text-slate-400">
                            {{ $tx->fees > 0 ? '$' . number_format($tx->fees, 2) : '—' }}
                        </td>
                        <td class="px-6 py-3 text-right font-medium text-red-600 dark:text-red-400">
                            {{ $tx->amount_out > 0 ? '-$' . number_format($tx->amount_out, 2) : '—' }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if($transactions->hasPages())
        <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-700">
            {{ $transactions->withQueryString()->links() }}
        </div>
        @endif
    @endif
</div>
@endsection
