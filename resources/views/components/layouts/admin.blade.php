<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full" x-data="{ sidebarOpen: true, darkMode: localStorage.getItem('pnlcs-dark') === 'true' }" :class="{ 'dark': darkMode }">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Admin' }} - PNLCS</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="h-full bg-slate-100 dark:bg-slate-900 text-slate-800 dark:text-slate-200">
    <div class="flex h-screen overflow-hidden">

        <!-- Sidebar -->
        <aside x-show="sidebarOpen" x-transition:enter="transition-transform duration-300"
               x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0"
               x-transition:leave="transition-transform duration-300"
               x-transition:leave-start="translate-x-0" x-transition:leave-end="-translate-x-full"
               class="fixed inset-y-0 left-0 z-30 w-64 bg-gradient-to-b from-indigo-950 via-slate-900 to-slate-950 shadow-2xl lg:relative lg:translate-x-0 flex flex-col">

            <!-- Logo -->
            <div class="flex items-center h-16 px-6 border-b border-white/10">
                <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3">
                    <div class="w-8 h-8 bg-indigo-500 rounded-lg flex items-center justify-center">
                        <span class="text-white font-bold text-sm">P</span>
                    </div>
                    <span class="text-xl font-bold text-white tracking-tight">PNLCS</span>
                </a>
            </div>

            <!-- Navigation -->
            <nav class="flex-1 overflow-y-auto py-4 px-3 space-y-1">
                <!-- Dashboard -->
                <a href="{{ route('admin.dashboard') }}"
                   class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all duration-200 {{ request()->routeIs('admin.dashboard') ? 'bg-indigo-600/30 text-white' : 'text-slate-300 hover:bg-white/5 hover:text-white' }}">
                    <x-heroicon-o-home class="w-5 h-5 shrink-0" />
                    <span>Dashboard</span>
                </a>

                <!-- Clients -->
                <div class="pt-4">
                    <p class="px-3 text-xs font-semibold text-indigo-300/60 uppercase tracking-wider">Management</p>
                </div>
                <a href="#" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium text-slate-300 hover:bg-white/5 hover:text-white transition-all duration-200">
                    <x-heroicon-o-users class="w-5 h-5 shrink-0" />
                    <span>Clients</span>
                </a>
                <a href="#" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium text-slate-300 hover:bg-white/5 hover:text-white transition-all duration-200">
                    <x-heroicon-o-shopping-cart class="w-5 h-5 shrink-0" />
                    <span>Orders</span>
                </a>
                <a href="#" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium text-slate-300 hover:bg-white/5 hover:text-white transition-all duration-200">
                    <x-heroicon-o-credit-card class="w-5 h-5 shrink-0" />
                    <span>Billing</span>
                </a>

                <!-- Services -->
                <div class="pt-4">
                    <p class="px-3 text-xs font-semibold text-indigo-300/60 uppercase tracking-wider">Services</p>
                </div>
                <a href="#" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium text-slate-300 hover:bg-white/5 hover:text-white transition-all duration-200">
                    <x-heroicon-o-server-stack class="w-5 h-5 shrink-0" />
                    <span>Products/Services</span>
                </a>
                <a href="#" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium text-slate-300 hover:bg-white/5 hover:text-white transition-all duration-200">
                    <x-heroicon-o-globe-alt class="w-5 h-5 shrink-0" />
                    <span>Domains</span>
                </a>

                <!-- Support -->
                <div class="pt-4">
                    <p class="px-3 text-xs font-semibold text-indigo-300/60 uppercase tracking-wider">Support</p>
                </div>
                <a href="#" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium text-slate-300 hover:bg-white/5 hover:text-white transition-all duration-200">
                    <x-heroicon-o-ticket class="w-5 h-5 shrink-0" />
                    <span>Support Tickets</span>
                </a>
                <a href="#" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium text-slate-300 hover:bg-white/5 hover:text-white transition-all duration-200">
                    <x-heroicon-o-book-open class="w-5 h-5 shrink-0" />
                    <span>Knowledge Base</span>
                </a>

                <!-- System -->
                <div class="pt-4">
                    <p class="px-3 text-xs font-semibold text-indigo-300/60 uppercase tracking-wider">System</p>
                </div>
                <a href="#" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium text-slate-300 hover:bg-white/5 hover:text-white transition-all duration-200">
                    <x-heroicon-o-chart-bar class="w-5 h-5 shrink-0" />
                    <span>Reports</span>
                </a>
                <a href="#" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium text-slate-300 hover:bg-white/5 hover:text-white transition-all duration-200">
                    <x-heroicon-o-cog-6-tooth class="w-5 h-5 shrink-0" />
                    <span>Settings</span>
                </a>
                <a href="#" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium text-slate-300 hover:bg-white/5 hover:text-white transition-all duration-200">
                    <x-heroicon-o-puzzle-piece class="w-5 h-5 shrink-0" />
                    <span>Addons</span>
                </a>
            </nav>

            <!-- User Info -->
            <div class="p-4 border-t border-white/10">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 bg-indigo-600 rounded-full flex items-center justify-center">
                        <span class="text-white text-sm font-semibold">{{ substr(Auth::guard('admin')->user()->first_name, 0, 1) }}</span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-white truncate">{{ Auth::guard('admin')->user()->full_name }}</p>
                        <p class="text-xs text-slate-400 truncate">{{ Auth::guard('admin')->user()->role->name }}</p>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden">

            <!-- Top Navigation -->
            <header class="h-16 bg-white dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between px-4 lg:px-6 shadow-sm">
                <div class="flex items-center gap-4">
                    <!-- Toggle Sidebar -->
                    <button @click="sidebarOpen = !sidebarOpen" class="p-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">
                        <x-heroicon-o-bars-3 class="w-5 h-5" />
                    </button>

                    <!-- Quick Actions -->
                    <div class="hidden md:flex items-center gap-2">
                        <button class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-medium rounded-lg transition-colors">
                            <x-heroicon-s-plus class="w-4 h-4" />
                            New Client
                        </button>
                        <button class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-sm font-medium rounded-lg transition-colors">
                            <x-heroicon-s-plus class="w-4 h-4" />
                            New Order
                        </button>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <!-- Search -->
                    <div class="hidden lg:block relative">
                        <x-heroicon-o-magnifying-glass class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
                        <input type="text" placeholder="Search clients, invoices, tickets..."
                               class="w-72 pl-9 pr-4 py-2 bg-slate-100 dark:bg-slate-700 border-0 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 transition-all">
                    </div>

                    <!-- Dark Mode Toggle -->
                    <button @click="darkMode = !darkMode; localStorage.setItem('pnlcs-dark', darkMode)"
                            class="p-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">
                        <x-heroicon-o-moon x-show="!darkMode" class="w-5 h-5" />
                        <x-heroicon-o-sun x-show="darkMode" class="w-5 h-5" />
                    </button>

                    <!-- Notifications -->
                    <button class="p-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors relative">
                        <x-heroicon-o-bell class="w-5 h-5" />
                        <span class="absolute top-1 right-1 w-2 h-2 bg-red-500 rounded-full"></span>
                    </button>

                    <!-- Profile Dropdown -->
                    <div x-data="{ open: false }" class="relative">
                        <button @click="open = !open" class="flex items-center gap-2 p-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">
                            <div class="w-8 h-8 bg-indigo-600 rounded-full flex items-center justify-center">
                                <span class="text-white text-sm font-semibold">{{ substr(Auth::guard('admin')->user()->first_name, 0, 1) }}</span>
                            </div>
                            <x-heroicon-s-chevron-down class="w-4 h-4 text-slate-400" />
                        </button>
                        <div x-show="open" @click.away="open = false" x-transition
                             class="absolute right-0 mt-2 w-48 bg-white dark:bg-slate-800 rounded-lg shadow-lg border border-slate-200 dark:border-slate-700 py-1 z-50">
                            <a href="#" class="block px-4 py-2 text-sm hover:bg-slate-100 dark:hover:bg-slate-700">My Account</a>
                            <hr class="my-1 border-slate-200 dark:border-slate-700">
                            <form method="POST" action="{{ route('admin.logout') }}">
                                @csrf
                                <button type="submit" class="block w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-slate-100 dark:hover:bg-slate-700">Sign Out</button>
                            </form>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Page Content -->
            <main class="flex-1 overflow-y-auto p-4 lg:p-6">
                {{ $slot }}
            </main>
        </div>
    </div>

    @livewireScripts
</body>
</html>
