<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Account - PNLCS</title>
    @vite(["resources/css/app.css"])
</head>
<body class="antialiased bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-200">
<nav class="bg-white dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700 shadow-sm">
    <div class="max-w-7xl mx-auto px-4 flex items-center justify-between h-16">
        <a href="{{ route("client.home") }}" class="text-xl font-bold text-indigo-600">PNLCS</a>
        <div class="flex items-center gap-6 text-sm">
            <a href="#" class="text-slate-600 dark:text-slate-300 hover:text-indigo-600">Services</a>
            <a href="#" class="text-slate-600 dark:text-slate-300 hover:text-indigo-600">Domains</a>
            <a href="#" class="text-slate-600 dark:text-slate-300 hover:text-indigo-600">Billing</a>
            <a href="#" class="text-slate-600 dark:text-slate-300 hover:text-indigo-600">Support</a>
            <form method="POST" action="{{ route("client.logout") }}" class="inline">@csrf<button class="text-red-600 hover:text-red-500">Logout</button></form>
        </div>
    </div>
</nav>
<main class="max-w-7xl mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold mb-6">Welcome, {{ auth()->user()->full_name }}</h1>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6 text-center">
            <x-heroicon-o-server-stack class="w-8 h-8 text-indigo-500 mx-auto mb-2" />
            <p class="text-2xl font-bold">0</p>
            <p class="text-sm text-slate-500">Active Services</p>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6 text-center">
            <x-heroicon-o-globe-alt class="w-8 h-8 text-emerald-500 mx-auto mb-2" />
            <p class="text-2xl font-bold">0</p>
            <p class="text-sm text-slate-500">Domains</p>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6 text-center">
            <x-heroicon-o-document-text class="w-8 h-8 text-amber-500 mx-auto mb-2" />
            <p class="text-2xl font-bold">0</p>
            <p class="text-sm text-slate-500">Unpaid Invoices</p>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6 text-center">
            <x-heroicon-o-ticket class="w-8 h-8 text-rose-500 mx-auto mb-2" />
            <p class="text-2xl font-bold">0</p>
            <p class="text-sm text-slate-500">Open Tickets</p>
        </div>
    </div>
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
        <h3 class="text-lg font-semibold mb-4">Recent Activity</h3>
        <p class="text-slate-500 text-sm">No recent activity.</p>
    </div>
</main>
</body>
</html>
