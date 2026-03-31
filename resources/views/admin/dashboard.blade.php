@extends('admin.layouts.app')
@section('title', 'Dashboard')
@section('content')
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-slate-500">Total Clients</p>
                <p class="text-3xl font-bold mt-1">{{ $totalClients }}</p>
            </div>
            <div class="w-12 h-12 bg-indigo-100 rounded-xl flex items-center justify-center">
                <x-heroicon-o-users class="w-6 h-6 text-indigo-600" />
            </div>
        </div>
    </div>
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-slate-500">Active Clients</p>
                <p class="text-3xl font-bold mt-1">{{ $activeClients }}</p>
            </div>
            <div class="w-12 h-12 bg-emerald-100 rounded-xl flex items-center justify-center">
                <x-heroicon-o-check-circle class="w-6 h-6 text-emerald-600" />
            </div>
        </div>
    </div>
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-slate-500">Pending Orders</p>
                <p class="text-3xl font-bold mt-1">0</p>
            </div>
            <div class="w-12 h-12 bg-amber-100 rounded-xl flex items-center justify-center">
                <x-heroicon-o-shopping-cart class="w-6 h-6 text-amber-600" />
            </div>
        </div>
    </div>
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-slate-500">Open Tickets</p>
                <p class="text-3xl font-bold mt-1">0</p>
            </div>
            <div class="w-12 h-12 bg-rose-100 rounded-xl flex items-center justify-center">
                <x-heroicon-o-ticket class="w-6 h-6 text-rose-600" />
            </div>
        </div>
    </div>
</div>

<div class="bg-gradient-to-r from-indigo-600 to-violet-600 rounded-xl shadow-lg p-8 text-white mb-8">
    <h2 class="text-2xl font-bold">Welcome to PNLCS</h2>
    <p class="mt-2 text-indigo-100">Next-generation hosting billing platform.</p>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
        <h3 class="text-lg font-semibold mb-4">System Info</h3>
        <dl class="space-y-3 text-sm">
            <div class="flex justify-between"><dt class="text-slate-500">PNLCS</dt><dd class="font-medium">1.0.0-dev</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Laravel</dt><dd class="font-medium">{{ app()->version() }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">PHP</dt><dd class="font-medium">{{ phpversion() }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Admins</dt><dd class="font-medium">{{ $totalAdmins }}</dd></div>
        </dl>
    </div>
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
        <h3 class="text-lg font-semibold mb-4">Recent Activity</h3>
        <p class="text-slate-500 text-sm">No activity yet.</p>
    </div>
</div>
@endsection
