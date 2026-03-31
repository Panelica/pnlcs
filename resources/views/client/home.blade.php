@extends("client.layouts.app")
@section("title", "My Account")
@section("content")
<h1 class="text-2xl font-bold mb-6">Welcome, {{ auth()->user()->full_name }}</h1>
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <a href="{{ route("client.services.index") }}" class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6 text-center hover:border-indigo-300 transition-colors">
        <x-heroicon-o-server-stack class="w-8 h-8 text-indigo-500 mx-auto mb-2" /><p class="text-2xl font-bold">0</p><p class="text-sm text-slate-500">Active Services</p>
    </a>
    <a href="{{ route("client.domains.index") }}" class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6 text-center hover:border-indigo-300 transition-colors">
        <x-heroicon-o-globe-alt class="w-8 h-8 text-emerald-500 mx-auto mb-2" /><p class="text-2xl font-bold">0</p><p class="text-sm text-slate-500">Domains</p>
    </a>
    <a href="{{ route("client.invoices.index") }}" class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6 text-center hover:border-indigo-300 transition-colors">
        <x-heroicon-o-document-text class="w-8 h-8 text-amber-500 mx-auto mb-2" /><p class="text-2xl font-bold">0</p><p class="text-sm text-slate-500">Unpaid Invoices</p>
    </a>
    <a href="{{ route("client.tickets.index") }}" class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6 text-center hover:border-indigo-300 transition-colors">
        <x-heroicon-o-ticket class="w-8 h-8 text-rose-500 mx-auto mb-2" /><p class="text-2xl font-bold">0</p><p class="text-sm text-slate-500">Open Tickets</p>
    </a>
</div>
<div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
    <h3 class="font-semibold mb-4">Recent Activity</h3>
    <p class="text-sm text-slate-400">No recent activity.</p>
</div>
@endsection
