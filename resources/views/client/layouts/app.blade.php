<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield("title", "My Account") - PNLCS</title>
    @vite(["resources/css/app.css"])
</head>
<body class="antialiased bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-200 min-h-screen">
<nav class="bg-white dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700 shadow-sm">
    <div class="max-w-7xl mx-auto px-4 flex items-center justify-between h-16">
        <a href="{{ route('client.home') }}" class="text-xl font-bold text-indigo-600">PNLCS</a>
        <div class="hidden md:flex items-center gap-6 text-sm">
            <a href="{{ route('client.services.index') }}" class="text-slate-600 hover:text-indigo-600 {{ request()->routeIs('client.services.*') ? 'text-indigo-600 font-medium' : '' }}">Services</a>
            <a href="{{ route('client.domains.index') }}" class="text-slate-600 hover:text-indigo-600 {{ request()->routeIs('client.domains.*') ? 'text-indigo-600 font-medium' : '' }}">Domains</a>
            <a href="{{ route('client.invoices.index') }}" class="text-slate-600 hover:text-indigo-600 {{ request()->routeIs('client.invoices.*') ? 'text-indigo-600 font-medium' : '' }}">Billing</a>
            <a href="{{ route('client.tickets.index') }}" class="text-slate-600 hover:text-indigo-600 {{ request()->routeIs('client.tickets.*') ? 'text-indigo-600 font-medium' : '' }}">Support</a>
            <a href="{{ route('client.kb.index') }}" class="text-slate-600 hover:text-indigo-600">KB</a>
            <form method="POST" action="{{ route('client.logout') }}" class="inline">@csrf<button class="text-red-500 hover:text-red-400 font-medium">Logout</button></form>
        </div>
    </div>
</nav>
<main class="max-w-7xl mx-auto px-4 py-8">
    @if(session('success'))<div class="mb-4 p-3 bg-emerald-50 border border-emerald-200 rounded-lg text-sm text-emerald-700">{{ session('success') }}</div>@endif
    @yield('content')
</main>
<footer class="border-t border-slate-200 dark:border-slate-700 py-6 text-center text-sm text-slate-400">&copy; {{ date('Y') }} PNLCS. All rights reserved.</footer>
</body>
</html>
