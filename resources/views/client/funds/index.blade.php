@extends('client.layouts.app')
@section('title', 'Add Funds')
@section('content')
<div class="max-w-lg mx-auto">
    <h1 class="text-2xl font-bold mb-6">Add Funds to Account</h1>
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-8">
        <form method="POST" action="{{ route('client.funds.store') }}">
            @csrf
            <div class="mb-6">
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-3">Select Amount</label>
                <div class="grid grid-cols-3 gap-3 mb-3">
                    @foreach([10, 20, 50, 100, 200, 500] as $preset)
                    <button type="button" onclick="document.getElementById('amount').value = '{{ $preset }}'"
                        class="py-2 text-sm font-medium rounded-lg border border-slate-300 dark:border-slate-600 hover:border-indigo-400 hover:text-indigo-600 transition-colors">
                        ${{ $preset }}
                    </button>
                    @endforeach
                </div>
                <div class="relative">
                    <span class="absolute left-3 top-2 text-slate-500 text-sm">$</span>
                    <input type="number" id="amount" name="amount" value="{{ old('amount') }}" min="5" max="10000" step="0.01" required
                        class="w-full rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 pl-7 pr-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                        placeholder="Custom amount">
                </div>
                @error('amount') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="mb-6">
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Payment Method <span class="text-red-500">*</span></label>
                @if($gateways->isEmpty())
                    <select name="payment_method" required
                        class="w-full rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                        <option value="">Select payment method...</option>
                        <option value="banktransfer">Bank Transfer</option>
                        <option value="paypal">PayPal</option>
                        <option value="stripe">Credit Card (Stripe)</option>
                    </select>
                @else
                    <select name="payment_method" required
                        class="w-full rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                        <option value="">Select payment method...</option>
                        @foreach($gateways as $gateway)
                            <option value="{{ $gateway }}" {{ old('payment_method') === $gateway ? 'selected' : '' }}>
                                {{ ucwords(str_replace('_', ' ', $gateway)) }}
                            </option>
                        @endforeach
                    </select>
                @endif
                @error('payment_method') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <button type="submit" class="w-full py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors">
                Add Funds &rarr;
            </button>
        </form>
    </div>
</div>
@endsection
