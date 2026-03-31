@extends('client.layouts.app')
@section('title', 'Affiliate Program')
@section('content')
<h1 class="text-2xl font-bold mb-6">Affiliate Program</h1>

@if(! $affiliate)
    {{-- Not yet activated --}}
    <div class="max-w-xl mx-auto text-center">
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-12">
            <div class="w-16 h-16 bg-indigo-100 dark:bg-indigo-900/40 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            </div>
            <h2 class="text-xl font-bold mb-2">Join Our Affiliate Program</h2>
            <p class="text-slate-500 text-sm mb-6">Earn commissions by referring new clients. Share your unique referral link and get paid for every successful signup.</p>
            <form method="POST" action="{{ route('client.affiliates.activate') }}">
                @csrf
                <button type="submit" class="px-6 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors">
                    Join Affiliate Program
                </button>
            </form>
        </div>
    </div>
@else
    {{-- Stats --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-5 text-center">
            <p class="text-2xl font-bold text-indigo-600">{{ number_format($affiliate->visitors) }}</p>
            <p class="text-xs text-slate-500 mt-1">Total Visits</p>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-5 text-center">
            <p class="text-2xl font-bold text-emerald-600">{{ $referralHistory->count() }}</p>
            <p class="text-xs text-slate-500 mt-1">Referrals</p>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-5 text-center">
            <p class="text-2xl font-bold text-amber-600">${{ number_format($affiliate->balance, 2) }}</p>
            <p class="text-xs text-slate-500 mt-1">Available Balance</p>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-5 text-center">
            <p class="text-2xl font-bold text-slate-600 dark:text-slate-300">${{ number_format($affiliate->withdrawn, 2) }}</p>
            <p class="text-xs text-slate-500 mt-1">Total Withdrawn</p>
        </div>
    </div>

    {{-- Referral Link --}}
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6 mb-6" x-data="{copied: false}">
        <h3 class="font-semibold mb-3">Your Referral Link</h3>
        <div class="flex gap-2">
            <input type="text" readonly
                value="{{ url('/client/register?ref=' . $client->id) }}"
                class="flex-1 rounded-lg border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-700 px-3 py-2 text-sm font-mono">
            <button type="button"
                x-on:click="navigator.clipboard.writeText('{{ url('/client/register?ref=' . $client->id) }}'); copied = true; setTimeout(() => copied = false, 2000)"
                class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied">Copied!</span>
            </button>
        </div>
        <p class="text-xs text-slate-500 mt-2">Commission rate: {{ $affiliate->pay_type === 'percentage' ? $affiliate->pay_amount . '%' : '$' . number_format($affiliate->pay_amount, 2) }} per referral</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Referral History --}}
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700">
            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                <h3 class="font-semibold">Commission History</h3>
            </div>
            @if($referralHistory->isEmpty())
                <div class="p-8 text-center text-sm text-slate-400">No commissions earned yet.</div>
            @else
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 dark:bg-slate-700/50">
                        <tr>
                            <th class="px-4 py-2 text-left text-slate-500">Date</th>
                            <th class="px-4 py-2 text-left text-slate-500">Description</th>
                            <th class="px-4 py-2 text-right text-slate-500">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                        @foreach($referralHistory as $tx)
                        <tr>
                            <td class="px-4 py-2 text-slate-500">{{ $tx->date?->format('d M Y') }}</td>
                            <td class="px-4 py-2">{{ $tx->description }}</td>
                            <td class="px-4 py-2 text-right text-emerald-600 font-medium">${{ number_format($tx->amount_in, 2) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        {{-- Withdrawal --}}
        @if($affiliate->balance > 0)
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
            <h3 class="font-semibold mb-4">Request Withdrawal</h3>
            <p class="text-sm text-slate-500 mb-4">Available balance: <strong>${{ number_format($affiliate->balance, 2) }}</strong></p>
            <form method="POST" action="{{ route('client.affiliates.withdraw') }}">
                @csrf
                <div class="mb-4">
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Amount</label>
                    <div class="relative">
                        <span class="absolute left-3 top-2 text-slate-500 text-sm">$</span>
                        <input type="number" name="amount" value="{{ old('amount', number_format($affiliate->balance, 2)) }}"
                            min="1" max="{{ $affiliate->balance }}" step="0.01" required
                            class="w-full rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 pl-7 pr-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                    </div>
                    @error('amount') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <button type="submit" class="w-full py-2.5 bg-emerald-600 text-white text-sm font-medium rounded-lg hover:bg-emerald-700 transition-colors">
                    Request Withdrawal
                </button>
            </form>
        </div>
        @endif
    </div>
@endif
@endsection
