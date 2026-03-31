<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin') - PNLCS</title>
    @vite(['resources/css/app.css'])
</head>
<body class="h-full bg-slate-100 dark:bg-slate-900 text-slate-800 dark:text-slate-200" x-data="{ sidebarOpen: true, mobileMenu: false }">
<div class="flex h-screen overflow-hidden">
    {{-- Mobile overlay --}}
    <div x-show="mobileMenu" x-transition:enter="transition-opacity ease-linear duration-200" x-transition:leave="transition-opacity ease-linear duration-200"
         class="fixed inset-0 z-40 bg-black/50 lg:hidden" x-on:click="mobileMenu = false" style="display:none;"></div>

    {{-- Sidebar --}}
    <aside :class="{ 'translate-x-0': mobileMenu, '-translate-x-full lg:translate-x-0': !mobileMenu, 'lg:w-64': sidebarOpen, 'lg:w-20': !sidebarOpen }"
           class="fixed inset-y-0 left-0 z-50 w-64 bg-gradient-to-b from-indigo-950 via-slate-900 to-slate-950 shadow-2xl lg:relative flex flex-col transition-all duration-200"
           x-data="{ configOpen: {{ request()->routeIs('admin.config.*') ? 'true' : 'false' }} }">

        {{-- Logo --}}
        <div class="flex items-center h-16 px-4 border-b border-white/10">
            <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3">
                <div class="w-8 h-8 bg-indigo-500 rounded-lg flex items-center justify-center shrink-0">
                    <span class="text-white font-bold text-sm">P</span>
                </div>
                <span x-show="sidebarOpen" class="text-xl font-bold text-white tracking-tight">PNLCS</span>
            </a>
        </div>

        {{-- Navigation --}}
        <nav class="flex-1 overflow-y-auto py-4 px-3 space-y-0.5 scrollbar-thin">

            @php
            $navItem = function($route, $icon, $label, $pattern = null) use (&$sidebarOpen) {
                $pattern = $pattern ?? $route;
                $active = request()->routeIs($pattern) ? 'bg-indigo-600/30 text-white' : 'text-slate-300 hover:bg-white/5 hover:text-white';
                return compact('route', 'icon', 'label', 'active');
            };
            @endphp

            {{-- Dashboard --}}
            <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('admin.dashboard') ? 'bg-indigo-600/30 text-white' : 'text-slate-300 hover:bg-white/5 hover:text-white' }} transition-all">
                <x-heroicon-o-home class="w-5 h-5 shrink-0"/>
                <span x-show="sidebarOpen">Dashboard</span>
            </a>

            {{-- Management --}}
            <div class="pt-4">
                <p x-show="sidebarOpen" class="px-3 text-[10px] font-semibold text-indigo-300/60 uppercase tracking-wider mb-1">Management</p>
            </div>
            <a href="{{ route('admin.clients.index') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('admin.clients.*') ? 'bg-indigo-600/30 text-white' : 'text-slate-300 hover:bg-white/5 hover:text-white' }} transition-all">
                <x-heroicon-o-users class="w-5 h-5 shrink-0"/>
                <span x-show="sidebarOpen">Clients</span>
            </a>
            <a href="{{ route('admin.orders.index') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('admin.orders.*') ? 'bg-indigo-600/30 text-white' : 'text-slate-300 hover:bg-white/5 hover:text-white' }} transition-all">
                <x-heroicon-o-shopping-cart class="w-5 h-5 shrink-0"/>
                <span x-show="sidebarOpen">Orders</span>
            </a>

            {{-- Billing --}}
            <div class="pt-4">
                <p x-show="sidebarOpen" class="px-3 text-[10px] font-semibold text-indigo-300/60 uppercase tracking-wider mb-1">Billing</p>
            </div>
            <a href="{{ route('admin.invoices.index') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('admin.invoices.*') ? 'bg-indigo-600/30 text-white' : 'text-slate-300 hover:bg-white/5 hover:text-white' }} transition-all">
                <x-heroicon-o-document-text class="w-5 h-5 shrink-0"/>
                <span x-show="sidebarOpen">Invoices</span>
            </a>
            <a href="{{ route('admin.config.transactions') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('admin.config.transactions') ? 'bg-indigo-600/30 text-white' : 'text-slate-300 hover:bg-white/5 hover:text-white' }} transition-all">
                <x-heroicon-o-banknotes class="w-5 h-5 shrink-0"/>
                <span x-show="sidebarOpen">Transactions</span>
            </a>
            <a href="{{ route('admin.quotes.index') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('admin.quotes.*') ? 'bg-indigo-600/30 text-white' : 'text-slate-300 hover:bg-white/5 hover:text-white' }} transition-all">
                <x-heroicon-o-document-duplicate class="w-5 h-5 shrink-0"/>
                <span x-show="sidebarOpen">Quotes</span>
            </a>
            <a href="{{ '#' }}" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium {{ request()->is('admin/projects*') ? 'bg-indigo-600/30 text-white' : 'text-slate-300 hover:bg-white/5 hover:text-white' }} transition-all">
                <x-heroicon-o-briefcase class="w-5 h-5 shrink-0"/>
                <span x-show="sidebarOpen">Projects</span>
            </a>

            {{-- Services --}}
            <div class="pt-4">
                <p x-show="sidebarOpen" class="px-3 text-[10px] font-semibold text-indigo-300/60 uppercase tracking-wider mb-1">Services</p>
            </div>
            <a href="{{ route('admin.products.index') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('admin.products.*') ? 'bg-indigo-600/30 text-white' : 'text-slate-300 hover:bg-white/5 hover:text-white' }} transition-all">
                <x-heroicon-o-cube class="w-5 h-5 shrink-0"/>
                <span x-show="sidebarOpen">Products</span>
            </a>
            <a href="{{ route('admin.services.index') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('admin.services.*') ? 'bg-indigo-600/30 text-white' : 'text-slate-300 hover:bg-white/5 hover:text-white' }} transition-all">
                <x-heroicon-o-server-stack class="w-5 h-5 shrink-0"/>
                <span x-show="sidebarOpen">Services</span>
            </a>
            <a href="{{ route('admin.domains.index') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('admin.domains.*') ? 'bg-indigo-600/30 text-white' : 'text-slate-300 hover:bg-white/5 hover:text-white' }} transition-all">
                <x-heroicon-o-globe-alt class="w-5 h-5 shrink-0"/>
                <span x-show="sidebarOpen">Domains</span>
            </a>

            {{-- Support --}}
            <div class="pt-4">
                <p x-show="sidebarOpen" class="px-3 text-[10px] font-semibold text-indigo-300/60 uppercase tracking-wider mb-1">Support</p>
            </div>
            <a href="{{ route('admin.tickets.index') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('admin.tickets.*') ? 'bg-indigo-600/30 text-white' : 'text-slate-300 hover:bg-white/5 hover:text-white' }} transition-all">
                <x-heroicon-o-ticket class="w-5 h-5 shrink-0"/>
                <span x-show="sidebarOpen">Tickets</span>
            </a>
            <a href="{{ route('admin.config.knowledge-base') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('admin.config.knowledge-base*') ? 'bg-indigo-600/30 text-white' : 'text-slate-300 hover:bg-white/5 hover:text-white' }} transition-all">
                <x-heroicon-o-book-open class="w-5 h-5 shrink-0"/>
                <span x-show="sidebarOpen">Knowledge Base</span>
            </a>
            <a href="{{ route('admin.config.announcements') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('admin.config.announcements') ? 'bg-indigo-600/30 text-white' : 'text-slate-300 hover:bg-white/5 hover:text-white' }} transition-all">
                <x-heroicon-o-megaphone class="w-5 h-5 shrink-0"/>
                <span x-show="sidebarOpen">Announcements</span>
            </a>
            <a href="{{ route('admin.config.network-issues') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('admin.config.network-issues') ? 'bg-indigo-600/30 text-white' : 'text-slate-300 hover:bg-white/5 hover:text-white' }} transition-all">
                <x-heroicon-o-signal-slash class="w-5 h-5 shrink-0"/>
                <span x-show="sidebarOpen">Network Status</span>
            </a>

            {{-- Utilities --}}
            <div class="pt-4">
                <p x-show="sidebarOpen" class="px-3 text-[10px] font-semibold text-indigo-300/60 uppercase tracking-wider mb-1">Utilities</p>
            </div>
            <a href="{{ route('admin.config.todo') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('admin.config.todo') ? 'bg-indigo-600/30 text-white' : 'text-slate-300 hover:bg-white/5 hover:text-white' }} transition-all">
                <x-heroicon-o-clipboard-document-check class="w-5 h-5 shrink-0"/>
                <span x-show="sidebarOpen">To-Do List</span>
            </a>
            <a href="{{ route('admin.reports.index') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('admin.reports.*') ? 'bg-indigo-600/30 text-white' : 'text-slate-300 hover:bg-white/5 hover:text-white' }} transition-all">
                <x-heroicon-o-chart-bar class="w-5 h-5 shrink-0"/>
                <span x-show="sidebarOpen">Reports</span>
            </a>
            <a href="{{ route('admin.config.downloads') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('admin.config.downloads') ? 'bg-indigo-600/30 text-white' : 'text-slate-300 hover:bg-white/5 hover:text-white' }} transition-all">
                <x-heroicon-o-arrow-down-tray class="w-5 h-5 shrink-0"/>
                <span x-show="sidebarOpen">Downloads</span>
            </a>

            {{-- Configuration (collapsible) --}}
            <div class="pt-4">
                <button x-on:click="configOpen = !configOpen" class="flex items-center justify-between w-full px-3 text-[10px] font-semibold text-indigo-300/60 uppercase tracking-wider mb-1 hover:text-indigo-300/80">
                    <span x-show="sidebarOpen">Configuration</span>
                    <x-heroicon-o-chevron-down x-show="sidebarOpen" class="w-3.5 h-3.5 transition-transform" x-bind:class="{ 'rotate-180': configOpen }"/>
                </button>
            </div>
            <div x-show="configOpen" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0" class="space-y-0.5">
                <a href="{{ route('admin.settings.general') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('admin.settings.*') ? 'bg-indigo-600/30 text-white' : 'text-slate-300 hover:bg-white/5 hover:text-white' }} transition-all">
                    <x-heroicon-o-cog-6-tooth class="w-5 h-5 shrink-0"/>
                    <span x-show="sidebarOpen">General Settings</span>
                </a>
                <a href="{{ route('admin.config.admins') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('admin.config.admins') ? 'bg-indigo-600/30 text-white' : 'text-slate-300 hover:bg-white/5 hover:text-white' }} transition-all">
                    <x-heroicon-o-user-group class="w-5 h-5 shrink-0"/>
                    <span x-show="sidebarOpen">Staff</span>
                </a>
                <a href="{{ route('admin.config.admin-roles') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('admin.config.admin-roles') ? 'bg-indigo-600/30 text-white' : 'text-slate-300 hover:bg-white/5 hover:text-white' }} transition-all">
                    <x-heroicon-o-shield-check class="w-5 h-5 shrink-0"/>
                    <span x-show="sidebarOpen">Admin Roles</span>
                </a>
                <a href="{{ route('admin.config.api-credentials') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('admin.config.api-credentials') ? 'bg-indigo-600/30 text-white' : 'text-slate-300 hover:bg-white/5 hover:text-white' }} transition-all">
                    <x-heroicon-o-key class="w-5 h-5 shrink-0"/>
                    <span x-show="sidebarOpen">API Credentials</span>
                </a>
                <a href="{{ route('admin.config.currencies') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('admin.config.currencies') ? 'bg-indigo-600/30 text-white' : 'text-slate-300 hover:bg-white/5 hover:text-white' }} transition-all">
                    <x-heroicon-o-currency-dollar class="w-5 h-5 shrink-0"/>
                    <span x-show="sidebarOpen">Currencies</span>
                </a>
                <a href="{{ route('admin.config.tax') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('admin.config.tax') ? 'bg-indigo-600/30 text-white' : 'text-slate-300 hover:bg-white/5 hover:text-white' }} transition-all">
                    <x-heroicon-o-calculator class="w-5 h-5 shrink-0"/>
                    <span x-show="sidebarOpen">Tax Rules</span>
                </a>
                <a href="{{ route('admin.config.gateways') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('admin.config.gateways') ? 'bg-indigo-600/30 text-white' : 'text-slate-300 hover:bg-white/5 hover:text-white' }} transition-all">
                    <x-heroicon-o-credit-card class="w-5 h-5 shrink-0"/>
                    <span x-show="sidebarOpen">Payment Gateways</span>
                </a>
                <a href="{{ route('admin.config.promotions') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('admin.config.promotions') ? 'bg-indigo-600/30 text-white' : 'text-slate-300 hover:bg-white/5 hover:text-white' }} transition-all">
                    <x-heroicon-o-tag class="w-5 h-5 shrink-0"/>
                    <span x-show="sidebarOpen">Promotions</span>
                </a>
                <a href="{{ route('admin.config.servers') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('admin.config.servers') ? 'bg-indigo-600/30 text-white' : 'text-slate-300 hover:bg-white/5 hover:text-white' }} transition-all">
                    <x-heroicon-o-server class="w-5 h-5 shrink-0"/>
                    <span x-show="sidebarOpen">Servers</span>
                </a>
                <a href="{{ route('admin.config.domain-pricing') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('admin.config.domain-pricing') ? 'bg-indigo-600/30 text-white' : 'text-slate-300 hover:bg-white/5 hover:text-white' }} transition-all">
                    <x-heroicon-o-globe-americas class="w-5 h-5 shrink-0"/>
                    <span x-show="sidebarOpen">Domain Pricing</span>
                </a>
                <a href="{{ route('admin.config.registrars') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('admin.config.registrars') ? 'bg-indigo-600/30 text-white' : 'text-slate-300 hover:bg-white/5 hover:text-white' }} transition-all">
                    <x-heroicon-o-globe-alt class="w-5 h-5 shrink-0"/>
                    <span x-show="sidebarOpen">Domain Registrars</span>
                </a>
                <a href="{{ route('admin.config.ticket-departments') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('admin.config.ticket-departments') ? 'bg-indigo-600/30 text-white' : 'text-slate-300 hover:bg-white/5 hover:text-white' }} transition-all">
                    <x-heroicon-o-building-office class="w-5 h-5 shrink-0"/>
                    <span x-show="sidebarOpen">Ticket Depts</span>
                </a>
                <a href="{{ route('admin.config.ticket-statuses') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('admin.config.ticket-statuses') ? 'bg-indigo-600/30 text-white' : 'text-slate-300 hover:bg-white/5 hover:text-white' }} transition-all">
                    <x-heroicon-o-tag class="w-5 h-5 shrink-0"/>
                    <span x-show="sidebarOpen">Ticket Statuses</span>
                </a>
                <a href="{{ route('admin.config.email-templates') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('admin.config.email-templates') ? 'bg-indigo-600/30 text-white' : 'text-slate-300 hover:bg-white/5 hover:text-white' }} transition-all">
                    <x-heroicon-o-envelope class="w-5 h-5 shrink-0"/>
                    <span x-show="sidebarOpen">Email Templates</span>
                </a>
                <a href="{{ route('admin.config.banned-ips') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('admin.config.banned-ips') ? 'bg-indigo-600/30 text-white' : 'text-slate-300 hover:bg-white/5 hover:text-white' }} transition-all">
                    <x-heroicon-o-shield-exclamation class="w-5 h-5 shrink-0"/>
                    <span x-show="sidebarOpen">Security</span>
                </a>
                <a href="{{ route('admin.config.affiliates') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('admin.config.affiliates') ? 'bg-indigo-600/30 text-white' : 'text-slate-300 hover:bg-white/5 hover:text-white' }} transition-all">
                    <x-heroicon-o-link class="w-5 h-5 shrink-0"/>
                    <span x-show="sidebarOpen">Affiliates</span>
                </a>
                <a href="{{ route('admin.config.activity-log') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('admin.config.activity-log') ? 'bg-indigo-600/30 text-white' : 'text-slate-300 hover:bg-white/5 hover:text-white' }} transition-all">
                    <x-heroicon-o-clipboard-document-list class="w-5 h-5 shrink-0"/>
                    <span x-show="sidebarOpen">Activity Log</span>
                </a>
                <a href="{{ route('admin.config.system-database') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('admin.config.system-database') ? 'bg-indigo-600/30 text-white' : 'text-slate-300 hover:bg-white/5 hover:text-white' }} transition-all">
                    <x-heroicon-o-circle-stack class="w-5 h-5 shrink-0"/>
                    <span x-show="sidebarOpen">System</span>
                </a>
            </div>
        </nav>

        {{-- User --}}
        <div class="p-4 border-t border-white/10">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 bg-indigo-600 rounded-full flex items-center justify-center shrink-0">
                    <span class="text-white text-sm font-semibold">{{ substr(Auth::guard('admin')->user()->first_name ?? 'A', 0, 1) }}</span>
                </div>
                <div x-show="sidebarOpen" class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-white truncate">{{ Auth::guard('admin')->user()->full_name ?? 'Admin' }}</p>
                    <p class="text-xs text-slate-400 truncate">{{ Auth::guard('admin')->user()->role->name ?? 'Administrator' }}</p>
                </div>
            </div>
        </div>
    </aside>

    {{-- Main content --}}
    <div class="flex-1 flex flex-col overflow-hidden">
        <header class="h-16 bg-white dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between px-4 lg:px-6 shadow-sm">
            <div class="flex items-center gap-4">
                <button x-on:click="sidebarOpen = !sidebarOpen" class="hidden lg:block p-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">
                    <x-heroicon-o-bars-3 class="w-5 h-5"/>
                </button>
                <button x-on:click="mobileMenu = !mobileMenu" class="lg:hidden p-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">
                    <x-heroicon-o-bars-3 class="w-5 h-5"/>
                </button>
                <h1 class="text-lg font-semibold text-slate-900 dark:text-white">@yield('title', 'Dashboard')</h1>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ url('/') }}" target="_blank" class="p-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors text-slate-500" title="View Site">
                    <x-heroicon-o-arrow-top-right-on-square class="w-5 h-5"/>
                </a>
                <form method="POST" action="{{ route('admin.logout') }}">@csrf
                    <button type="submit" class="px-3 py-1.5 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 text-sm text-red-600 font-medium transition-colors">Sign Out</button>
                </form>
            </div>
        </header>
        <main class="flex-1 overflow-y-auto p-4 lg:p-6">
            @yield('content')
        </main>
    </div>
</div>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
@stack('scripts')
</body>
</html>
