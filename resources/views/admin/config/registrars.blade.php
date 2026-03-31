@extends('admin.layouts.app')
@section('title', 'Domain Registrars')
@section('content')

<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Domain Registrars</h1>
</div>

<x-flash-message/>

<p class="text-sm text-slate-500 dark:text-slate-400 mb-6">
    Enable and configure domain registrar integrations for automatic domain registration and management.
</p>

@php
$registrars = [
    [
        'name'        => 'WHMCS None / Manual',
        'key'         => 'manual',
        'description' => 'No automatic registration. Admin processes domain orders manually.',
        'icon'        => 'hand-raised',
        'color'       => 'bg-slate-500',
    ],
    [
        'name'        => 'Enom',
        'key'         => 'enom',
        'description' => 'Integrate with Enom for automatic domain registration via API.',
        'icon'        => 'globe-alt',
        'color'       => 'bg-blue-600',
    ],
    [
        'name'        => 'ResellerClub',
        'key'         => 'resellerclub',
        'description' => 'Use ResellerClub (LogicBoxes) HTTP API for domain management.',
        'icon'        => 'globe-alt',
        'color'       => 'bg-indigo-600',
    ],
    [
        'name'        => 'Namecheap',
        'key'         => 'namecheap',
        'description' => 'Integrate with Namecheap API for domain registration and management.',
        'icon'        => 'globe-alt',
        'color'       => 'bg-orange-500',
    ],
    [
        'name'        => 'Cloudflare',
        'key'         => 'cloudflare',
        'description' => 'Use Cloudflare Registrar API for domain management.',
        'icon'        => 'shield-check',
        'color'       => 'bg-amber-500',
    ],
];
@endphp

<div class="grid grid-cols-1 sm:grid-cols-2 gap-4" x-data="{ activeRegistrar: null }">
    @foreach($registrars as $registrar)
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
        <div class="flex items-start justify-between gap-4">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 {{ $registrar['color'] }} rounded-xl flex items-center justify-center shadow-sm">
                    @if($registrar['icon'] === 'hand-raised')
                        <x-heroicon-o-hand-raised class="w-6 h-6 text-white"/>
                    @elseif($registrar['icon'] === 'shield-check')
                        <x-heroicon-o-shield-check class="w-6 h-6 text-white"/>
                    @else
                        <x-heroicon-o-globe-alt class="w-6 h-6 text-white"/>
                    @endif
                </div>
                <div>
                    <h3 class="font-semibold text-slate-900 dark:text-white">{{ $registrar['name'] }}</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ $registrar['description'] }}</p>
                </div>
            </div>
            <div class="flex items-center gap-1 shrink-0" x-data="{ active: '{{ $registrar['key'] }}' === 'manual' }">
                <button x-on:click="active = !active" :class="active ? 'bg-indigo-600' : 'bg-slate-200 dark:bg-slate-600'" class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none">
                    <span :class="active ? 'translate-x-6' : 'translate-x-1'" class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform"></span>
                </button>
            </div>
        </div>

        @if($registrar['key'] !== 'manual')
        <div class="mt-4 pt-4 border-t border-slate-100 dark:border-slate-700">
            <button x-on:click="activeRegistrar = activeRegistrar === '{{ $registrar['key'] }}' ? null : '{{ $registrar['key'] }}'"
                    class="inline-flex items-center gap-1.5 text-sm text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 font-medium transition">
                <x-heroicon-o-cog-6-tooth class="w-4 h-4"/>
                <span x-text="activeRegistrar === '{{ $registrar['key'] }}' ? 'Hide Settings' : 'Configure'"></span>
            </button>
        </div>
        <div x-show="activeRegistrar === '{{ $registrar['key'] }}'" x-cloak class="mt-4 pt-4 border-t border-slate-100 dark:border-slate-700">
            <form method="POST" action="{{ route('admin.config.registrars.settings.update', $registrar['key']) }}">
                @csrf
                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Username / API User</label>
                        <input type="text" name="username" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">API Key / Password</label>
                        <input type="password" name="api_key" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm">
                    </div>
                    @if($registrar['key'] === 'resellerclub')
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Reseller ID</label>
                        <input type="text" name="reseller_id" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm">
                    </div>
                    @endif
                    <div class="flex items-center gap-2">
                        <input type="checkbox" name="test_mode" value="1" id="test_{{ $registrar['key'] }}" class="w-4 h-4 text-indigo-600 rounded">
                        <label for="test_{{ $registrar['key'] }}" class="text-xs text-slate-600 dark:text-slate-400">Test / Sandbox mode</label>
                    </div>
                </div>
                <div class="flex justify-end mt-4">
                    <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition">Save Settings</button>
                </div>
            </form>
        </div>
        @endif
    </div>
    @endforeach
</div>
@endsection
