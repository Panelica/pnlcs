@extends('admin.layouts.app')
@section('title', 'Affiliates')
@section('content')

<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Affiliates</h1>
    <div class="text-sm text-slate-500 dark:text-slate-400">Read-only summary</div>
</div>

<x-flash-message/>

{{-- Stats --}}
@if($affiliates->isNotEmpty())
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-5">
        <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Total Affiliates</p>
        <p class="text-2xl font-bold text-slate-900 dark:text-white mt-1">{{ $affiliates->count() }}</p>
    </div>
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-5">
        <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Total Visits</p>
        <p class="text-2xl font-bold text-slate-900 dark:text-white mt-1">{{ number_format($affiliates->sum('visitors')) }}</p>
    </div>
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-5">
        <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Total Balance Pending</p>
        <p class="text-2xl font-bold text-slate-900 dark:text-white mt-1">${{ number_format($affiliates->sum('balance'), 2) }}</p>
    </div>
</div>
@endif

<div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
    @if($affiliates->isEmpty())
        <x-empty-state title="No affiliates yet" description="Clients who sign up through referral links will appear here." icon="users"/>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 dark:bg-slate-700/50 border-b border-slate-200 dark:border-slate-700">
                    <tr>
                        <th class="text-left px-6 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Client</th>
                        <th class="text-left px-6 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Visits</th>
                        <th class="text-left px-6 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Referrals</th>
                        <th class="text-left px-6 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Balance</th>
                        <th class="text-left px-6 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Withdrawn</th>
                        <th class="text-left px-6 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Pay Type</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                    @foreach($affiliates as $affiliate)
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition">
                        <td class="px-6 py-4">
                            @if($affiliate->client)
                                <div>
                                    <p class="font-medium text-slate-900 dark:text-white">
                                        {{ $affiliate->client->first_name }} {{ $affiliate->client->last_name }}
                                    </p>
                                    <p class="text-xs text-slate-400">{{ $affiliate->client->email }}</p>
                                </div>
                            @else
                                <span class="text-slate-400">Client #{{ $affiliate->client_id }}</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-slate-500 dark:text-slate-400">{{ number_format($affiliate->visitors) }}</td>
                        <td class="px-6 py-4 text-slate-500 dark:text-slate-400">—</td>
                        <td class="px-6 py-4">
                            <span class="font-medium {{ $affiliate->balance > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-500 dark:text-slate-400' }}">
                                ${{ number_format($affiliate->balance, 2) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-slate-500 dark:text-slate-400">${{ number_format($affiliate->withdrawn, 2) }}</td>
                        <td class="px-6 py-4">
                            <span class="capitalize text-slate-500 dark:text-slate-400">{{ $affiliate->pay_type ?: 'percentage' }}</span>
                            @if($affiliate->pay_amount)
                                <span class="ml-1 text-xs text-slate-400">{{ $affiliate->pay_amount }}</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
