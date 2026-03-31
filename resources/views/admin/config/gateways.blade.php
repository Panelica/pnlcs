@extends('admin.layouts.app')
@section('title', 'Payment Gateways')
@section('content')

<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Payment Gateways</h1>
</div>

<x-flash-message/>

<p class="text-sm text-slate-500 dark:text-slate-400 mb-6">
    Enable and configure payment gateways that clients can use to pay invoices.
</p>

@php
$gateways = [
    [
        'name'        => 'Bank Transfer',
        'key'         => 'bank_transfer',
        'description' => 'Accept manual bank wire transfers. Admin marks invoices paid manually.',
        'icon'        => 'building-library',
        'color'       => 'bg-blue-500',
    ],
    [
        'name'        => 'PayPal',
        'key'         => 'paypal',
        'description' => 'Accept PayPal payments via PayPal Standard or REST API.',
        'icon'        => 'credit-card',
        'color'       => 'bg-indigo-500',
    ],
    [
        'name'        => 'Stripe',
        'key'         => 'stripe',
        'description' => 'Accept credit/debit cards securely via Stripe Checkout.',
        'icon'        => 'credit-card',
        'color'       => 'bg-purple-500',
    ],
    [
        'name'        => 'Crypto',
        'key'         => 'crypto',
        'description' => 'Accept cryptocurrency payments via CoinGate or similar providers.',
        'icon'        => 'currency-dollar',
        'color'       => 'bg-amber-500',
    ],
];
@endphp

<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-2 gap-4" x-data="{ activeGateway: null }">
    @foreach($gateways as $gateway)
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
        <div class="flex items-start justify-between gap-4">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 {{ $gateway['color'] }} rounded-xl flex items-center justify-center shadow-sm">
                    @if($gateway['icon'] === 'building-library')
                        <x-heroicon-o-building-library class="w-6 h-6 text-white"/>
                    @elseif($gateway['icon'] === 'currency-dollar')
                        <x-heroicon-o-currency-dollar class="w-6 h-6 text-white"/>
                    @else
                        <x-heroicon-o-credit-card class="w-6 h-6 text-white"/>
                    @endif
                </div>
                <div>
                    <h3 class="font-semibold text-slate-900 dark:text-white">{{ $gateway['name'] }}</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ $gateway['description'] }}</p>
                </div>
            </div>
            <div class="flex items-center gap-1 shrink-0" x-data="{ active: false }">
                <button x-on:click="active = !active" :class="active ? 'bg-indigo-600' : 'bg-slate-200 dark:bg-slate-600'" class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none">
                    <span :class="active ? 'translate-x-6' : 'translate-x-1'" class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform"></span>
                </button>
            </div>
        </div>
        <div class="mt-4 pt-4 border-t border-slate-100 dark:border-slate-700">
            <button x-on:click="activeGateway = activeGateway === '{{ $gateway['key'] }}' ? null : '{{ $gateway['key'] }}'"
                    class="inline-flex items-center gap-1.5 text-sm text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 font-medium transition">
                <x-heroicon-o-cog-6-tooth class="w-4 h-4"/>
                <span x-text="activeGateway === '{{ $gateway['key'] }}' ? 'Hide Settings' : 'Configure'"></span>
            </button>
        </div>
        <div x-show="activeGateway === '{{ $gateway['key'] }}'" x-cloak class="mt-4 pt-4 border-t border-slate-100 dark:border-slate-700">
            <form method="POST" action="{{ route('admin.config.gateways.settings.update', $gateway['key']) }}">
                @csrf
                @if($gateway['key'] === 'bank_transfer')
                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Bank Name</label>
                        <input type="text" name="bank_name" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Account Name</label>
                        <input type="text" name="account_name" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Account Number / IBAN</label>
                        <input type="text" name="account_number" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Payment Instructions</label>
                        <textarea name="instructions" rows="3" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm"></textarea>
                    </div>
                </div>
                @elseif($gateway['key'] === 'paypal')
                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">PayPal Email / Client ID</label>
                        <input type="text" name="paypal_email" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Secret Key</label>
                        <input type="password" name="paypal_secret" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm">
                    </div>
                    <div class="flex items-center gap-2">
                        <input type="checkbox" name="paypal_sandbox" value="1" id="paypal_sandbox" class="w-4 h-4 text-indigo-600 rounded">
                        <label for="paypal_sandbox" class="text-xs text-slate-600 dark:text-slate-400">Sandbox / Test mode</label>
                    </div>
                </div>
                @elseif($gateway['key'] === 'stripe')
                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Publishable Key</label>
                        <input type="text" name="stripe_public" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm font-mono">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Secret Key</label>
                        <input type="password" name="stripe_secret" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm font-mono">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Webhook Secret</label>
                        <input type="password" name="stripe_webhook" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm font-mono">
                    </div>
                </div>
                @else
                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">API Key</label>
                        <input type="text" name="api_key" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">API Secret</label>
                        <input type="password" name="api_secret" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm">
                    </div>
                </div>
                @endif
                <div class="flex justify-end mt-4">
                    <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition">Save Settings</button>
                </div>
            </form>
        </div>
    </div>
    @endforeach
</div>
@endsection
