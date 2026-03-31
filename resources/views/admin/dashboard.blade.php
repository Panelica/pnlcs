@extends("admin.layouts.app")
@section("title", "Dashboard")
@section("content")
<!-- Stats Row -->
<div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-8 gap-4 mb-8">
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-4 text-center">
        <p class="text-2xl font-bold text-indigo-600">{{ $totalClients }}</p>
        <p class="text-xs text-slate-500 mt-1">Clients</p>
    </div>
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-4 text-center">
        <p class="text-2xl font-bold text-emerald-600">{{ $activeServices }}</p>
        <p class="text-xs text-slate-500 mt-1">Services</p>
    </div>
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-4 text-center">
        <p class="text-2xl font-bold text-cyan-600">{{ $activeDomains }}</p>
        <p class="text-xs text-slate-500 mt-1">Domains</p>
    </div>
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-4 text-center">
        <p class="text-2xl font-bold text-amber-600">{{ $pendingOrders }}</p>
        <p class="text-xs text-slate-500 mt-1">Pending Orders</p>
    </div>
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-4 text-center">
        <p class="text-2xl font-bold text-rose-600">{{ $openTickets }}</p>
        <p class="text-xs text-slate-500 mt-1">Open Tickets</p>
    </div>
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-4 text-center">
        <p class="text-2xl font-bold text-orange-600">{{ $unpaidInvoices }}</p>
        <p class="text-xs text-slate-500 mt-1">Unpaid Invoices</p>
    </div>
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-4 text-center">
        <p class="text-2xl font-bold text-violet-600">{{ $totalAdmins }}</p>
        <p class="text-xs text-slate-500 mt-1">Staff</p>
    </div>
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-4 text-center">
        <p class="text-2xl font-bold text-green-600">${{ number_format($totalRevenue, 0) }}</p>
        <p class="text-xs text-slate-500 mt-1">Revenue</p>
    </div>
</div>

<!-- Welcome Banner -->
<div class="bg-gradient-to-r from-indigo-600 to-violet-600 rounded-xl shadow-lg p-6 text-white mb-8">
    <h2 class="text-xl font-bold">Welcome to PNLCS</h2>
    <p class="mt-1 text-indigo-100 text-sm">Next-generation hosting billing platform — WHMCS-compatible, Laravel 13 powered.</p>
    <div class="mt-3 flex gap-2">
        <a href="{{ route("admin.clients.create") }}" class="px-3 py-1.5 bg-white/20 hover:bg-white/30 rounded-lg text-xs font-medium backdrop-blur-sm transition-colors">Add Client</a>
        <a href="{{ route("admin.products.index") }}" class="px-3 py-1.5 bg-white/10 hover:bg-white/20 rounded-lg text-xs font-medium backdrop-blur-sm transition-colors">Configure Products</a>
        <a href="{{ route("admin.settings.general") }}" class="px-3 py-1.5 bg-white/10 hover:bg-white/20 rounded-lg text-xs font-medium backdrop-blur-sm transition-colors">Settings</a>
    </div>
</div>

<!-- Recent Activity Grid -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
    <!-- Recent Clients -->
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700">
        <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-700 flex justify-between items-center">
            <h3 class="font-semibold">Recent Clients</h3>
            <a href="{{ route("admin.clients.index") }}" class="text-xs text-indigo-600">View All</a>
        </div>
        <div class="divide-y divide-slate-100 dark:divide-slate-700">
            @forelse($recentClients as $client)
            <a href="{{ route("admin.clients.show", $client) }}" class="flex items-center gap-3 px-5 py-3 hover:bg-slate-50 dark:hover:bg-slate-700/30">
                <div class="w-8 h-8 bg-indigo-100 dark:bg-indigo-900/30 rounded-full flex items-center justify-center text-indigo-600 text-xs font-bold">{{ strtoupper(substr($client->first_name, 0, 1)) }}</div>
                <div class="flex-1 min-w-0"><p class="text-sm font-medium truncate">{{ $client->full_name }}</p><p class="text-xs text-slate-400">{{ $client->email }}</p></div>
            </a>
            @empty
            <p class="px-5 py-4 text-sm text-slate-400">No clients yet</p>
            @endforelse
        </div>
    </div>

    <!-- Recent Tickets -->
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700">
        <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-700 flex justify-between items-center">
            <h3 class="font-semibold">Recent Tickets</h3>
            <a href="{{ route("admin.tickets.index") }}" class="text-xs text-indigo-600">View All</a>
        </div>
        <div class="divide-y divide-slate-100 dark:divide-slate-700">
            @forelse($recentTickets as $ticket)
            <a href="{{ route("admin.tickets.show", $ticket) }}" class="block px-5 py-3 hover:bg-slate-50 dark:hover:bg-slate-700/30">
                <p class="text-sm font-medium truncate">#{{ $ticket->tid }} {{ $ticket->title }}</p>
                <p class="text-xs text-slate-400">{{ $ticket->department->name ?? "" }} · {{ ucfirst($ticket->status) }}</p>
            </a>
            @empty
            <p class="px-5 py-4 text-sm text-slate-400">No tickets yet</p>
            @endforelse
        </div>
    </div>

    <!-- Recent Orders -->
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700">
        <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-700 flex justify-between items-center">
            <h3 class="font-semibold">Recent Orders</h3>
            <a href="{{ route("admin.orders.index") }}" class="text-xs text-indigo-600">View All</a>
        </div>
        <div class="divide-y divide-slate-100 dark:divide-slate-700">
            @forelse($recentOrders as $order)
            <a href="{{ route("admin.orders.show", $order) }}" class="block px-5 py-3 hover:bg-slate-50 dark:hover:bg-slate-700/30">
                <p class="text-sm font-medium">#{{ $order->order_num }}</p>
                <p class="text-xs text-slate-400">{{ $order->client->full_name ?? "N/A" }} · ${{ number_format($order->amount, 2) }} · {{ ucfirst($order->status) }}</p>
            </a>
            @empty
            <p class="px-5 py-4 text-sm text-slate-400">No orders yet</p>
            @endforelse
        </div>
    </div>
</div>

<!-- System Info -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
        <h3 class="font-semibold mb-4">System Information</h3>
        <dl class="space-y-2 text-sm">
            <div class="flex justify-between"><dt class="text-slate-500">PNLCS</dt><dd class="font-medium">1.0.0-dev</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Laravel</dt><dd class="font-medium">{{ app()->version() }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">PHP</dt><dd class="font-medium">{{ phpversion() }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Database</dt><dd class="font-medium">MySQL {{ DB::selectOne("SELECT VERSION() as v")->v }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Server</dt><dd class="font-medium">{{ php_uname("n") }}</dd></div>
        </dl>
    </div>
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
        <h3 class="font-semibold mb-4">Quick Actions</h3>
        <div class="grid grid-cols-2 gap-2">
            <a href="{{ route("admin.clients.create") }}" class="flex items-center gap-2 px-3 py-2 rounded-lg bg-slate-50 dark:bg-slate-700 hover:bg-slate-100 text-sm"><x-heroicon-o-user-plus class="w-4 h-4 text-indigo-500" /> Add Client</a>
            <a href="{{ route("admin.products.create") }}" class="flex items-center gap-2 px-3 py-2 rounded-lg bg-slate-50 dark:bg-slate-700 hover:bg-slate-100 text-sm"><x-heroicon-o-cube class="w-4 h-4 text-emerald-500" /> Add Product</a>
            <a href="{{ route("admin.invoices.index") }}" class="flex items-center gap-2 px-3 py-2 rounded-lg bg-slate-50 dark:bg-slate-700 hover:bg-slate-100 text-sm"><x-heroicon-o-document-text class="w-4 h-4 text-amber-500" /> Invoices</a>
            <a href="{{ route("admin.tickets.index") }}" class="flex items-center gap-2 px-3 py-2 rounded-lg bg-slate-50 dark:bg-slate-700 hover:bg-slate-100 text-sm"><x-heroicon-o-ticket class="w-4 h-4 text-rose-500" /> Tickets</a>
        </div>
    </div>
</div>
@endsection
