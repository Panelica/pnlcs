<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin') - PNLCS</title>
    @vite(['resources/css/app.css'])
</head>
<body class="h-full bg-slate-100 dark:bg-slate-900 text-slate-800 dark:text-slate-200" x-data="{ sidebarOpen: true }">
<div class="flex h-screen overflow-hidden">
    <aside x-show="sidebarOpen" class="fixed inset-y-0 left-0 z-30 w-64 bg-gradient-to-b from-indigo-950 via-slate-900 to-slate-950 shadow-2xl lg:relative flex flex-col">
        <div class="flex items-center h-16 px-6 border-b border-white/10">
            <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3">
                <div class="w-8 h-8 bg-indigo-500 rounded-lg flex items-center justify-center"><span class="text-white font-bold text-sm">P</span></div>
                <span class="text-xl font-bold text-white tracking-tight">PNLCS</span>
            </a>
        </div>
        <nav class="flex-1 overflow-y-auto py-4 px-3 space-y-1">
            <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium {{ request()->routeIs('admin.dashboard') ? 'bg-indigo-600/30 text-white' : 'text-slate-300 hover:bg-white/5 hover:text-white' }} transition-all">
                <x-heroicon-o-home class="w-5 h-5 shrink-0" /><span>Dashboard</span>
            </a>
            <div class="pt-4"><p class="px-3 text-xs font-semibold text-indigo-300/60 uppercase tracking-wider">Management</p></div>
            <a href="{{ route("admin.clients.index") }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium {{ request()->routeIs("admin.clients.*") ? "bg-indigo-600/30 text-white" : "text-slate-300 hover:bg-white/5 hover:text-white" }} transition-all"><x-heroicon-o-users class="w-5 h-5 shrink-0" /><span>Clients</span></a>
            <a href="{{ route("admin.orders.index") }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium {{ request()->routeIs("admin.orders.*") ? "bg-indigo-600/30 text-white" : "text-slate-300 hover:bg-white/5 hover:text-white" }} transition-all"><x-heroicon-o-shopping-cart class="w-5 h-5 shrink-0" /><span>Orders</span></a>
            <a href="{{ route("admin.invoices.index") }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium {{ request()->routeIs("admin.invoices.*") ? "bg-indigo-600/30 text-white" : "text-slate-300 hover:bg-white/5 hover:text-white" }} transition-all"><x-heroicon-o-credit-card class="w-5 h-5 shrink-0" /><span>Billing</span></a>
            <div class="pt-4"><p class="px-3 text-xs font-semibold text-indigo-300/60 uppercase tracking-wider">Services</p></div>
            <a href="{{ route("admin.services.index") }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium {{ request()->routeIs("admin.services.*") ? "bg-indigo-600/30 text-white" : "text-slate-300 hover:bg-white/5 hover:text-white" }} transition-all"><x-heroicon-o-server-stack class="w-5 h-5 shrink-0" /><span>Products/Services</span></a>
            <a href="{{ route("admin.domains.index") }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium {{ request()->routeIs("admin.domains.*") ? "bg-indigo-600/30 text-white" : "text-slate-300 hover:bg-white/5 hover:text-white" }} transition-all"><x-heroicon-o-globe-alt class="w-5 h-5 shrink-0" /><span>Domains</span></a>
            <div class="pt-4"><p class="px-3 text-xs font-semibold text-indigo-300/60 uppercase tracking-wider">Support</p></div>
            <a href="{{ route("admin.tickets.index") }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium {{ request()->routeIs("admin.tickets.*") ? "bg-indigo-600/30 text-white" : "text-slate-300 hover:bg-white/5 hover:text-white" }} transition-all"><x-heroicon-o-ticket class="w-5 h-5 shrink-0" /><span>Support Tickets</span></a>
            <a href="#" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium text-slate-300 hover:bg-white/5 hover:text-white transition-all"><x-heroicon-o-book-open class="w-5 h-5 shrink-0" /><span>Knowledge Base</span></a>
            <div class="pt-4"><p class="px-3 text-xs font-semibold text-indigo-300/60 uppercase tracking-wider">System</p></div>
            <a href="#" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium text-slate-300 hover:bg-white/5 hover:text-white transition-all"><x-heroicon-o-chart-bar class="w-5 h-5 shrink-0" /><span>Reports</span></a>
            <a href="{{ route("admin.settings.general") }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium {{ request()->routeIs("admin.settings.*") ? "bg-indigo-600/30 text-white" : "text-slate-300 hover:bg-white/5 hover:text-white" }} transition-all"><x-heroicon-o-cog-6-tooth class="w-5 h-5 shrink-0" /><span>Settings</span></a>
            <a href="#" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium text-slate-300 hover:bg-white/5 hover:text-white transition-all"><x-heroicon-o-puzzle-piece class="w-5 h-5 shrink-0" /><span>Addons</span></a>
        </nav>
        <div class="p-4 border-t border-white/10">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 bg-indigo-600 rounded-full flex items-center justify-center"><span class="text-white text-sm font-semibold">{{ substr(Auth::guard('admin')->user()->first_name, 0, 1) }}</span></div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-white truncate">{{ Auth::guard('admin')->user()->full_name }}</p>
                    <p class="text-xs text-slate-400 truncate">{{ Auth::guard('admin')->user()->role->name }}</p>
                </div>
            </div>
        </div>
    </aside>

    <div class="flex-1 flex flex-col overflow-hidden">
        <header class="h-16 bg-white dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between px-4 lg:px-6 shadow-sm">
            <div class="flex items-center gap-4">
                <button @click="sidebarOpen = !sidebarOpen" class="p-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors"><x-heroicon-o-bars-3 class="w-5 h-5" /></button>
            </div>
            <div class="flex items-center gap-3">
                <form method="POST" action="{{ route('admin.logout') }}">@csrf
                    <button type="submit" class="px-3 py-1.5 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 text-sm text-red-600 font-medium transition-colors">Sign Out</button>
                </form>
            </div>
        </header>
        <main class="flex-1 overflow-y-auto p-4 lg:p-6">@yield('content')</main>
    </div>
</div>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</body>
</html>
