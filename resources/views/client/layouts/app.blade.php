<!DOCTYPE html>
<html lang="en" x-data="{ mobileOpen: false }">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'My Account') - PNLCS</title>
    @vite(['resources/css/app.css'])
</head>
<body class="antialiased bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-200 min-h-screen">

<nav class="bg-white dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700 shadow-sm relative z-50">
    <div class="max-w-7xl mx-auto px-4">
        <div class="flex items-center justify-between h-16">
            {{-- Logo --}}
            <a href="{{ route('client.home') }}" class="text-xl font-bold text-indigo-600 flex-shrink-0">PNLCS</a>

            {{-- Desktop Nav --}}
            <div class="hidden lg:flex items-center gap-1 text-sm">
                {{-- Home --}}
                <a href="{{ route('client.home') }}" class="px-3 py-2 rounded-lg {{ request()->routeIs('client.home') ? 'text-indigo-600 font-medium bg-indigo-50 dark:bg-indigo-900/20' : 'text-slate-600 dark:text-slate-300 hover:text-indigo-600 hover:bg-slate-100 dark:hover:bg-slate-700' }}">Home</a>

                {{-- Services --}}
                <div class="relative group">
                    <button class="flex items-center gap-1 px-3 py-2 rounded-lg {{ request()->routeIs('client.services.*') ? 'text-indigo-600 font-medium bg-indigo-50 dark:bg-indigo-900/20' : 'text-slate-600 dark:text-slate-300 hover:text-indigo-600 hover:bg-slate-100 dark:hover:bg-slate-700' }}">
                        Services
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div class="absolute left-0 top-full pt-1 hidden group-hover:block w-48">
                        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-lg border border-slate-200 dark:border-slate-700 py-1">
                            <a href="{{ route('client.services.index') }}" class="block px-4 py-2 text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700">My Services</a>
                            <a href="#" class="block px-4 py-2 text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700">Order New Service</a>
                        </div>
                    </div>
                </div>

                {{-- Domains --}}
                <div class="relative group">
                    <button class="flex items-center gap-1 px-3 py-2 rounded-lg {{ request()->routeIs('client.domains.*') ? 'text-indigo-600 font-medium bg-indigo-50 dark:bg-indigo-900/20' : 'text-slate-600 dark:text-slate-300 hover:text-indigo-600 hover:bg-slate-100 dark:hover:bg-slate-700' }}">
                        Domains
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div class="absolute left-0 top-full pt-1 hidden group-hover:block w-48">
                        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-lg border border-slate-200 dark:border-slate-700 py-1">
                            <a href="{{ route('client.domains.index') }}" class="block px-4 py-2 text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700">My Domains</a>
                            <a href="#" class="block px-4 py-2 text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700">Register Domain</a>
                        </div>
                    </div>
                </div>

                {{-- Billing --}}
                <div class="relative group">
                    <button class="flex items-center gap-1 px-3 py-2 rounded-lg {{ request()->routeIs('client.invoices.*') || request()->routeIs('client.funds.*') ? 'text-indigo-600 font-medium bg-indigo-50 dark:bg-indigo-900/20' : 'text-slate-600 dark:text-slate-300 hover:text-indigo-600 hover:bg-slate-100 dark:hover:bg-slate-700' }}">
                        Billing
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div class="absolute left-0 top-full pt-1 hidden group-hover:block w-48">
                        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-lg border border-slate-200 dark:border-slate-700 py-1">
                            <a href="{{ route('client.invoices.index') }}" class="block px-4 py-2 text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700">Invoices</a>
                            <a href="{{ route('client.funds.index') }}" class="block px-4 py-2 text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700">Add Funds</a>
                        </div>
                    </div>
                </div>

                {{-- Support --}}
                <div class="relative group">
                    <button class="flex items-center gap-1 px-3 py-2 rounded-lg {{ request()->routeIs('client.tickets.*') || request()->routeIs('client.kb.*') || request()->routeIs('client.announcements.*') || request()->routeIs('client.contact') || request()->routeIs('client.downloads.*') ? 'text-indigo-600 font-medium bg-indigo-50 dark:bg-indigo-900/20' : 'text-slate-600 dark:text-slate-300 hover:text-indigo-600 hover:bg-slate-100 dark:hover:bg-slate-700' }}">
                        Support
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div class="absolute left-0 top-full pt-1 hidden group-hover:block w-52">
                        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-lg border border-slate-200 dark:border-slate-700 py-1">
                            <a href="{{ route('client.tickets.index') }}" class="block px-4 py-2 text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700">My Tickets</a>
                            <a href="{{ route('client.tickets.create') }}" class="block px-4 py-2 text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700">Open Ticket</a>
                            <a href="{{ route('client.kb.index') }}" class="block px-4 py-2 text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700">Knowledge Base</a>
                            <a href="{{ route('client.announcements.index') }}" class="block px-4 py-2 text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700">Announcements</a>
                            <a href="{{ route('client.contact') }}" class="block px-4 py-2 text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700">Contact Us</a>
                            <a href="{{ route('client.downloads.index') }}" class="block px-4 py-2 text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700">Downloads</a>
                        </div>
                    </div>
                </div>

                {{-- Affiliates --}}
                <a href="{{ route('client.affiliates.index') }}" class="px-3 py-2 rounded-lg {{ request()->routeIs('client.affiliates.*') ? 'text-indigo-600 font-medium bg-indigo-50 dark:bg-indigo-900/20' : 'text-slate-600 dark:text-slate-300 hover:text-indigo-600 hover:bg-slate-100 dark:hover:bg-slate-700' }}">Affiliates</a>

                {{-- Account --}}
                <div class="relative group">
                    <button class="flex items-center gap-1 px-3 py-2 rounded-lg text-slate-600 dark:text-slate-300 hover:text-indigo-600 hover:bg-slate-100 dark:hover:bg-slate-700">
                        {{ auth()->user()?->first_name ?? 'Account' }}
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div class="absolute right-0 top-full pt-1 hidden group-hover:block w-48">
                        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-lg border border-slate-200 dark:border-slate-700 py-1">
                            <a href="#" class="block px-4 py-2 text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700">Profile</a>
                            <a href="#" class="block px-4 py-2 text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700">Security</a>
                            <div class="border-t border-slate-200 dark:border-slate-700 my-1"></div>
                            <form method="POST" action="{{ route('client.logout') }}">
                                @csrf
                                <button type="submit" class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20">Logout</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Mobile hamburger --}}
            <button class="lg:hidden p-2 rounded-lg text-slate-600 hover:bg-slate-100 dark:hover:bg-slate-700"
                x-on:click="mobileOpen = !mobileOpen">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path x-show="!mobileOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    <path x-show="mobileOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        {{-- Mobile Menu --}}
        <div x-show="mobileOpen" x-transition class="lg:hidden pb-4 space-y-1 text-sm">
            <a href="{{ route('client.home') }}" class="block px-3 py-2 rounded-lg text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700">Home</a>
            <div class="pt-1 pb-1"><p class="px-3 text-xs font-semibold text-slate-400 uppercase tracking-wider">Services</p></div>
            <a href="{{ route('client.services.index') }}" class="block px-3 py-2 rounded-lg text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700">My Services</a>
            <div class="pt-1 pb-1"><p class="px-3 text-xs font-semibold text-slate-400 uppercase tracking-wider">Domains</p></div>
            <a href="{{ route('client.domains.index') }}" class="block px-3 py-2 rounded-lg text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700">My Domains</a>
            <div class="pt-1 pb-1"><p class="px-3 text-xs font-semibold text-slate-400 uppercase tracking-wider">Billing</p></div>
            <a href="{{ route('client.invoices.index') }}" class="block px-3 py-2 rounded-lg text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700">Invoices</a>
            <a href="{{ route('client.funds.index') }}" class="block px-3 py-2 rounded-lg text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700">Add Funds</a>
            <div class="pt-1 pb-1"><p class="px-3 text-xs font-semibold text-slate-400 uppercase tracking-wider">Support</p></div>
            <a href="{{ route('client.tickets.index') }}" class="block px-3 py-2 rounded-lg text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700">My Tickets</a>
            <a href="{{ route('client.tickets.create') }}" class="block px-3 py-2 rounded-lg text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700">Open Ticket</a>
            <a href="{{ route('client.kb.index') }}" class="block px-3 py-2 rounded-lg text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700">Knowledge Base</a>
            <a href="{{ route('client.announcements.index') }}" class="block px-3 py-2 rounded-lg text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700">Announcements</a>
            <a href="{{ route('client.contact') }}" class="block px-3 py-2 rounded-lg text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700">Contact Us</a>
            <a href="{{ route('client.downloads.index') }}" class="block px-3 py-2 rounded-lg text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700">Downloads</a>
            <div class="pt-1 pb-1"><p class="px-3 text-xs font-semibold text-slate-400 uppercase tracking-wider">Account</p></div>
            <a href="{{ route('client.affiliates.index') }}" class="block px-3 py-2 rounded-lg text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700">Affiliates</a>
            <form method="POST" action="{{ route('client.logout') }}">
                @csrf
                <button type="submit" class="w-full text-left px-3 py-2 rounded-lg text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20">Logout</button>
            </form>
        </div>
    </div>
</nav>

<main class="max-w-7xl mx-auto px-4 py-8">
    @if(session('success'))
        <div class="mb-4 p-3 bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 rounded-lg text-sm text-emerald-700 dark:text-emerald-400">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="mb-4 p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg text-sm text-red-700 dark:text-red-400">{{ session('error') }}</div>
    @endif
    @if(session('info'))
        <div class="mb-4 p-3 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg text-sm text-blue-700 dark:text-blue-400">{{ session('info') }}</div>
    @endif
    @yield('content')
</main>

<footer class="border-t border-slate-200 dark:border-slate-700 py-6 text-center text-sm text-slate-400">&copy; {{ date('Y') }} PNLCS. All rights reserved.</footer>
</body>
</html>
